# AI Research Synthesis V1 (Tahap 16)

Sintesis AI yang HANYA bersandar pada Evidence Bundle (Tahap 15). **Provider nyata tidak aktif**: hanya provider uji (Mock) yang tersambung, tanpa jaringan.

## Alur

`Workspace → "Analisis dengan AI" → konfirmasi → GroundedSynthesisService::run`

1. `EvidenceBundleBuilder::build` (server membangun ulang dari kategori + kueri; klien tidak dipercaya).
2. `ResearchSynthesisRequest` (alat + prompt AKTIF + versinya, kueri, kategori, konteks percakapan ≤ 4 pesan bertanda "NOT EVIDENCE", bundle, aturan keluaran). Hanya bukti yang lolos anggaran bundle yang dikirim.
3. Bundle tanpa teks bukti (semua metadata) → berhenti `insufficient_evidence`, provider TIDAK dipanggil.
4. `GroundedProvider::complete` → teks JSON mentah. `GroundedProviderManager` menolak provider selain `mock` (`ProviderNotEnabled`).
5. `StructuredResponseParser` (skema) → `SynthesisValidator` (sitasi, klaim, sumber, keterbatasan) → status `valid | partial | invalid`.
6. Simpan: `ai_runs` (+`user_parameters_json`: mode, versi prompt, hash request/bundle), `ai_run_evidence` (snapshot), `ai_run_results` (hasil), `audit_logs`.

Alur riset Pustaka lama (`ProcessResearchRun` / `ResearchRunService` / `CitationValidator`) tidak diubah.

## Persistensi (butuh migration, DRAFT belum dijalankan)

`ai_run_retrievals` ber-FK ke `document_chunks` sehingga tak bisa memuat chunk full-text eksternal/abstrak. Draf `2026_11_25_000001_create_ai_run_evidence_table`: tabel baru `ai_run_evidence`, **tanpa FK ke dokumen/publikasi**; menyimpan SALINAN teks, `text_sha256`, checksum sumber/chunk, halaman, level, lisensi, provenance. Model `AiRunEvidence` menolak `update` (tidak berubah). Tanpa migration, UI jujur menyatakan analisis belum tersedia dan tidak menjalankan apa pun.

## Kontrak keluaran model (tidak bergantung vendor)

`{answer, claims:[{text, claim_type, citations[], quote?}], limitations[], sources_used[]}`. Penanda sitasi di `answer`: `[EXT:929:C12:P8-9]`, `[EXT:923:ABS]`, `[INT:DOC-3:C5:P12]`.

## Status validasi

- **fatal → invalid**: JSON/skema rusak, sitasi karangan (sumber/evidence/halaman tidak ada), `sources_used` di luar bundle, tanpa klaim sah.
- **claim → partial** (invalid bila tak ada klaim sah): jenis klaim tak dikenal, klaim tak didukung level bukti (mis. klaim teks lengkap dari abstrak), metadata dipakai untuk klaim isi, kutipan tak ada di teks bukti.
- **soft → partial**: keterbatasan tidak disalin, penanda jawaban ≠ klaim, `sources_used` tak sama dengan sumber yang disitasi.
- Tidak ada perbaikan diam-diam. Keluaran yang ditolak disimpan sebagai `answer_unverified` dan hanya tampil dalam `<details>` berperingatan. Keterbatasan sistem SELALU dilampirkan pada hasil.

## Mock (skenario uji)

`config('research.synthesis.mock_scenario')` / env `RESEARCH_SYNTHESIS_MOCK_SCENARIO` (bukan opsi pengguna): `valid`, `fake_citation`, `wrong_page`, `abstract_as_fulltext`, `metadata_substantive`, `missing_limitations`, `malformed_json`, `schema_violation`, `source_not_in_bundle`, `uncited_marker`, `wrong_claim_type`.

## Batasan jujur

- Validator memeriksa ID, halaman, level, jenis klaim, dan kutipan; ia tidak menilai apakah teks klaim benar-benar *sesuai* isi bukti.
- Pertanyaan lanjutan (follow-up) belum diimplementasikan; kontrak sudah menyiapkan `conversation_context`, tetapi UI/penyimpanan induk-run adalah tahap terpisah.
- Run sintesis belum tersambung ke Simpan Hasil / Generate Document / riwayat percakapan.

---

# Tahap 17B — Adaptor provider AI nyata (siap, TIDAK pernah dipanggil)

Tidak ada satu pun permintaan live yang dilakukan pada tahap ini. Semua tes memakai `Http::fake` + `Http::preventStrayRequests`; QA browser memakai server OpenAI PALSU lokal.

## Abstraksi

`GroundedAiProvider::complete(ResearchSynthesisRequest): {raw, meta}`. Implementasi: `GroundedMockProvider`, `OpenAiGroundedProvider`. Menambah Gemini/OpenRouter = satu kelas + satu baris di `GroundedProviderManager::REAL`; Evidence Pipeline, parser, validator, snapshot, dan UI tidak berubah. `GroundedSynthesisService` tidak menyebut OpenAI maupun `Http::` (diuji).

## Tiga mode (`GroundedProviderManager::mode()`)

| mode | arti |
|---|---|
| `mock` | default; tanpa jaringan dan tanpa biaya |
| `real-disabled` | provider nyata dipilih, tetapi masih ada penghalang (live off, kunci, model, atau tarif belum ada); tidak ada permintaan |
| `real-enabled` | provider=openai + `live_enabled` (admin) + kunci di server + model + kedua tarif |

Sintesis memakai `research.synthesis.provider`, TERPISAH dari `research.ai_provider` (alur riset lama). Mengisi `OPENAI_API_KEY` atau memilih openai di alur lama tidak mengaktifkan sintesis nyata. Tarif tidak punya nilai bawaan (tidak mengarang harga).

