# Full-Text Acquisition V1 (Tahap 13)

Menemukan lokasi teks lengkap artikel eksternal, memeriksa izin, mengunduh secara aman, mengekstrak, dan mengindeks sebagai evidence.
**Belum ada unduhan nyata, belum ada migration di database asli, tidak ada AI.** Semua diuji terisolasi.

## Alur

```
metadata (external_publications) --Periksa Full Text--> lokasi (external_fulltexts) --LicensePolicy--> izin
  allow  -> DOWNLOAD_ELIGIBLE --Unduh & Indeks (admin)--> DOWNLOAD_QUEUED -> DOWNLOADING -> FULL_TEXT_STORED -> FULL_TEXT_INDEXED
  review -> LICENSE_REVIEW_REQUIRED --Tinjau Lisensi (admin + catatan bukti)--> ELIGIBLE | ditolak
  deny / landing / tdm -> FULL_TEXT_LINK_FOUND (tidak pernah diunduh otomatis)
  gagal  -> DOWNLOAD_FAILED | EXTRACTION_FAILED (Retry; ekstraksi ulang tanpa unduh ulang)
```

Status baris hanya berpindah lewat `FullTextState` + `ExternalFulltext::transition()` (compare-and-set atomik). `METADATA_ONLY` / `ABSTRACT_AVAILABLE` adalah status tingkat publikasi (turunan data).

## Komponen (`app/Services/FullText/`)

| Kelas | Tugas |
|---|---|
| `FullTextLocator` | lokasi dari data tersimpan; OpenAlex (`best_oa_location`, `locations`); Crossref (`license`, `link`) |
| `LicensePolicy` | normalisasi lisensi + keputusan izin (default-deny) |
| `UrlGuard` | SSRF: HTTPS, port 443, tanpa kredensial/IP literal, DNS IPv4+IPv6 semuanya publik, IP dikunci ke koneksi |
| `SecureDownloader` | streaming ke `.part`, redirect manual (tiap lompatan divalidasi), batas ukuran, MIME + magic `%PDF-` + `%%EOF`, cooldown/Retry-After per host |
| `FullTextExtractor` | PDF per halaman (pdftotext bila ada, fallback smalot), TEI/JATS anti-XXE; tanpa OCR |
| `SectionSegmenter`, `FullTextChunker` | bagian baku saja, referensi ditandai, halaman awal/akhir, checksum |
| `FullTextIngestor` | unduh → simpan → ekstrak → chunk → indeks; idempoten, checkpoint, aman batal/duplikat/crash |
| `FullTextService` | check, tinjau, antre (batas), batal, retry, hapus salinan, pulihkan job macet, statistik |
| `ExternalEvidence` | retrieval chunk tanpa bias ID + paket bukti (full_text / abstract / metadata) |

## Aturan izin

Otomatis boleh diunduh hanya bila: berkas langsung (`pdf`/`xml`) **dan** open access terkonfirmasi penyedia **dan** lisensi ∈ `auto_licenses` (`cc0`, `cc-by`) **dan** versi ∈ `publishedVersion`/`acceptedVersion` **dan** lisensi tingkat artikel. Selain itu:

- lisensi tak diketahui / NC / ND / SA / publisher-specific / public-domain mark / versi tak jelas / lisensi tingkat jurnal (DOAJ) / OA tak terkonfirmasi → `LICENSE_REVIEW_REQUIRED`
- tertutup → ditolak; halaman arahan dan tautan TDM Crossref → `FULL_TEXT_LINK_FOUND`, tidak bisa disetujui untuk unduhan.
- Izin diperiksa ulang saat unduhan berjalan.

Data aktual saat audit: 965 dari 970 publikasi **tanpa lisensi**; 937 berstatus `OPEN_ACCESS_LINK` hanya karena flag OA. Itu sebabnya hampir semua akan masuk tinjauan.

## Migration (DRAFT, belum dijalankan di DB asli)

`database/migrations/2026_11_24_000001_create_external_fulltext_tables.php`: dua tabel baru (`external_fulltexts`, `external_fulltext_chunks`), tidak mengubah tabel yang ada. Divalidasi di database scratch MariaDB (lalu di-DROP) dan tes pengaman panjang pengenal. Sebelum dijalankan, fitur tampil nonaktif dengan pesan jujur (`FullTextService::ready()`).

## Antrean dan batas

Antrean `fulltext` (`php artisan queue:work --queue=fulltext`), terpisah dari `research` dan `ingestion`. Batas (config `external-sources.fulltext`): antre ≤ 25, unduh bersamaan ≤ 2, file ≤ 25 MB, ≤ 400 halaman, ≤ 2 jt karakter, ≤ 800 chunk, kuota 2 GB, sisa disk ≥ 1 GB, percobaan ≤ 3 dengan backoff, interval 1,5 dtk per host. Token antrean mencegah job usang berefek; `recoverStale()` mengembalikan unduhan macet (>15 mnt) ke antrean.

## Estimasi penyimpanan (ASUMSI, belum diukur pada PDF nyata)

PDF artikel OA ±0,5–2 MB; teks ekstraksi + chunk ±60–150 KB di database. ±1.000 artikel ≈ 1–2 GB berkas + 100–150 MB DB. Kuota bawaan 2 GB ≈ 1.000–2.000 PDF. Disk bebas saat audit: 227 GB.

## KeywordRetriever (audit)

Temuan: `limit(400)` tanpa urutan pada seluruh chunk dokumen terpilih → chunk paling relevan ber-ID besar bisa terbuang sebelum diranking. Perbaikan tanpa migration: seluruh chunk yang memuat istilah dinilai (streaming per 500, `lazyById`), disimpan hanya `max_per_document` terbaik per dokumen (hasil akhir identik dengan menilai semuanya). `candidate_limit` kini = jumlah kandidat terbaik yang dipertahankan; `scan_limit` (50.000) = pagar pengaman, tercapai → `wasScanLimited()`. Batasan: LIKE tetap O(chunk yang cocok); FULLTEXT adalah langkah berikut bila Pustaka membesar.

## Keamanan

Tindakan hanya Admin (grup `admin`, `web` + CSRF); tidak ada rute yang menyajikan berkas; disk `external_fulltext` privat tanpa URL; nama berkas dibuat server; audit tanpa URL/isi; dokumen privat Pustaka tidak pernah dianggap tersedia (`eligibleForResearch`); XML dengan DOCTYPE/ENTITY ditolak.

## Batasan jujur

- Heading tidak baku/digabung baris tidak dikenali (section = null); baris tunggal "References" di tengah isi dianggap heading.
- JATS ber-DOCTYPE ditolak (anti-XXE).
- Kind `pdf` hanya untuk tautan jelas (`.pdf`, segmen `/pdf`, atau deklarasi penyedia); galley OJS `/article/download/...` dianggap halaman arahan.
- Unpaywall: hanya diaudit sebagai opsi, tidak diintegrasikan.
- Ekstraksi smalot memakai memori besar untuk PDF besar; pdftotext lebih hemat tetapi belum tentu terpasang di server.
- Retrieval chunk eksternal belum disambungkan ke pipeline research (sengaja; menunggu tahap sintesis AI).
