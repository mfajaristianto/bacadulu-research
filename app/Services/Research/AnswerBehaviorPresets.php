<?php

namespace App\Services\Research;

/*
 * Preset aturan awal. Hanya titik mulai yang dapat dipasang admin sebagai DRAF lewat aksi eksplisit; tidak ada yang aktif otomatis
 * dan tidak ada yang dipasang tanpa tindakan admin. Teks ditulis netral topik (tanpa contoh jawaban atau pertanyaan tetap).
 */
class AnswerBehaviorPresets
{
    /** @return list<array{key: string, title: string, description: string, group: string, scope: string, tool_code: ?string, input: array, text: string}> */
    public static function all(): array
    {
        return [
            [
                'key' => 'jawaban-akademik', 'title' => 'Jawaban Riset Akademik', 'description' => 'Gaya jawaban akademik yang jelas untuk analisis riset.',
                'group' => 'style', 'scope' => 'research', 'tool_code' => null,
                'input' => ['depth' => 'standard', 'register' => 'formal', 'language' => 'id', 'explain_terms' => true],
                'text' => "Jawab pertanyaan utama pengguna terlebih dahulu, lalu berikan analisis yang relevan.\nGunakan bahasa akademik yang jelas dan hindari bertele-tele.\nBedakan temuan yang dilaporkan sumber dari interpretasi Anda sendiri.",
            ],
            [
                'key' => 'struktur-riset', 'title' => 'Struktur Jawaban Riset', 'description' => 'Urutan bagian jawaban dari ringkasan sampai kesimpulan.',
                'group' => 'structure', 'scope' => 'research', 'tool_code' => null,
                'input' => ['sections' => ['summary', 'analysis', 'evidence', 'limits', 'conclusion']],
                'text' => 'Susun jawaban dengan ringkasan di awal, analisis, bukti pendukung dari sumber, keterbatasan, lalu kesimpulan. Lewati bagian yang tidak dapat didukung bukti.',
            ],
            [
                'key' => 'keterbatasan-sumber', 'title' => 'Keterbatasan Sumber', 'description' => 'Cara AI dan sistem menyampaikan sumber yang kurang, tidak relevan, bertentangan, atau error teknis.',
                'group' => 'grounding', 'scope' => 'global', 'tool_code' => null,
                'input' => [
                    'partial' => true, 'conflict' => true, 'uncertainty' => true,
                    'unavailable' => [
                        'no_documents' => ['message' => 'Pustaka belum memuat dokumen yang dapat dipakai untuk pertanyaan ini, sehingga belum ada dasar untuk menjawabnya.', 'next_action' => 'Tambahkan sumber yang relevan ke Pustaka, pilih kategori lain, atau ubah pertanyaan.'],
                        'irrelevant' => ['message' => 'Ada dokumen di Pustaka, tetapi tidak ada bagian yang cukup relevan dengan pertanyaan ini.', 'next_action' => 'Gunakan kata kunci yang lebih spesifik, persempit topik, atau perbaiki rumusan pertanyaan.'],
                        'insufficient' => ['message' => 'Sumber yang tersedia belum cukup untuk menyimpulkan hal ini secara meyakinkan.', 'next_action' => 'Perluas kata kunci, pilih kategori lain yang relevan, atau tambahkan sumber ke Pustaka.'],
                        'error' => ['message' => 'Terjadi gangguan teknis saat memproses riset ini. Ini bukan berarti literatur tidak ada.', 'next_action' => 'Coba jalankan ulang riset beberapa saat lagi.'],
                    ],
                ],
                'text' => "Hanya nyatakan klaim yang didukung bukti dari sumber. Jika bukti hanya mendukung sebagian pertanyaan, jawab bagian yang didukung dan jelaskan bagian yang belum.\nJangan mengklaim bahwa pencarian internet sudah dilakukan; sumber hanya berasal dari Pustaka.",
            ],
            [
                'key' => 'pertanyaan-lanjutan', 'title' => 'Pertanyaan Lanjutan', 'description' => 'Saran pertanyaan riset berikutnya yang berasal dari topik dan hasil analisis.',
                'group' => 'followup', 'scope' => 'research', 'tool_code' => null,
                'input' => ['enabled' => true, 'min' => 2, 'max' => 4, 'types' => ['topic', 'method', 'theory', 'variable', 'literature'], 'detail' => 'short', 'hide_below_evidence' => 2],
                'text' => 'Tawarkan pertanyaan lanjutan hanya bila relevan dengan topik dan analisis yang baru diberikan. Jangan memaksa memberi saran bila tidak ada yang bermakna.',
            ],
            [
                'key' => 'research-gap-lanjutan', 'title' => 'Research Gap — Tindak Lanjut', 'description' => 'Tindak lanjut khusus Research Gap: teori, variabel, dan hipotesis.',
                'group' => 'followup', 'scope' => 'tool', 'tool_code' => 'research-gap',
                'input' => ['enabled' => true, 'min' => 2, 'max' => 4, 'types' => ['theory', 'variable', 'method'], 'detail' => 'detailed', 'hide_below_evidence' => 2],
                'text' => 'Setelah analisis kesenjangan, sarankan pertanyaan yang membantu memperdalam gap yang ditemukan, misalnya teori penjelas, variabel tambahan, dan cara menguji gap tersebut.',
            ],
            [
                'key' => 'penulisan-dokumen', 'title' => 'Perilaku Penulisan Dokumen', 'description' => 'Cara AI membantu pengguna dalam alur penulisan dokumen. Belum dipakai: generator dokumen AI belum terhubung.',
                'group' => 'style', 'scope' => 'document', 'tool_code' => null,
                'input' => ['depth' => 'standard', 'register' => 'formal', 'language' => 'id', 'explain_terms' => false],
                'text' => 'Bantu pengguna menentukan struktur, periksa masukan yang kurang, jelaskan kebutuhan referensi, dan arahkan ke bagian berikutnya tanpa menulis ulang isi bab yang diatur Prompt Dokumen.',
            ],
        ];
    }
}
