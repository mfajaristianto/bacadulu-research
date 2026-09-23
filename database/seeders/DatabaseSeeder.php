<?php

namespace Database\Seeders;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = trim((string) env('ADMIN_EMAIL'));
        $adminPassword = (string) env('ADMIN_PASSWORD');

        $adminUser = null;

        if ($adminEmail !== '') {
            if ($adminPassword === '') {
                throw new RuntimeException('ADMIN_PASSWORD must be configured when ADMIN_EMAIL is set.');
            }

            $adminUser = User::query()->where('email', $adminEmail)->first();

            if (! $adminUser) {
                $adminUser = User::create([
                    'name' => env('ADMIN_NAME', 'BacaDulu Administrator'),
                    'email' => $adminEmail,
                    'password' => Hash::make($adminPassword),
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
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active']
            );
        }

        $tools = [
            [
                'code' => 'literature-insight',
                'name' => 'Literature Insight',
                'description' => 'Extract themes, arguments, and research gaps from selected sources.',
                'icon' => '◎',
            ],
            [
                'code' => 'research-summary',
                'name' => 'Research Summary',
                'description' => 'Create a structured summary grounded in selected internal documents.',
                'icon' => '▤',
            ],
            [
                'code' => 'trend-explorer',
                'name' => 'Trend Explorer',
                'description' => 'Explore recurring concepts and research directions across the library.',
                'icon' => '⌁',
            ],
            [
                'code' => 'research-gap',
                'name' => 'Research Gap',
                'description' => 'Identify possible gaps and questions supported by selected evidence.',
                'icon' => '+',
            ],
        ];

        $toolModels = [];

        foreach ($tools as $tool) {
            $model = AiTool::updateOrCreate(
                ['code' => $tool['code']],
                [...$tool, 'status' => 'active']
            );
            $toolModels[$tool['code']] = $model;
        }

        $prompts = [
            [
                'tool' => 'trend-explorer',
                'name' => 'Analyse Research Trends',
                'task_prompt' => 'Using only the selected internal sources, identify dominant themes, recurring concepts, emerging directions, and evidence for each observation.',
                'output_instructions' => 'Cite the supporting internal source for every substantive claim.',
            ],
            [
                'tool' => 'research-summary',
                'name' => 'Generate Research Summary',
                'task_prompt' => 'Using only the selected internal sources, produce a structured research summary covering purpose, methods, main findings, limitations, and implications.',
                'output_instructions' => 'Do not introduce information that is not supported by the selected sources.',
            ],
            [
                'tool' => 'research-gap',
                'name' => 'Explore Research Gaps',
                'task_prompt' => 'Using only the selected internal sources, identify explicit limitations, underexplored relationships, methodological opportunities, and possible research questions.',
                'output_instructions' => 'Distinguish documented gaps from reasonable research directions.',
            ],
        ];

        foreach ($prompts as $promptData) {
            $tool = $toolModels[$promptData['tool']];

            $prompt = PromptTemplate::updateOrCreate(
                [
                    'ai_tool_id' => $tool->id,
                    'name' => $promptData['name'],
                    'version' => 1,
                ],
                [
                    'system_prompt' => 'You are BacaDulu Research. Work only with the eligible internal sources supplied by the platform.',
                    'task_prompt' => $promptData['task_prompt'],
                    'retrieval_instructions' => 'Use only published and eligible internal sources selected for the current research request.',
                    'output_instructions' => $promptData['output_instructions'],
                    'status' => 'active',
                    'created_by' => $adminUser?->id,
                ]
            );

            $tool->update(['active_prompt_template_id' => $prompt->id]);
        }
    }
}
