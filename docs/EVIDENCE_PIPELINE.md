# Research Evidence Pipeline V1 (Tahap 15)

Menyatukan bukti dari Pustaka Internal, abstrak eksternal, dan full-text eksternal terindeks menjadi satu **Evidence Bundle** yang siap diteruskan ke AI Research tool. **Tidak ada AI, unduhan, Sync, atau migration.**

## Alur (`App\Services\Evidence\EvidenceBundleBuilder`)

1. Source Discovery (analisis query deterministik, peringkat relevansi; Pustaka dan eksternal bersaing apa adanya).
2. Ambil ≤ 5 sumber teratas **menurut urutan relevansi** (DOI ganda disaring; dokumen privat/draf/kategori lain dibuang).
3. Per sumber: chunk teks (Pustaka atau full-text eksternal `FULL_TEXT_INDEXED`) → bila tidak ada, abstrak → bila tidak ada, **metadata saja (tidak pernah menghasilkan teks evidence)**.
4. Anggaran bergiliran antar sumber: `research.evidence` (`max_chunks` 12, `max_per_source` 3, `max_chars` 16.000; env `RESEARCH_EVIDENCE_BUNDLE_*`).
5. Peta sitasi + keterbatasan otomatis (`limitations_must_be_preserved = true`).

Relevansi (skor sumber, menentukan urutan) dan kedalaman bukti (level, menentukan apa yang boleh diklaim) adalah dua hal terpisah: PDF tersedia tidak mengubah peringkat.

## Model

- `evidence_level`: `METADATA_ONLY` | `ABSTRACT` | `FULL_TEXT`; `source_kind`: `internal_document` | `external_abstract` | `external_fulltext`.
- Item: `evidence_id` (EV01…), `citation_id`, judul, penulis, tahun, DOI, provider, `source_url` (DOI/landing publik, tidak pernah path berkas), section (bila ada), `page_start/end`, `text`, `source_checksum`, `chunk_checksum`, lisensi, `provenance`, `relevance {source_rank, source_score, chunk_score}`, `claim_support`, `allowed_claims`.
- Skor chunk = skor dasar retriever + bonus frasa persis (≤ 3 × 0,05) + konteks judul (≤ 0,03). Setara → halaman lebih awal, lalu nomor chunk. ID hanya penentu akhir. Bagian artikel tidak dipakai untuk peringkat.

## Kontrak sitasi (`CitationId`, `EvidenceCitationValidator`)

`INT:DOC-3` / `EXT:929` (sumber, bibliografi saja) · `INT:DOC-3:C5:P12` / `EXT:929:C12:P8-9` (chunk + halaman) · `EXT:929:ABS` (abstrak).
Ditolak: `malformed_id`, `unknown_source`, `unknown_evidence`, `page_mismatch`, `unknown_claim_type`, `claim_not_supported`, `metadata_only`, `quote_not_found`.
Klaim: `bibliographic` (sumber apa pun) · `abstract_statement` (butuh `…:ABS`) · `fulltext_statement` (butuh chunk teks lengkap; abstrak/metadata ditolak). Dukungan: `BIBLIOGRAPHIC_ONLY` / `ABSTRACT_SUPPORTED` / `FULLTEXT_SUPPORTED`.

## Batasan jujur

- Belum disambungkan ke `AiRun`/`ProcessResearchRun`/`CitationValidator` lama: `ai_run_retrievals` memiliki FK ke `document_chunks`, jadi menyimpan evidence eksternal di sana butuh **migration** (menunggu izin).
- Peringkat chunk bersifat leksikal (bukan semantik); chunk 400 kata bisa memuat banyak topik. Label bagian dari PDF sering tidak akurat: gunakan halaman.
- Chunk Pustaka dipotong KeywordRetriever per dokumen sebelum bonus frasa; bonus frasa penuh hanya pada chunk eksternal.
- Validator tidak bisa menilai apakah sebuah klaim benar-benar *isi* kutipan (hanya bahwa kutipan/halaman/level sah); `quote` opsional memeriksa teks.
