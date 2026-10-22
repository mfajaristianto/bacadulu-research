<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 60)
                ->nullable()
                ->after('institution');

            $table->string('domicile_city', 120)
                ->nullable()
                ->after('role');

            $table->string('research_interest', 120)
                ->nullable()
                ->after('domicile_city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'domicile_city',
                'research_interest',
            ]);
        });
    }
};
