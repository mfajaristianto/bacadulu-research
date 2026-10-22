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

        if (! Schema::hasColumn('prompt_templates', 'question_template')) {
            Schema::table(
                'prompt_templates',
                function (Blueprint $table): void {
                    $table
                        ->longText('question_template')
                        ->nullable()
                        ->after('short_description');
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Default Question Templates
        |--------------------------------------------------------------------------
        |
        | Ini adalah prompt yang dilihat user di composer.
        |
        | BUKAN:
        | - system_prompt
        | - task_prompt
        | - retrieval_instructions
        | - output_instructions
        |
        | Field ini user-facing dan boleh diedit user sebelum Send.
        |
        */

        $starters = [
            'Literature Synthesis' =>
                'Sintesis penelitian mengenai {topik} berdasarkan evidence yang tersedia. '
                .'Bandingkan temuan utama, persamaan, perbedaan, dan pola yang muncul antarpenelitian.',

            'Research Gap' =>
                'Identifikasi research gap mengenai {topik} berdasarkan evidence yang tersedia. '
                .'Fokuskan pada keterbatasan, inkonsistensi hasil, hubungan yang belum banyak diuji, '
                .'dan peluang penelitian lanjutan.',

            'Theoretical Mapping' =>
                'Petakan teori yang digunakan dalam penelitian mengenai {topik}. '
                .'Jelaskan konsep utama setiap teori dan bagaimana teori tersebut digunakan '
                .'untuk menjelaskan variabel, konstruk, atau hubungan penelitian.',

            'Conceptual Framework' =>
                'Susun conceptual framework mengenai {topik} berdasarkan teori dan evidence yang tersedia. '
                .'Identifikasi konstruk atau variabel utama serta hubungan yang dapat diusulkan secara defensible.',

            'Comparative Analysis' =>
                'Bandingkan penelitian mengenai {topik} berdasarkan dimensi yang tersedia dalam evidence, '
                .'seperti objek, sampel, metode, variabel, teori, dan hasil penelitian.',

            'Variable Identification' =>
                'Identifikasi variabel atau konstruk yang digunakan dalam penelitian mengenai {topik}. '
                .'Jelaskan peran, definisi, operationalization jika tersedia, dan hubungan antarvariabel '
                .'berdasarkan evidence.',

            /*
             * Legacy prompts.
             *
             * Tetap diberi question starter jika record lama
             * masih ada karena provenance/history tidak kita delete.
             */

            'Explore Research Gaps' =>
                'Identifikasi research gap mengenai {topik} berdasarkan evidence yang tersedia. '
                .'Fokuskan pada keterbatasan, inkonsistensi hasil, dan peluang penelitian lanjutan.',

            'Generate Research Summary' =>
                'Ringkas penelitian mengenai {topik} berdasarkan source yang tersedia. '
                .'Jelaskan tujuan, metode, temuan utama, keterbatasan, dan implikasinya.',

            'Analyse Research Trends' =>
                'Analisis tren penelitian mengenai {topik} berdasarkan source yang tersedia. '
                .'Identifikasi tema dominan, konsep berulang, dan arah penelitian yang sedang berkembang.',
        ];

        foreach (
            $starters as $name => $questionTemplate
        ) {
            DB::table('prompt_templates')
                ->where(
                    'name',
                    $name
                )
                ->where(
                    function ($query): void {
                        $query
                            ->whereNull(
                                'question_template'
                            )
                            ->orWhere(
                                'question_template',
                                ''
                            );
                    }
                )
                ->update([
                    'question_template' =>
                        $questionTemplate,

                    'updated_at' =>
                        now(),
                ]);
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable(
                'prompt_templates'
            )
            &&
            Schema::hasColumn(
                'prompt_templates',
                'question_template'
            )
        ) {
            Schema::table(
                'prompt_templates',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'question_template'
                    );
                }
            );
        }
    }
};