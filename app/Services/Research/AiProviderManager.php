<?php

namespace App\Services\Research;

use RuntimeException;

class AiProviderManager
{
    public function driver(): AiProvider
    {
        return match (
            strtolower(
                (string) config(
                    'research.ai_provider',
                    'mock'
                )
            )
        ) {
            'mock' => app(
                MockResearchProvider::class
            ),

            'openai' => app(
                OpenAiResponsesProvider::class
            ),

            default => throw new RuntimeException(
                'AI_PROVIDER tidak dikenali. Gunakan mock atau openai.'
            ),
        };
    }
}
