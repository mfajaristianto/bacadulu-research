<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = Schema::getColumnListing('users');

        Schema::table('users', function (Blueprint $table) use ($columns): void {
            if (! in_array('institution', $columns, true)) {
                $table
                    ->string('institution', 190)
                    ->nullable();
            }

            if (! in_array('date_of_birth', $columns, true)) {
                $table
                    ->date('date_of_birth')
                    ->nullable();
            }

            if (! in_array('phone_encrypted', $columns, true)) {
                $table
                    ->text('phone_encrypted')
                    ->nullable();
            }

            if (! in_array('address_encrypted', $columns, true)) {
                $table
                    ->text('address_encrypted')
                    ->nullable();
            }

            if (! in_array('bio', $columns, true)) {
                $table
                    ->text('bio')
                    ->nullable();
            }

            if (! in_array('professional_role', $columns, true)) {
                $table
                    ->string('professional_role', 160)
                    ->nullable();
            }

            if (! in_array('organization_type', $columns, true)) {
                $table
                    ->string('organization_type', 50)
                    ->nullable();
            }

            if (! in_array('division', $columns, true)) {
                $table
                    ->string('division', 160)
                    ->nullable();
            }

            if (! in_array('department', $columns, true)) {
                $table
                    ->string('department', 160)
                    ->nullable();
            }

            if (! in_array('organization_location', $columns, true)) {
                $table
                    ->string('organization_location', 190)
                    ->nullable();
            }

            if (! in_array('public_profile_url', $columns, true)) {
                $table
                    ->string('public_profile_url', 500)
                    ->nullable();
            }

            if (! in_array('research_interests', $columns, true)) {
                $table
                    ->json('research_interests')
                    ->nullable();
            }

            if (! in_array('two_factor_enabled', $columns, true)) {
                $table
                    ->boolean('two_factor_enabled')
                    ->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $possibleColumns = [
            'phone_encrypted',
            'address_encrypted',
            'bio',
            'professional_role',
            'organization_type',
            'division',
            'department',
            'organization_location',
            'public_profile_url',
            'research_interests',
            'two_factor_enabled',
        ];

        $existingColumns = array_values(
            array_filter(
                $possibleColumns,
                fn (string $column): bool =>
                    Schema::hasColumn('users', $column)
            )
        );

        if ($existingColumns === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }
};