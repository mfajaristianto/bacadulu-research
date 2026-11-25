# Source Discovery V1 — referensi paling relevan tanpa AI

Halaman: `GET /research/references` (nama rute `research.references`). Kode: `app/Services/Discovery/*`,
`ReferenceDiscoveryController`, `resources/views/research/references/*`.

## Alur

1. **Pemahaman pertanyaan** (`QueryAnalyzer`, deterministik, tanpa LLM): normalisasi (huruf kecil, tanpa diakritik), kata fungsi (ID/EN)
   memisahkan **frasa**; kata riset umum (pengaruh, analisis, effect, impact, ...) tetap menjadi istilah tetapi berbobot rendah dan tidak
   menentukan kelayakan. Tahun/bahasa hanya dianggap petunjuk bila ditulis eksplisit ("tahun 2021", "in english").
2. **Kandidat tanpa pemotongan menurut ID (V1.2)**: Pustaka Internal = dokumen kategori yang memuat istilah (judul/abstrak/isi chunk), maks
   `internal_limit` (2.000; bila tercapai hasil ditandai tidak pasti). Sumber Eksternal = `ExternalCandidateFinder`: SQL menghitung untuk tiap
   baris **batas atas skor** `ub` (dijamin >= skor sebenarnya) dan mengurutkan menurut `ub`; PHP menilai baris menurut urutan itu
   (kelompok tumbuh 25, 50, 100, 200, 250...) dan berhenti hanya bila `ub` baris berikutnya < skor ke-N yang sudah pasti
   (`rank_limit` = 100). Hasilnya identik dengan menilai SELURUH katalog (diuji terhadap *oracle* pada 500/2.000/10.000 baris).
   Bila `scan_limit` (50.000 baris) atau `examine_limit` (3.000 baris) tercapai sebelum terbukti, `counts.exact = false` dan UI berkata
   "mungkin belum lengkap": tidak ada pemotongan diam-diam. V1.1 memakai `ORDER BY hits DESC, id ASC LIMIT 400`: `hits` hanya skor kasar,
   sehingga artikel paling relevan ber-ID besar terbuang (pada uji sintetis: 0 dari 5 artikel teratas masuk shortlist lama).
3. **Skor relevansi** (0–100, 1 desimal, `SourceDiscovery::WEIGHTS`, versi algoritma 2): cakupan istilah di judul 25, frasa di judul 25,
   fokus judul 5 (porsi kata bermakna judul yang merupakan istilah pencarian), cakupan abstrak 12, frasa di abstrak 8, pengulangan frasa di abstrak 4
   (jenuh pada 4 kali), kata kunci 6, teks terindeks 10 (Pustaka), kategori (terverifikasi 4 / disarankan 2), kelengkapan metadata ≤4, petunjuk
   tahun/bahasa 3/2, penalti jenis "other" −8 dan tahun masa depan −10. Kelayakan: istilah inti yang cocok ≥ n (n≤2) atau max(2, ⌈n/2⌉).
   **Ketersediaan bukti (abstrak ada / teks terindeks) TIDAK menambah skor**: abstrak tersedia bukan berarti lebih relevan atau bermutu.
   Tidak ada kemiripan semantik, tidak ada bonus penyedia, tidak ada bonus tahun terbaru.
4. **Dedup**: kunci kanonik = DOI huruf kecil. Judul sama dengan DOI berbeda **tidak** digabung. Bila Pustaka dan eksternal berbagi DOI,
   entri Pustaka dipertahankan (punya bukti teks) dan penyedia eksternal dicatat.
5. **Urutan** (`SourceDiscovery::compare`, eksplisit dan deterministik): (1) skor relevansi turun; (2) ketersediaan bukti turun
   (teks terindeks > abstrak > metadata); (3) abjad judul ternormalisasi (huruf kecil, tanpa diakritik); (4) DOI; (5) id numerik hanya untuk entri
   identik. Penyedia dan kunci teks `ext:ID` TIDAK dipakai. Referensi dengan skor dan bukti yang sama ditandai `tied_with` dan UI menyatakan bahwa
   urutan di antara mereka bukan peringkat kualitas ilmiah.
6. **Pemilihan**: semua kandidat layak diberi peringkat dulu; top 5 = irisan terdepan (tanpa kuota per penyedia). "Lihat semua" = halaman 10 per
   halaman (server-side).

