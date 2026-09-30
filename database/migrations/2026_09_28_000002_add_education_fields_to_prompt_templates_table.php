<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prompt_templates')) {
            return;
        }

        if (! Schema::hasColumn('prompt_templates', 'indonesian_name')) {
            Schema::table('prompt_templates', function (Blueprint $table): void {
                $table->string('indonesian_name', 180)
                    ->nullable()
                    ->after('name');
            });
        }

        if (! Schema::hasColumn('prompt_templates', 'short_description')) {
            Schema::table('prompt_templates', function (Blueprint $table): void {
                $table->text('short_description')
                    ->nullable()
                    ->after('indonesian_name');
            });
        }

        if (! Schema::hasColumn('prompt_templates', 'meaning')) {
            Schema::table('prompt_templates', function (Blueprint $table): void {
                $table->text('meaning')
                    ->nullable()
                    ->after('short_description');
            });
        }

        if (! Schema::hasColumn('prompt_templates', 'when_to_use')) {
            Schema::table('prompt_templates', function (Blueprint $table): void {
                $table->text('when_to_use')
                    ->nullable()
                    ->after('meaning');
            });
        }

        if (! Schema::hasColumn('prompt_templates', 'when_not_to_use')) {
            Schema::table('prompt_templates', function (Blueprint $table): void {
                $table->text('when_not_to_use')
                    ->nullable()
                    ->after('when_to_use');
            });
        }

        if (! Schema::hasColumn('prompt_templates', 'example_text')) {
            Schema::table('prompt_templates', function (Blueprint $table): void {
                $table->text('example_text')
                    ->nullable()
                    ->after('when_not_to_use');
            });
        }

        if (! Schema::hasColumn('prompt_templates', 'expected_output')) {
            Schema::table('prompt_templates', function (Blueprint $table): void {
                $table->text('expected_output')
                    ->nullable()
                    ->after('example_text');
            });
        }

        /*
         * Isi prompt bawaan existing.
         * Custom prompt milik Admin tidak disentuh.
         */

        DB::table('prompt_templates')
            ->where('name', 'Explore Research Gaps')
            ->update([
                'indonesian_name' => 'Eksplorasi Kesenjangan Penelitian',

                'short_description' => 'Menganalisis keterbatasan, konflik hasil, dan peluang penelitian berdasarkan evidence yang tersedia.',

                'meaning' => 'Research gap adalah area penelitian yang masih memiliki keterbatasan, hasil yang belum konsisten, hubungan yang belum banyak diuji, atau pertanyaan yang belum terjawab oleh sumber yang dianalisis.',

                'when_to_use' => "Gunakan ketika Anda ingin:\n- menemukan keterbatasan penelitian sebelumnya;\n- melihat hasil yang berbeda atau belum konsisten;\n- menemukan hubungan yang belum banyak diteliti;\n- merumuskan peluang penelitian berikutnya.",

                'when_not_to_use' => 'Jangan gunakan ketika tujuan utama Anda hanya merangkum satu artikel atau ketika source yang tersedia belum cukup untuk membandingkan temuan penelitian.',

                'example_text' => 'Identifikasi research gap dari penelitian mengenai ESG disclosure dan firm value berdasarkan source yang tersedia.',

                'expected_output' => "1. Existing findings\n2. Limitations\n3. Inconsistencies\n4. Potential research gaps\n5. Supporting evidence\n6. Sources",
            ]);

        DB::table('prompt_templates')
            ->where('name', 'Generate Research Summary')
            ->update([
                'indonesian_name' => 'Membuat Ringkasan Penelitian',

                'short_description' => 'Merangkum tujuan, metode, temuan, keterbatasan, dan implikasi penelitian dari source yang tersedia.',

                'meaning' => 'Research Summary membantu memadatkan isi penelitian menjadi struktur yang lebih mudah dipahami tanpa mengganti evidence asli dari source.',

                'when_to_use' => "Gunakan ketika Anda ingin:\n- memahami isi source dengan lebih cepat;\n- melihat tujuan dan metode penelitian;\n- mengenali temuan utama;\n- melihat keterbatasan dan implikasi penelitian.",

                'when_not_to_use' => 'Jangan gunakan jika tujuan Anda adalah menemukan gap lintas banyak penelitian atau menyusun hubungan konseptual antarvariabel.',

                'example_text' => 'Ringkas penelitian yang tersedia mengenai penerapan AI dalam proses akuntansi.',

                'expected_output' => "1. Research purpose\n2. Research method\n3. Main findings\n4. Limitations\n5. Implications\n6. Sources",
            ]);

        DB::table('prompt_templates')
            ->where('name', 'Analyse Research Trends')
            ->update([
                'indonesian_name' => 'Analisis Tren Penelitian',

                'short_description' => 'Mengidentifikasi tema dominan, konsep berulang, dan arah penelitian yang muncul dari beberapa source.',

                'meaning' => 'Research trend menunjukkan pola topik, konsep, metode, atau arah penelitian yang muncul secara berulang pada source yang dianalisis.',

                'when_to_use' => "Gunakan ketika Anda ingin:\n- melihat tema yang sering muncul;\n- memahami perkembangan suatu topik;\n- mengenali arah penelitian yang berkembang;\n- membandingkan pola antar source.",

                'when_not_to_use' => 'Jangan gunakan jika Anda hanya membutuhkan ringkasan satu source atau ingin langsung merumuskan hipotesis tanpa melakukan sintesis evidence.',

                'example_text' => 'Analisis tren penelitian sustainability reporting berdasarkan source yang tersedia.',

                'expected_output' => "1. Dominant themes\n2. Recurring concepts\n3. Emerging directions\n4. Supporting evidence\n5. Sources",
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('prompt_templates')) {
            return;
        }

        $columns = [
            'indonesian_name',
            'short_description',
            'meaning',
            'when_to_use',
            'when_not_to_use',
            'example_text',
            'expected_output',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('prompt_templates', $column)) {
                Schema::table('prompt_templates', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