## Pagar biaya (`SynthesisBudget`), semuanya SEBELUM AiRun dan permintaan dibuat

Perkiraan token masukan = ceil(byte prompt / 3) + 450 (heuristik konservatif, BUKAN tokenizer). Biaya maksimum = masukan x tarif + `max_output_tokens` x tarif. Ditolak (`SynthesisRejected`, audit `research.synthesis_budget_exceeded` / `_rate_limited`) bila: token masukan > batas, biaya maksimum > batas per permintaan, kuota harian per pengguna/global habis (dihitung dari `ai_runs` provider nyata), batas per menit, atau permintaan identik sedang berjalan (kunci cache per sidik jari). Mock tidak dibatasi dan tidak memakai kuota nyata.

## Timeout, retry, laju

connect_timeout 10 dtk, timeout per permintaan (admin), `total_timeout` 110 dtk. Retry hanya galat transien (timeout, koneksi, 408, 5xx, 429 non-kuota), maksimum `max_retries` (0-3), exponential backoff 1, 2, 4... dtk atau `Retry-After` (dilewati bila > 20 dtk). Tanpa retry: 401/403, 400/404/422 (model/parameter), kuota habis, keluaran buruk. Semua percobaan di dalam SATU AiRun. Catatan: timeout yang diulang dapat tertagih dua kali di sisi provider; default `max_retries` = 1.

## Status gagal (`failure_state`, pesan tetap, tanpa galat mentah)

`provider_unavailable`, `provider_auth_failed`, `provider_rate_limited`, `provider_quota_exhausted`, `provider_timeout`, `provider_invalid_response`, `provider_schema_failed`, `citation_failed`, `budget_exceeded`. Status AiRun tetap `failed` / `citation_failed` / `insufficient_evidence` (tanpa migration).

## Pencatatan pemakaian

`ai_run_results.structured_output_json.usage_accounting`: provider, model, token masukan/keluaran/total, durasi, jumlah percobaan, `request_id` (hanya bila lolos pola aman), perkiraan token dan biaya maksimum, biaya aktual (usage dilaporkan provider x tarif admin; null bila usage/tarif tidak ada). `provider_meta.usage` memakai format yang dibaca analitik Admin yang sudah ada. Tidak disimpan: kunci, header Authorization, galat mentah, teks bukti di audit (bukti ada di snapshot).

## Admin (Penyedia AI > "Sintesis AI berbasis bukti")

Menampilkan mode, kunci hanya "Terkonfigurasi"/"Belum dikonfigurasi", model, status live, tarif, batas biaya, timeout, retry, token, kuota. Mengaktifkan live ditolak tanpa provider nyata + kunci + model + dua tarif. Menyimpan konfigurasi tidak memanggil provider. Kunci API tidak dapat disimpan lewat pengaturan.

## ClaimEvidenceVerifier

Kontrak `ClaimEvidenceVerifier` + baseline `LexicalClaimEvidenceVerifier`: ada teks bukti, tumpang-tindih kata isi (>= 0,5), angka klaim yang tidak ada di bukti, ketidakcocokan negasi; kutipan dan label halaman/judul tidak dihitung. Hasil per klaim: `verified_structurally` atau `needs_semantic_review` (+ alasan). Ini BUKAN entailment semantik dan tidak mengubah status valid/partial/invalid; UI memberi label dan catatan itu. LLM judge dapat dipasang nanti dengan mengganti binding di `AppServiceProvider`.

## Retensi (rancangan, tidak ada yang dihapus)

Ukuran terukur di DB asli (24 Nov 2026): run uji #6 = 12 baris snapshot (12,2 KB teks) + hasil JSON 18 KB (termasuk `raw_response` hingga 20 KB). Perkiraan 35 sampai 60 KB per run sebelum overhead InnoDB; 10.000 run kira-kira 0,4 sampai 0,6 GB.
- **Run selesai (`completed`)**: simpan; ini hasil akademik. Rekomendasi: tanpa penghapusan otomatis.
- **Run gagal / ditolak tanpa hasil**: aman diarsipkan setelah 90 hari; snapshot-nya ikut (cascade), hasilnya tidak bernilai akademik.
- **`raw_response`**: kandidat pertama untuk dipangkas (misalnya setelah 30 hari) karena sudah terwakili klaim tervalidasi; hanya kolom JSON itu, bukan snapshot.
- **Snapshot evidence**: bukti audit; jangan dihapus otomatis tanpa keputusan produk. Opsi: pemegang akun menghapus run miliknya (cascade), atau ekspor lalu arsip.
- **Audit log**: kebijakan terpisah; metadata sintesis tidak memuat teks bukti sehingga murah disimpan lama.
- Perlu keputusan produk sebelum implementasi: apakah pengguna dapat menghapus run, masa simpan minimum untuk kebutuhan akademik, dan apakah arsip dingin diperlukan.

## Syarat SATU permintaan live pertama (checklist)

1. `OPENAI_API_KEY` di environment server (bukan di pengaturan).
2. Admin mengisi model, tarif input dan output (dari halaman harga resmi provider), batas biaya per permintaan, dan kuota; lalu mengaktifkan mode live.
3. Uji koneksi (tanpa biaya) di halaman Penyedia AI untuk memastikan kunci sah dan model tersedia.
4. Lakukan SATU analisis dari Workspace dengan kueri kecil; periksa halaman hasil (usage, biaya, status validasi) dan audit.
5. Keputusan arsitektur: panggilan sintesis berjalan sinkron di request web (batas `total_timeout` 110 dtk). Untuk lalu lintas nyata, pindahkan ke antrean khusus.
6. Verifikasi pagar anggaran dengan batas biaya sengaja kecil sebelum membuka kuota.
