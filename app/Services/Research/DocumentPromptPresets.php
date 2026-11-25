<?php

namespace App\Services\Research;

/*
 * Katalog preset template yang bisa DIIMPOR admin. Ini bukan seeder dan bukan data produksi: tidak ada yang tertulis ke
 * database sampai admin membuka pratinjau preset, membacanya, lalu menekan "Simpan sebagai Draf". Hasilnya selalu draf
 * dan tidak ada yang diaktifkan otomatis.
 *
 * Preset "book-chapter-fajar":
 *   - Instruksi inti (task_prompt) disusun dari berkas "Fajar.docx" (instruksi penulisan Book Chapter; judul dalam berkas:
 *     "Landasan Konseptual Inovasi Keuangan Syariah") dan rincian bagian dari spesifikasi admin.
 *   - Aturan integritas sumber (retrieval_instructions) dan format keluaran (output_instructions) berasal dari spesifikasi
 *     admin untuk modul ini, bukan dari isi berkas Fajar; pratinjau menandai asal tiap blok.
 *   - Topik, judul, dan rumusan masalah Keuangan Syariah TIDAK ada di prompt: semuanya placeholder. Judul dan rumusan dari
 *     berkas hanya disimpan sebagai metadata contoh (example_json).
 */
class DocumentPromptPresets
{
    /** Aturan sumber yang sama untuk bagian yang memakai referensi (spesifikasi admin). */
    private const SOURCE_RULES = "Gunakan hanya sumber yang ada pada {selected_sources}. Jangan membuat sitasi, DOI, nama penulis, judul artikel, nama jurnal, atau URL yang tidak ada pada sumber. Jika sumber tidak cukup untuk mendukung suatu klaim, tulis klaim itu secara hati-hati dan tandai dengan [SUMBER KURANG: uraikan kebutuhan] alih-alih mengarang. Tulis dengan redaksi sendiri; jangan menyalin kalimat sumber secara utuh. Jangan menyatakan bahwa naskah pasti lolos detektor AI atau bebas plagiarisme.";