## Label bukti (hanya yang didukung database)

| Label | Arti |
|---|---|
| `METADATA ONLY` | tidak ada abstrak tersimpan (≥40 karakter) |
| `ABSTRACT AVAILABLE` | abstrak tersimpan; dihitung dari isi, bukan kolom `availability` (910 rekaman DOAJ bernilai `OPEN_ACCESS_LINK` padahal punya abstrak) |
| `FULL TEXT INDEXED` | dokumen Pustaka dengan chunk terindeks |
| `FULL TEXT AVAILABLE` | **tidak dipakai**: sistem tidak menyimpan PDF/teks eksternal |

Tahun > tahun berjalan ditandai "perlu verifikasi" dan diberi penalti. Judul berawalan *Retracted/Withdrawn/Retraction/Expression of concern*
disembunyikan (dihitung di ikhtisar).

## AI sesuai permintaan

Pencarian tidak pernah memanggil AI. `POST /research/references/analysis` hanya pra-pemeriksaan: validasi server → muat ulang sumber →
kualitas bukti → ketersediaan provider (`AnalysisContract::aiStatus()`: hanya `openai` + kunci terisi; `mock` = tidak tersedia) →
konfirmasi eksplisit. Aksi mengikuti Katalog Alat AI aktif (Literature Synthesis, Research Gap, Theoretical Mapping, Conceptual Framework).
Satu dokumen Pustaka yang dikonfirmasi diserahkan ke Workspace (`?source=ID&prompt=ID`); **sumber eksternal belum tersambung ke mesin sintesis**
(kontrak input sudah ada dan teruji, eksekusi belum).

Kontrak (`AnalysisContract::build`): `source_id`, `citation_id` (S1..), bibliografi, DOI/URL, provenance, `evidence {type, content}`,
`claims_allowed`. Metadata tidak membawa isi; sitasi yang tidak ada di kontrak ditolak (`unknownCitations`). `CitationValidator` Research tidak diubah.

## Performa (terukur, V1.2)

MariaDB 10.4.32 lokal, `innodb_buffer_pool_size` 16 MB, database scratch terpisah (`bacadulu_scratch_v12`), katalog sintetis dengan 90% "decoy"
(memuat kedua istilah terpisah, kasus terburuk bagi batas atas), kueri "financial reporting", kedalaman N = 100, cache dingin:

| Baris | Kandidat SQL | Dinilai | Total | Query kandidat | Pasti | = oracle |
|---|---|---|---|---|---|---|
| 500 | 429 | 429 | 0,6 s | ±0,2 s | ya | ya |
| 2.000 | 1.715 | 175 | 0,2 s | 0,1 s | ya | ya |
| 10.000 | 8.556 | 875 | 0,9 s | 0,5 s | ya | ya |
| 50.000 | 42.756 | 375 | 2,6 s | 2,3 s | ya | (Top 5 = gold) |
| 100.000 | 50.000 (batas pindai) | 625 | 5,7 s | 5,4 s | ya | (Top 5 = gold) |

Hasil di-cache 120 detik (permintaan kedua ±10-30 ms). Memori PHP naik 4-22 MB per pencarian; mysqld stabil ±65 MB. Pada data nyata (970 baris)
tiap pencarian 130-220 ms dan identik dengan oracle untuk 7 kueri uji.

`EXPLAIN`: query kandidat memakai indeks `category_id` pada `external_publication_categories` (bukan pemindaian seluruh tabel publikasi), lalu
`eq_ref` ke `external_publications`; subquery penyedia dimaterialisasi (memindai `external_publication_sources`) dan hasil diurut (filesort) atas
kandidat. Biaya utama = LIKE `%istilah%` pada judul/abstrak untuk tiap baris kategori: **O(baris kategori)**.

Batas jujur: kueri dengan istilah jamak yang akarnya awalan kata lain (mis. "reports" -> "report" cocok "reporting") menggandakan kandidat SQL
dibanding yang layak di PHP dan dapat menghabiskan `examine_limit` sebelum terbukti (ditandai `exact=false`). Kedalaman 200 tidak lagi terbukti
pada katalog padat 50.000 baris; kedalaman 100 terbukti dengan 375 baris dinilai. Di atas ±20-30 ribu baris per kategori pencarian dengan LIKE
melewati ±1-2 detik; rencanakan FULLTEXT atau mesin pencari khusus (di bawah).

