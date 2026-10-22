<?php

namespace App\Services\Research;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class OpenAiResponsesProvider implements AiProvider
{
    public function generate(
        array $payload
    ): array {
        $apiKey = trim(
            (string) config(
                'services.openai.api_key'
            )
        );

        $model = trim(
            (string) config(
                'research.ai_model'
            )
        );

        if ($apiKey === '') {
            throw new RuntimeException(
                'OPENAI_API_KEY belum dikonfigurasi.'
            );
        }

        if ($model === '') {
            throw new RuntimeException(
                'AI_MODEL belum dikonfigurasi.'
            );
        }

        $baseUrl = rtrim(
            (string) config(
                'services.openai.base_url',
                'https://api.openai.com/v1'
            ),
            '/'
        );

        $systemPrompt = trim(
            (string) (
                $payload[
                    'system_prompt'
                ] ?? ''
            )
        );

        $compiledPrompt = trim(
            (string) (
                $payload[
                    'compiled_prompt'
                ] ?? ''
            )
        );

        if ($compiledPrompt === '') {
            throw new RuntimeException(
                'Compiled research prompt kosong.'
            );
        }

        try {
            $response = Http::withToken(
                $apiKey
            )
                ->acceptJson()
                ->asJson()
                ->timeout(
                    max(
                        20,
                        (int) config(
                            'research.ai.timeout',
                            90
                        )
                    )
                )
                ->post(
                    $baseUrl.'/responses',
                    [
                        'model' => $model,

                        'input' => [
                            [
                                'role' => 'system',

                                'content' => $systemPrompt
                                        !== ''
                                    ? $systemPrompt
                                    : 'You are BacaDulu Research. Use only the evidence provided by the application.',
                            ],

                            [
                                'role' => 'user',

                                'content' => $compiledPrompt,
                            ],
                        ],

                        /*
                         * Structured Output.
                         */
                        'text' => [
                            'format' => [
                                'type' => 'json_schema',

                                'name' => 'bacadulu_research_answer',

                                'strict' => true,

                                'schema' => $this
                                    ->outputSchema(),
                            ],
                        ],

                        'max_output_tokens' => max(
                            500,
                            (int) config(
                                'research.ai.max_output_tokens',
                                3500
                            )
                        ),

                        /*
                         * Jangan simpan response
                         * pada provider.
                         */
                        'store' => false,
                    ]
                )
                ->throw();
        } catch (
            RequestException $exception
        ) {
            $message =
                $exception
                    ->response
                    ?->json(
                        'error.message'
                    )
                ?: $exception
                    ->getMessage();

            throw new RuntimeException(
                'AI provider request gagal: '
                .$message
            );
        }

        $json =
            $response->json();

        $outputText =
            $this->extractOutputText(
                $json
            );

        if ($outputText === '') {
            $refusal =
                $this->extractRefusal(
                    $json
                );

            throw new RuntimeException(
                $refusal !== ''
                    ? 'AI provider menolak request: '
                        .$refusal
                    : 'AI provider tidak mengembalikan text output.'
            );
        }

        $decoded =
            $this->decodeJsonObject(
                $outputText
            );

        if (
            ! is_string(
                $decoded['answer']
                ?? null
            )
            || trim(
                $decoded['answer']
            ) === ''
        ) {
            throw new RuntimeException(
                'AI output tidak memiliki field answer yang valid.'
            );
        }

        $citations =
            $decoded[
                'citations'
            ] ?? [];

        if (! is_array($citations)) {
            throw new RuntimeException(
                'AI output citations tidak valid.'
            );
        }

        return [
            'answer' => trim(
                $decoded[
                    'answer'
                ]
            ),

            'citations' => $citations,

            'next_action' => filled(
                $decoded[
                    'next_action'
                ] ?? null
            )
                    ? trim(
                        (string) $decoded[
                            'next_action'
                        ]
                    )
                    : null,

            'provider_meta' => [
                'provider' => 'openai',

                'model' => (string) (
                    $json['model']
                    ?? $model
                ),

                'response_id' => $json['id']
                        ?? null,

                'usage' => is_array(
                    $json['usage']
                    ?? null
                )
                        ? $json[
                            'usage'
                        ]
                        : null,
            ],
        ];
    }

    private function outputSchema(): array
    {
        return [
            'type' => 'object',

            'additionalProperties' => false,

            'properties' => [
                'answer' => [
                    'type' => 'string',
                ],

                'citations' => [
                    'type' => 'array',

                    'items' => [
                        'type' => 'object',

                        'additionalProperties' => false,

                        'properties' => [
                            'citation_key' => [
                                'type' => 'string',
                            ],

                            'claim_label' => [
                                'type' => 'string',
                            ],

                            'quote_snippet' => [
                                'type' => 'string',
                            ],
                        ],

                        'required' => [
                            'citation_key',
                            'claim_label',
                            'quote_snippet',
                        ],
                    ],
                ],

                'next_action' => [
                    'type' => 'string',
                ],
            ],

            'required' => [
                'answer',
                'citations',
                'next_action',
            ],
        ];
    }

    private function extractOutputText(
        array $json
    ): string {
        $outputText = trim(
            (string) (
                $json[
                    'output_text'
                ] ?? ''
            )
        );

        if ($outputText !== '') {
            return $outputText;
        }

        foreach (
            $json['output']
            ?? [] as $output
        ) {
            if (
                (
                    $output['type']
                    ?? null
                )
                !== 'message'
            ) {
                continue;
            }

            foreach (
                $output['content']
                ?? [] as $content
            ) {
                if (
                    (
                        $content[
                            'type'
                        ] ?? null
                    )
                    === 'output_text'
                ) {
                    $outputText .=
                        (string) (
                            $content[
                                'text'
                            ] ?? ''
                        );
                }
            }
        }

        return trim(
            $outputText
        );
    }

    private function extractRefusal(
        array $json
    ): string {
        foreach (
            $json['output']
            ?? [] as $output
        ) {
            if (
                (
                    $output['type']
                    ?? null
                )
                !== 'message'
            ) {
                continue;
            }

            foreach (
                $output['content']
                ?? [] as $content
            ) {
                if (
                    (
                        $content[
                            'type'
                        ] ?? null
                    )
                    === 'refusal'
                ) {
                    return trim(
                        (string) (
                            $content[
                                'refusal'
                            ] ?? ''
                        )
                    );
                }
            }
        }

        return '';
    }

    private function decodeJsonObject(
        string $text
    ): array {
        try {
            $decoded = json_decode(
                trim($text),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (
            JsonException $exception
        ) {
            throw new RuntimeException(
                'AI output bukan JSON valid: '
                .$exception
                    ->getMessage()
            );
        }

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'AI output harus berupa JSON object.'
            );
        }

        return $decoded;
    }
}