    public static function all(): array
    {
        return ['book-chapter-fajar' => self::bookChapterFajar()];
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    private static function bookChapterFajar(): array
    {
        return [
            'key' => 'book-chapter-fajar',
            'name' => 'Book Chapter Akademik — Fajar',
            'document_type' => 'book_chapter',
            'description' => 'Template penulisan satu bab buku akademik: Pendahuluan, Rumusan Masalah, Tujuan Penelitian, Hasil dan Pembahasan, Kesimpulan dan Saran, Daftar Pustaka, serta Pertanyaan dan Jawaban. Berasal dari instruksi Book Chapter pada berkas Fajar; dapat dipakai untuk topik riset apa pun.',
            'origin' => 'Instruksi inti dari berkas Fajar.docx. Aturan integritas sumber dan format keluaran ditambahkan dari spesifikasi admin modul ini.',
            'language' => 'id',
            'citation_style' => 'APA 7',
            'qa_mode' => 'inline',
            'example' => [
                'title' => 'Landasan Konseptual Inovasi Keuangan Syariah',
                'research_question' => 'Bagaimana inovasi dalam perspektif Islam yang berkaitan dengan prinsip maqasid syariah dan kemaslahatan?',
                'note' => 'Contoh dari berkas Fajar. Hanya metadata: tidak dipakai sebagai isi prompt.',
            ],
            'sections' => [
                [
                    'key' => 'pendahuluan', 'title' => 'Pendahuluan', 'role' => 'body', 'is_required' => true,
                    'description' => 'Pendahuluan chapter yang sistematis, mengalir, dan bersumber.',
                    'purpose' => 'Membangun latar fenomena, celah, urgensi, penelitian terdahulu, rumusan masalah, tujuan, implikasi, dan keterbaruan chapter.',
                    'min_words' => 1000, 'max_words' => null, 'target_words' => null, 'item_count' => null, 'min_sources' => null,
                    'requirements' => "Minimal 1.000 kata.\nBahasa akademik dengan redaksi orisinal.\nSitasi format APA pada setiap klaim bersumber.\nFenomena terkini hanya dari sumber yang tersedia; tanpa sumber terkini, nyatakan keterbatasannya.",
                    'depends_on' => [],
                    'prompt' => [
                        'task_prompt' => "Anda adalah penulis book chapter berjudul \"{chapter_title}\" pada dokumen \"{document_title}\". Topik: {research_topic}. Kategori: {selected_category}.\n\nTulis bagian Pendahuluan minimal 1.000 kata (target {target_words} kata bila ditentukan) dalam bahasa {language}, secara sistematis, dengan redaksi yang mengalir, mudah dipahami, berbahasa akademik, dan menghindari plagiasi. Cantumkan sumber dengan format sitasi {citation_style} berdasarkan {selected_sources}.\n\nSusun Pendahuluan dengan urutan berikut:\n1. Fenomena, informasi, dan berita terkini yang relevan dengan topik (dua paragraf).\n2. Fenomena gap (satu paragraf).\n3. Urgensi penelitian (satu paragraf).\n4. Penelitian terdahulu yang berkaitan dengan judul chapter.\n5. Rumusan masalah, berdasarkan {research_questions}.\n6. Tujuan penelitian: ubah setiap pertanyaan pada rumusan masalah menjadi kalimat pernyataan (acuan: {research_objectives} bila tersedia).\n7. Implikasi praktis dan akademik dari chapter ini (satu paragraf).\n8. Keterbaruan pembahasan chapter (satu paragraf).",
                        'retrieval_instructions' => "Fenomena, berita, atau data terkini hanya boleh ditulis bila ada pada {selected_sources}. Jika tidak ada sumber yang cukup terkini, nyatakan keterbatasan itu dan jangan membuat berita, tanggal, angka, atau fenomena.\n".self::SOURCE_RULES,
                        'output_instructions' => "Keluarkan naskah dalam paragraf utuh sesuai urutan di atas, tanpa daftar butir kecuali diperlukan. Jangan menuliskan label \"Fenomena gap\" sebagai catatan di luar naskah. Jangan menyebut topik atau judul lain selain yang diberikan pada placeholder.",
                    ],
                ],
                [
                    'key' => 'rumusan-masalah', 'title' => 'Rumusan Masalah', 'role' => 'body', 'is_required' => true,
                    'description' => 'Rumusan masalah sesuai pertanyaan penelitian yang diisi pengguna.',
                    'purpose' => 'Menjadi dasar Tujuan Penelitian, pembahasan, dan kesimpulan.',
                    'min_words' => null, 'max_words' => null, 'target_words' => null, 'item_count' => null, 'min_sources' => null,
                    'requirements' => "Mengikuti pertanyaan penelitian pengguna; dukung satu atau beberapa pertanyaan.\nTidak boleh bertentangan dengan judul atau topik.",
                    'depends_on' => [],
                    'prompt' => [
                        'task_prompt' => "Susun rumusan masalah untuk chapter \"{chapter_title}\" (topik: {research_topic}) berdasarkan {research_questions}.\n\nPertahankan maksud dan urutan pertanyaan dari pengguna; Anda boleh merapikan redaksinya tanpa mengubah maknanya. Bila ada lebih dari satu pertanyaan, beri nomor dan jaga urutannya. Rumusan masalah ini menjadi dasar bagi Tujuan Penelitian, Hasil dan Pembahasan, dan Kesimpulan.\n\nBila {research_questions} kosong, jangan membuat rumusan yang bertentangan dengan judul atau topik. Sampaikan bahwa rumusan masalah belum diisi, lalu ajukan usulan pertanyaan yang selaras dengan {chapter_title} dan tandai jelas sebagai USULAN yang perlu disetujui pengguna.",
                        'retrieval_instructions' => null,
                        'output_instructions' => "Keluarkan hanya rumusan masalah (bernomor bila lebih dari satu), tanpa pembahasan.",
                    ],
                ],
                [
                    'key' => 'tujuan-penelitian', 'title' => 'Tujuan Penelitian', 'role' => 'body', 'is_required' => true,
                    'description' => 'Tujuan penelitian sebagai pernyataan dari rumusan masalah.',
                    'purpose' => 'Mengubah setiap pertanyaan rumusan masalah menjadi kalimat pernyataan yang selaras.',
                    'min_words' => null, 'max_words' => null, 'target_words' => null, 'item_count' => null, 'min_sources' => null,
                    'requirements' => "Satu tujuan untuk setiap rumusan masalah.\nTidak menambah tujuan tanpa dasar pada rumusan masalah.",
                    'depends_on' => ['rumusan-masalah'],
                    'prompt' => [
                        'task_prompt' => "Ubah setiap pertanyaan pada rumusan masalah ({research_questions}) menjadi kalimat pernyataan tujuan penelitian: satu tujuan untuk satu pertanyaan, dengan urutan yang sama. Bila pengguna sudah mengisi {research_objectives}, jadikan itu acuan dan pastikan tetap selaras dengan rumusan masalah.\n\nContoh bentuk transformasi (hanya ilustrasi bentuk; jangan disalin ke topik lain): pertanyaan \"Bagaimana pengaruh A terhadap B?\" menjadi \"Menganalisis pengaruh A terhadap B.\"

Bagian sebelumnya: {previous_section}. Jangan membuat tujuan baru yang tidak berdasar pada rumusan masalah. Pembahasan akan mengikuti tujuan yang Anda tetapkan di sini dan kesimpulan harus menjawabnya.",
                        'retrieval_instructions' => null,
                        'output_instructions' => "Keluarkan tujuan dalam kalimat pernyataan (bernomor bila lebih dari satu), diawali kata kerja operasional seperti menganalisis, menjelaskan, atau mengidentifikasi.",
                    ],
                ],
                [
                    'key' => 'hasil-dan-pembahasan', 'title' => 'Hasil dan Pembahasan', 'role' => 'body', 'is_required' => true,
                    'description' => 'Pembahasan yang sinkron dengan rumusan masalah dan tujuan penelitian.',
                    'purpose' => 'Menjawab setiap rumusan masalah dengan temuan atau sintesis literatur, penelitian terdahulu, argumentasi, fenomena, dan teori.',
                    'min_words' => 2000, 'max_words' => null, 'target_words' => null, 'item_count' => null, 'min_sources' => null,
                    'requirements' => "Minimal 2.000 kata.\nSatu pembahasan untuk setiap rumusan masalah.\nKajian konseptual memakai istilah hasil sintesis literatur.\nTanpa data wawancara, survei, statistik, atau temuan empiris yang tidak tersedia.",
                    'depends_on' => ['rumusan-masalah', 'tujuan-penelitian'],
                    'prompt' => [
                        'task_prompt' => "Tulis bagian Hasil dan Pembahasan untuk chapter \"{chapter_title}\" (topik: {research_topic}) minimal 2.000 kata (target {target_words} kata bila ditentukan), dalam bahasa {language}, dengan sitasi {citation_style} dari {selected_sources}.\n\nBuat pembahasan untuk setiap rumusan masalah pada {research_questions} agar sinkron dengan rumusan masalah dan {research_objectives}. Bila ada beberapa rumusan masalah, hasilkan pembahasan untuk masing-masing sesuai urutannya. Pada setiap pembahasan:\n1. Ubah fokus rumusan masalah menjadi kalimat pernyataan.\n2. Ungkapkan temuan atau hasil sintesis yang tersedia.\n3. Hubungkan dengan penelitian terdahulu yang mendukung tujuan penelitian.\n4. Sampaikan argumentasi akademik.\n5. Hubungkan dengan fenomena penelitian.\n6. Hubungkan dengan teori yang digunakan.\n7. Jaga keterkaitan antara rumusan masalah, tujuan, dan pembahasan.\n8. Gunakan referensi yang relevan.\n\nKonteks bagian sebelumnya: {previous_section}.",
                        'retrieval_instructions' => self::SOURCE_RULES,
                        'output_instructions' => "Bila dokumen merupakan kajian konseptual, sebut temuan sebagai \"hasil sintesis literatur\". Jangan mengarang hasil wawancara, data survei, hasil statistik, temuan empiris, kutipan narasumber, atau hasil penelitian yang tidak tersedia pada sumber.",
                    ],
                ],
                [
                    'key' => 'kesimpulan-dan-saran', 'title' => 'Kesimpulan dan Saran', 'role' => 'body', 'is_required' => true,
                    'description' => 'Kesimpulan yang selaras dengan rumusan masalah dan tujuan, beserta saran.',
                    'purpose' => 'Merangkum pembahasan, implikasi, keterbatasan, dan arah penelitian lanjutan.',
                    'min_words' => null, 'max_words' => 500, 'target_words' => null, 'item_count' => null, 'min_sources' => null,
                    'requirements' => "Maksimal 500 kata.\nTidak memuat kesimpulan baru yang tidak didukung pembahasan.",
                    'depends_on' => ['hasil-dan-pembahasan'],
                    'prompt' => [
                        'task_prompt' => "Tulis Kesimpulan dan Saran untuk chapter \"{chapter_title}\" dalam bahasa {language}, maksimal 500 kata, dengan rincian:\n1. Simpulkan masing-masing pembahasan, diselaraskan dengan rumusan masalah ({research_questions}) dan tujuan penelitian ({research_objectives}).\n2. Ungkapkan temuan yang paling penting secara nyata dan jelas.\n3. Ungkapkan implikasi akademik.\n4. Ungkapkan implikasi praktis.\n5. Ungkapkan keterbatasan penelitian.\n6. Ungkapkan saran bagi peneliti selanjutnya yang relevan dengan topik.\n\nDasarkan seluruhnya pada pembahasan yang sudah ada: {previous_section}. Sumber pendukung bila diperlukan: {selected_sources}.",
                        'retrieval_instructions' => "Jangan menghasilkan kesimpulan baru yang tidak didukung bagian pembahasan. Jangan menambah sitasi baru di luar {selected_sources}.",
                        'output_instructions' => "Pisahkan \"Kesimpulan\" dan \"Saran\" dengan jelas dan patuhi batas 500 kata.",
                    ],
                ],
                [
                    'key' => 'daftar-pustaka', 'title' => 'Daftar Pustaka', 'role' => 'references', 'is_required' => true,
                    'description' => 'Daftar pustaka format APA edisi ke-7 dari sumber yang benar-benar dirujuk.',
                    'purpose' => 'Mencocokkan sitasi dalam naskah dengan daftar pustaka dan menandai metadata yang belum lengkap.',
                    'min_words' => null, 'max_words' => null, 'target_words' => null, 'item_count' => null, 'min_sources' => null,
                    'requirements' => "Format APA edisi ke-7.\nHanya sumber yang dirujuk dalam naskah.\nTandai referensi dengan metadata tidak lengkap.",
                    'depends_on' => ['pendahuluan', 'hasil-dan-pembahasan', 'kesimpulan-dan-saran'],
                    'prompt' => [
                        'task_prompt' => "Susun Daftar Pustaka dalam format {citation_style} (APA edisi ke-7 untuk bawaan template ini) dari sumber yang benar-benar dirujuk dalam naskah: {previous_section}. Sumber yang tersedia: {selected_sources}.\n\nCocokkan setiap sitasi dalam naskah dengan satu entri daftar pustaka dan sebaliknya. Urutkan menurut abjad nama penulis. Bila ada sitasi dalam naskah yang tidak ditemukan pada {selected_sources}, daftarkan sebagai ketidaksesuaian, jangan dibuatkan entri.",
                        'retrieval_instructions' => "Gunakan metadata yang tersedia pada {selected_sources}. Jangan membuat DOI, nama jurnal, judul artikel, penulis, atau URL yang tidak ada. Entri dengan metadata tidak lengkap tetap ditampilkan apa adanya dan ditandai [METADATA TIDAK LENGKAP: sebutkan bagian yang kurang] agar diperiksa admin atau penulis.",
                        'output_instructions' => "Keluarkan daftar entri, lalu daftar singkat \"Perlu diperiksa\" yang memuat entri bermetadata tidak lengkap dan sitasi tanpa pasangan. Aturan APA 7 ini hanya berlaku untuk format sitasi, bukan ukuran halaman cetak.",
                    ],
                ],
                [
                    'key' => 'pertanyaan-dan-jawaban', 'title' => 'Pertanyaan dan Jawaban', 'role' => 'qa', 'is_required' => false,
                    'description' => 'Lima pertanyaan beserta jawaban yang komprehensif dan relevan dengan chapter.',
                    'purpose' => 'Menguji pemahaman konsep chapter. Tujuan penempatan (naskah, lampiran, materi evaluasi) diatur pada pengaturan template.',
                    'min_words' => null, 'max_words' => null, 'target_words' => null, 'item_count' => 5, 'min_sources' => null,
                    'requirements' => "Lima pertanyaan beserta jawabannya.\nBerkaitan dengan isi chapter dan didukung pembahasan.\nTidak memperkenalkan fakta baru.",
                    'depends_on' => ['hasil-dan-pembahasan'],
                    'prompt' => [
                        'task_prompt' => "Buat {item_count} pertanyaan beserta jawabannya yang komprehensif dan relevan dengan chapter \"{chapter_title}\" (topik: {research_topic}), dalam bahasa {language}.\n\nPertanyaan harus berkaitan dengan isi chapter, menguji pemahaman konsep, dan jawabannya jelas serta didukung pembahasan berikut: {previous_section}.",
                        'retrieval_instructions' => "Jawaban hanya boleh memakai isi pembahasan dan {selected_sources}. Jangan memperkenalkan fakta, data, atau kutipan baru yang tidak ada di sana.",
                        'output_instructions' => "Keluarkan pasangan \"Pertanyaan n\" dan \"Jawaban n\" secara berurutan sejumlah {item_count}.",
                    ],
                ],
            ],
        ];
    }
}