## Rancangan migration FULLTEXT (BELUM dijalankan, BELUM ada di `database/migrations`)

```php
// Menambah indeks FULLTEXT; hanya MySQL/MariaDB (InnoDB). SQLite uji tetap memakai jalur LIKE.
public function up(): void
{
    if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        DB::statement('ALTER TABLE external_publications ADD FULLTEXT ext_pub_title_abstract_ft (title, abstract)');
    }
}
public function down(): void
{
    if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        DB::statement('ALTER TABLE external_publications DROP INDEX ext_pub_title_abstract_ft');
    }
}
```

**Pemakaian (hybrid retrieval):** pada MariaDB, FULLTEXT hanya menggantikan klausa pra-saring `any` di `ExternalCandidateFinder` (OR dari LIKE per istilah)
dengan `MATCH(title, abstract) AGAINST('istilah1 istilah2' IN BOOLEAN MODE)`; bendera, batas atas, loop pemeriksaan, dan skor PHP tidak berubah.
Prasyarat jaminan kelayakan: (1) kata >= `innodb_ft_min_token_size` (bawaan 3; "AI" tidak terindeks) dan bukan stopword InnoDB harus tetap lewat
LIKE; (2) tokenisasi FULLTEXT berbeda dari token skor, jadi ia hanya boleh dipakai bila diuji dengan sifat "recall penuh" yang sama seperti
`test_sql_upper_bound_never_underestimates...`; (3) kata kunci (`keywords_json`) tetap lewat LIKE karena tidak ada di indeks.

**Dampak:** FULLTEXT mempercepat kueri selektif (istilah langka) dari O(baris) ke O(kecocokan); kueri padat (istilah umum pada kategori besar) tetap harus
menilai banyak baris. Ukuran indeks ±40-70% dari teks judul+abstrak (±1,5 KB/rekaman -> ±0,6-1 KB); pembangunan awal mengunci tulis sinkronisasi
(jalankan di jam sepi); tiap sinkronisasi menulis indeks tambahan. Dengan `innodb_buffer_pool_size` 16 MB indeks besar akan banyak membaca disk:
sarankan >= 128 MB sebelum >50.000 baris (my.ini TIDAK diubah proyek ini). Alternatif bila kategori melampaui ratusan ribu baris atau butuh peringkat
BM25/semantik: mesin pencari khusus (Meilisearch/OpenSearch) dengan skor aturan tetap dihitung di PHP sebagai penentu akhir.

## Batasan yang diketahui

- Kesahihan batas atas bergantung pada: LIKE judul/abstrak tak peka huruf besar/aksen (kolasi `utf8mb4_unicode_ci`; pada SQLite uji aksen peka);
  pemisah antar kata frasa <= `PHRASE_GAP` (5) karakter non-alfanumerik; kata kunci ber-escape unicode diperlakukan permisif. Semuanya diuji sebagai sifat
  (batas atas >= skor sebenarnya dan recall kandidat penuh), bukan hanya contoh.
- Retriever chunk Research Workspace (`KeywordRetriever`, `retrieval.candidate_limit`, 400 chunk tanpa urutan) di luar cakupan V1.2 dan belum diubah; ia hanya
  memengaruhi bukti isi/cuplikan dokumen Pustaka, bukan peringkat sumber eksternal.
- Skor relevansi tidak menilai mutu jurnal, jumlah sitasi, atau metodologi; abstrak yang pendek/tanpa abstrak otomatis kehilangan sinyal abstrak.

- Tidak ada ekspansi lintas bahasa: pertanyaan Indonesia hanya menemukan metadata berbahasa Indonesia; mayoritas metadata DOAJ berbahasa Inggris
  (Glosarium tidak memuat terjemahan).
- Cuplikan Pustaka Internal (≤240 karakter dari chunk terindeks) ditampilkan; kebijakan lama menyembunyikan chunk dari rute pengguna
  legacy (`library.*`). Keputusan produk: tinjau bila ingin cuplikan itu disembunyikan.
- Tautan "Buka Sumber" memakai DOI (doi.org) atau halaman penyedia; sebagian situs jurnal memblokir otomasi (HTTP 403) walau halamannya ada.
