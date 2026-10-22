<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable(
                'research_settings'
            )
        ) {
            return;
        }

        Schema::create(
            'research_settings',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table
                    ->string(
                        'key',
                        180
                    )
                    ->unique();

                $table
                    ->string(
                        'group',
                        80
                    )
                    ->index();

                $table
                    ->text(
                        'value'
                    )
                    ->nullable();

                $table
                    ->string(
                        'value_type',
                        30
                    )
                    ->default(
                        'string'
                    );

                $table
                    ->string(
                        'description',
                        500
                    )
                    ->nullable();

                $table
                    ->foreignId(
                        'updated_by'
                    )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'research_settings'
        );
    }
};