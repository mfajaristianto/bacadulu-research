<?php

namespace App\Services\Research;

use App\Models\GlossaryTerm;
use Illuminate\Support\Str;

class GlossaryContextService
{
    /**
     * @param array<int,array<string,mixed>> $evidence
     * @return array<int,array<string,mixed>>
     */
    public function resolve(
        string $question,
        array $evidence = [],
        ?int $categoryId = null,
        int $limit = 12
    ): array {
        $haystack =
            $this->normalize(
                $question
                .' '
                .collect(
                    $evidence
                )
                    ->map(
                        fn (
                            array $item
                        ): string =>
                            implode(
                                ' ',
                                [
                                    (string) (
                                        $item[
                                            'title'
                                        ]
                                        ?? ''
                                    ),

                                    (string) (
                                        $item[
                                            'text'
                                        ]
                                        ?? ''
                                    ),
                                ]
                            )
                    )
                    ->implode(
                        ' '
                    )
            );

        if (
            $haystack === ''
        ) {
            return [];
        }

        $query =
            GlossaryTerm::query()
                ->active()
                ->with(
                    'category:id,name'
                )
                ->orderBy(
                    'term'
                );

        /*
         * Global glossary:
         * category_id = NULL
         *
         * Category glossary:
         * category_id = active category
         */

        if (
            $categoryId !== null
        ) {
            $query->where(
                function (
                    $builder
                ) use (
                    $categoryId
                ): void {
                    $builder
                        ->whereNull(
                            'category_id'
                        )
                        ->orWhere(
                            'category_id',
                            $categoryId
                        );
                }
            );
        }

        return $query
            ->get()
            ->filter(
                fn (
                    GlossaryTerm $term
                ): bool =>
                    $this->matches(
                        $haystack,
                        $term
                    )
            )
            ->take(
                max(
                    1,
                    $limit
                )
            )
            ->map(
                fn (
                    GlossaryTerm $term
                ): array => [
                    'id' =>
                        $term->id,

                    'term' =>
                        $term->term,

                    'definition' =>
                        $term->definition,

                    'aliases' =>
                        collect(
                            $term
                                ->aliases_json
                            ??
                            []
                        )
                            ->map(
                                fn (
                                    $alias
                                ) =>
                                    trim(
                                        (string)
                                            $alias
                                    )
                            )
                            ->filter()
                            ->values()
                            ->all(),

                    'context_note' =>
                        $term
                            ->context_note,

                    'category_id' =>
                        $term
                            ->category_id,

                    'category' =>
                        $term
                            ->category
                            ?->name,
                ]
            )
            ->values()
            ->all();
    }

    private function matches(
        string $haystack,
        GlossaryTerm $term
    ): bool {
        $needles =
            collect([
                $term->term,

                ...(
                    $term
                        ->aliases_json
                    ??
                    []
                ),
            ])
                ->map(
                    fn (
                        $value
                    ) =>
                        $this->normalize(
                            (string)
                                $value
                        )
                )
                ->filter()
                ->unique()
                ->values();

        foreach (
            $needles as
            $needle
        ) {
            if (
                $this
                    ->containsNeedle(
                        $haystack,
                        $needle
                    )
            ) {
                return true;
            }
        }

        return false;
    }

    private function containsNeedle(
        string $haystack,
        string $needle
    ): bool {
        if (
            $needle === ''
        ) {
            return false;
        }

        /*
         * Singkatan seperti:
         *
         * ESG
         * AI
         * ROA
         *
         * harus menggunakan word boundary supaya
         * "ai" tidak cocok ke kata "nilai".
         */

        if (
            mb_strlen(
                $needle
            )
            <=
            3
        ) {
            return preg_match(
                '/(?<![\p{L}\p{N}])'
                .preg_quote(
                    $needle,
                    '/'
                )
                .'(?![\p{L}\p{N}])/iu',
                $haystack
            )
            ===
            1;
        }

        return str_contains(
            $haystack,
            $needle
        );
    }

    private function normalize(
        string $value
    ): string {
        $value =
            Str::lower(
                trim(
                    $value
                )
            );

        return preg_replace(
            '/\s+/u',
            ' ',
            $value
        )
        ??
        $value;
    }
}