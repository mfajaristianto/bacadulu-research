<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Compatibility no-op.
     *
     * An earlier project snapshot accidentally shipped two migrations that
     * added the same date_of_birth column. The canonical migration is
     * 2026_09_21_000001_add_date_of_birth_to_users_table.php.
     */
    public function up(): void
    {
        // Intentionally empty.
    }

    public function down(): void
    {
        // Intentionally empty so a partial rollback cannot drop the column.
    }
};
