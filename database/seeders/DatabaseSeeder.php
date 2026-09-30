<?php

namespace Database\Seeders;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = trim(
            (string) env(
                'ADMIN_EMAIL'
            )
        );

        $adminPassword = (string) env(
            'ADMIN_PASSWORD'
        );

        $adminUser = null;

        if ($adminEmail !== '') {
            if ($adminPassword === '') {
                throw new RuntimeException(
                    'ADMIN_PASSWORD must be configured when ADMIN_EMAIL is set.'
                );
            }

            $adminUser = User::query()
                ->where(
                    'email',
                    $adminEmail
                )
                ->first();

            if (! $adminUser) {
                $adminUser = User::create([
                    'name' => env(
                        'ADMIN_NAME',
                        'BacaDulu Administrator'
                    ),

                    'email' => $adminEmail,

                    'password' => Hash::make(
                        $adminPassword
                    ),

                    'status' => 'active',

                    'email_verified_at' => now(),
                ]);
            }
        }

        $categories = [
            'Sustainability',
            'Accounting & Finance',
            'Management',
            'Technology & AI',
        ];

        foreach ($categories as $name) {
            Category::updateOrCreate(
                [
                    'slug' => str($name)
                        ->slug()
                        ->toString(),
                ],
                [
                    'name' => $name,
                    'status' => 'active',
                ]
            );
        }

        /*
         * Archive only old default tools.
         * Historical AI Run relationships remain intact.
         */
        $legacyTools = AiTool::query()
            ->whereIn(
                'code',
                [
                    'literature-insight',
                    'research-summary',
                    'trend-explorer',
                ]
            )
            ->get();

        foreach ($legacyTools as $legacyTool) {
            $legacyTool->update([
                'status' => 'archived',
            ]);

            PromptTemplate::query()
                ->where(
                    'ai_tool_id',
                    $legacyTool->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->update([
                    'status' => 'archived',
                ]);
        }

        $definitions = $this->canonicalTools();

        foreach ($definitions as $definition) {
            $promptDefinition =
                $definition['prompt'];

            $tool = AiTool::updateOrCreate(
                [
                    'code' => $definition['code'],
                ],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                    'status' => 'active',
                ]
            );

            /*
             * One active prompt version per tool.
             */
            PromptTemplate::query()
                ->where(
                    'ai_tool_id',
                    $tool->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->update([
                    'status' => 'draft',
                ]);

            $prompt = PromptTemplate::updateOrCreate(
                [
                    'ai_tool_id' => $tool->id,
                    'name' => $promptDefinition['name'],
                    'version' => 1,
                ],
                [
                    'indonesian_name'
                        => $promptDefinition[
                            'indonesian_name'
                        ],

                    'short_description'
                        => $promptDefinition[
                            'short_description'
                        ],

                    'meaning'
                        => $promptDefinition[
                            'meaning'
                        ],

                    'when_to_use'
                        => $promptDefinition[
                            'when_to_use'
                        ],

                    'when_not_to_use'
                        => $promptDefinition[
                            'when_not_to_use'
                        ],

                    'example_text'
                        => $promptDefinition[
                            'example_text'
                        ],

                    'expected_output'
                        => $promptDefinition[
                            'expected_output'
                        ],

                    'system_prompt'
                        => 'You are BacaDulu Research. Work only with retrieved eligible evidence supplied by the platform. Never invent sources or citations.',

                    'task_prompt'
                        => $promptDefinition[
                            'task_prompt'
                        ],

                    'retrieval_instructions'
                        => $promptDefinition[
                            'retrieval_instructions'
                        ],

                    'output_instructions'
                        => $promptDefinition[
                            'output_instructions'
                        ],

                    'status' => 'active',

                    'created_by'
                        => $adminUser?->id,
                ]
            );

            $tool->update([
                'active_prompt_template_id'
                    => $prompt->id,
            ]);
        }
    }

    private function canonicalTools(): array
    {
        return [
            [
                'code' => 'literature-synthesis',
                'name' => 'Literature Synthesis',
                'description' => 'Menyatukan dan membandingkan temuan beberapa source untuk melihat pola, persamaan, perbedaan, dan perkembangan penelitian.',
                'icon' => 'LS',

                'prompt' => [
                    'name' => 'Literature Synthesis',
                    'indonesian_name' => 'Sintesis Literatur',

                    'short_description' => 'Menyatukan dan membandingkan temuan dari beberapa source untuk melihat pola, persamaan, perbedaan, dan perkembangan penelitian.',

                    'meaning' => 'Literature Synthesis membantu menghubungkan temuan dari beberapa penelitian menjadi pemahaman yang lebih utuh. Fokusnya bukan sekadar meringkas source satu per satu, tetapi melihat pola dan hubungan antarpenelitian.',

                    'when_to_use' => "Gunakan ketika Anda ingin:\n- menyatukan temuan beberapa penelitian;\n- melihat persamaan dan perbedaan hasil;\n- mengenali pola yang berulang;\n- memahami perkembangan suatu topik;\n- membangun dasar literature review.",

                    'when_not_to_use' => 'Jangan gunakan ketika Anda hanya ingin mengetahui isi satu artikel secara singkat atau ketika source yang tersedia terlalu sedikit untuk dibandingkan.',

                    'example_text' => 'Sintesis temuan penelitian mengenai hubungan ESG disclosure dan firm value berdasarkan source yang tersedia.',

                    'expected_output' => "1. Scope of literature\n2. Main findings\n3. Similarities\n4. Differences\n5. Recurring patterns\n6. Supporting evidence\n7. Sources",

                    'task_prompt' => 'Using only the retrieved eligible sources, synthesize the literature by identifying major findings, similarities, differences, recurring patterns, and relevant developments across the sources.',

                    'retrieval_instructions' => 'Retrieve evidence from multiple relevant eligible sources. Prioritise source diversity and evidence directly related to the user research question.',

                    'output_instructions' => 'Separate source-supported findings from synthesis. Every substantive comparison must remain traceable to supporting evidence.',
                ],
            ],

            [
                'code' => 'research-gap',
                'name' => 'Research Gap',
                'description' => 'Mencari keterbatasan, konflik hasil, dan area penelitian yang masih terbuka.',
                'icon' => 'RG',

                'prompt' => [
                    'name' => 'Research Gap',
                    'indonesian_name' => 'Kesenjangan Penelitian',

                    'short_description' => 'Menganalisis keterbatasan, hasil yang belum konsisten, dan peluang penelitian berdasarkan evidence yang tersedia.',

                    'meaning' => 'Research gap adalah area penelitian yang masih memiliki keterbatasan, hasil yang berbeda, hubungan yang belum banyak diuji, atau pertanyaan yang belum cukup dijawab oleh penelitian sebelumnya.',

                    'when_to_use' => "Gunakan ketika Anda ingin:\n- menemukan keterbatasan penelitian sebelumnya;\n- melihat hasil yang berbeda atau belum konsisten;\n- menemukan hubungan yang belum banyak diteliti;\n- menemukan peluang penelitian lanjutan;\n- membantu merumuskan research problem.",

                    'when_not_to_use' => 'Jangan gunakan ketika Anda hanya membutuhkan ringkasan satu artikel atau ketika evidence yang tersedia belum cukup untuk menunjukkan adanya gap.',

                    'example_text' => 'Identifikasi research gap dari penelitian mengenai ESG disclosure dan firm value berdasarkan source yang tersedia.',

                    'expected_output' => "1. Existing findings\n2. Existing limitations\n3. Inconsistencies\n4. Underexplored areas\n5. Potential research gaps\n6. Supporting evidence\n7. Sources",

                    'task_prompt' => 'Using only the retrieved eligible sources, identify documented limitations, inconsistent findings, underexplored relationships, methodological opportunities, and defensible potential research gaps.',

                    'retrieval_instructions' => 'Prioritise evidence containing limitations, recommendations, conflicting findings, scope restrictions, methodological differences, and underexplored relationships.',

                    'output_instructions' => 'Clearly distinguish documented gaps from reasonable research opportunities inferred from the evidence. Do not claim a gap without supporting evidence.',
                ],
            ],

            [
                'code' => 'theoretical-mapping',
                'name' => 'Theoretical Mapping',
                'description' => 'Memetakan teori yang digunakan dalam penelitian dan kaitannya dengan topik, konstruk, atau hubungan yang dianalisis.',
                'icon' => 'TM',

                'prompt' => [
                    'name' => 'Theoretical Mapping',
                    'indonesian_name' => 'Pemetaan Teori',

                    'short_description' => 'Memetakan teori yang digunakan dalam source dan menjelaskan kaitannya dengan topik atau hubungan penelitian.',

                    'meaning' => 'Theoretical Mapping membantu memahami teori apa yang digunakan oleh penelitian sebelumnya, konsep yang dijelaskan oleh teori tersebut, serta bagaimana teori tersebut diterapkan dalam konteks penelitian tertentu.',

                    'when_to_use' => "Gunakan ketika Anda ingin:\n- mengetahui teori yang sering digunakan;\n- membandingkan theoretical lens antarpenelitian;\n- melihat hubungan teori dengan variabel atau konstruk;\n- menentukan teori yang relevan untuk penelitian berikutnya.",

                    'when_not_to_use' => 'Jangan gunakan hanya untuk mencari definisi variabel atau ketika source tidak menjelaskan theoretical foundation secara memadai.',

                    'example_text' => 'Petakan teori yang digunakan untuk menjelaskan hubungan corporate governance dan firm performance.',

                    'expected_output' => "1. Identified theories\n2. Core theoretical concepts\n3. Research context\n4. Variables or constructs explained\n5. Similarities and differences\n6. Supporting evidence\n7. Sources",

                    'task_prompt' => 'Using only the retrieved eligible sources, identify the theoretical lenses used, explain their relevant concepts, and map how each theory is connected to the research topic, constructs, or relationships.',

                    'retrieval_instructions' => 'Prioritise theoretical framework, literature review, hypothesis development, conceptual framework, and theory discussion sections.',

                    'output_instructions' => 'Do not invent a theoretical relationship that is not supported by the retrieved evidence. Clearly indicate when a source mentions a theory without applying it explicitly.',
                ],
            ],

            [
                'code' => 'conceptual-framework',
                'name' => 'Conceptual Framework',
                'description' => 'Membantu menyusun hubungan konseptual antarvariabel atau konstruk berdasarkan teori dan evidence.',
                'icon' => 'CF',

                'prompt' => [
                    'name' => 'Conceptual Framework',
                    'indonesian_name' => 'Kerangka Konseptual',

                    'short_description' => 'Membantu menyusun hubungan konseptual antarvariabel atau konstruk dengan tetap mengacu pada teori dan evidence.',

                    'meaning' => 'Conceptual Framework adalah struktur yang menunjukkan konsep atau variabel penting dalam penelitian serta hubungan yang diusulkan di antara konsep tersebut.',

                    'when_to_use' => "Gunakan ketika Anda ingin:\n- menyusun hubungan antarvariabel;\n- merancang conceptual model;\n- menghubungkan evidence dengan theoretical reasoning;\n- mempersiapkan dasar pengembangan hipotesis.",

                    'when_not_to_use' => 'Jangan gunakan untuk mengklaim hubungan kausal sebagai fakta jika source hanya menunjukkan hubungan konseptual atau asosiasi. Framework yang dihasilkan bukan bukti empiris baru.',

                    'example_text' => 'Susun conceptual framework untuk hubungan ESG disclosure, firm reputation, dan firm value berdasarkan teori dan evidence yang tersedia.',

                    'expected_output' => "1. Main constructs or variables\n2. Theoretical basis\n3. Proposed relationships\n4. Supporting evidence\n5. Conceptual framework narrative\n6. Framework structure\n7. Sources",

                    'task_prompt' => 'Using only retrieved eligible evidence, identify relevant constructs or variables and develop a defensible conceptual framework grounded in the available theory and findings.',

                    'retrieval_instructions' => 'Retrieve evidence about constructs, variable relationships, theoretical arguments, prior findings, mediation, moderation, and conceptual models when available.',

                    'output_instructions' => 'Present proposed conceptual relationships as research propositions or framework logic rather than proven causal facts unless the evidence explicitly supports such claims.',
                ],
            ],

            [
                'code' => 'comparative-analysis',
                'name' => 'Comparative Analysis',
                'description' => 'Membandingkan penelitian berdasarkan objek, metode, variabel, hasil, atau perspektif.',
                'icon' => 'CA',

                'prompt' => [
                    'name' => 'Comparative Analysis',
                    'indonesian_name' => 'Analisis Perbandingan',

                    'short_description' => 'Membandingkan beberapa penelitian berdasarkan objek, metode, variabel, teori, atau hasil yang relevan.',

                    'meaning' => 'Comparative Analysis membantu melihat secara sistematis bagaimana beberapa penelitian berbeda atau serupa berdasarkan dimensi tertentu.',

                    'when_to_use' => "Gunakan ketika Anda ingin:\n- membandingkan metode penelitian;\n- membandingkan objek atau sampel;\n- melihat perbedaan variabel;\n- melihat persamaan atau konflik hasil;\n- membandingkan teori atau perspektif.",

                    'when_not_to_use' => 'Jangan gunakan ketika hanya ada satu source yang relevan atau ketika dimensi yang ingin dibandingkan tidak tersedia dalam evidence.',

                    'example_text' => 'Bandingkan penelitian mengenai Islamic banking profitability berdasarkan variabel, sampel, metode, dan hasil.',

                    'expected_output' => "1. Comparison dimensions\n2. Similarities\n3. Differences\n4. Methodological comparison\n5. Findings comparison\n6. Interpretation\n7. Evidence\n8. Sources",

                    'task_prompt' => 'Using only the retrieved eligible sources, compare the studies across dimensions relevant to the user request such as research object, sample, method, variables, theory, and findings.',

                    'retrieval_instructions' => 'Retrieve evidence that allows direct comparison across at least two relevant sources. Avoid comparing dimensions that are not reported by the sources.',

                    'output_instructions' => 'Use explicit comparison dimensions and distinguish factual source differences from interpretive synthesis.',
                ],
            ],

            [
                'code' => 'variable-identification',
                'name' => 'Variable Identification',
                'description' => 'Membantu mengidentifikasi variabel atau konsep yang digunakan dalam penelitian serta perannya.',
                'icon' => 'VI',

                'prompt' => [
                    'name' => 'Variable Identification',
                    'indonesian_name' => 'Identifikasi Variabel',

                    'short_description' => 'Mengidentifikasi variabel, konstruk, atau konsep yang digunakan dalam source dan menjelaskan perannya dalam penelitian.',

                    'meaning' => 'Variable Identification membantu menemukan variabel yang digunakan dalam penelitian, perannya sebagai independent, dependent, mediator, moderator, control, atau bentuk konstruk lain jika dinyatakan oleh source.',

                    'when_to_use' => "Gunakan ketika Anda ingin:\n- menemukan variabel yang digunakan penelitian sebelumnya;\n- mengetahui peran setiap variabel;\n- melihat definisi konseptual;\n- mencari indikator atau operationalization jika tersedia;\n- menemukan kandidat variabel untuk research model.",

                    'when_not_to_use' => 'Jangan gunakan untuk menetapkan suatu variabel sebagai mediator atau moderator jika source tidak mendukung peran tersebut.',

                    'example_text' => 'Identifikasi variabel yang digunakan dalam penelitian mengenai digital transformation dan firm performance.',

                    'expected_output' => "1. Variable or construct\n2. Role in the study\n3. Conceptual definition\n4. Operationalization if available\n5. Related variables\n6. Supporting evidence\n7. Sources",

                    'task_prompt' => 'Using only the retrieved eligible sources, identify variables and constructs relevant to the research question and describe their roles, definitions, operationalisation, and relationships when explicitly supported.',

                    'retrieval_instructions' => 'Prioritise variable definitions, conceptual framework, hypotheses, methods, measurement, operationalisation, and results sections.',

                    'output_instructions' => 'Do not assign variable roles that are not explicitly stated or defensibly supported by the source. Mark missing operationalisation clearly.',
                ],
            ],
        ];
    }
}