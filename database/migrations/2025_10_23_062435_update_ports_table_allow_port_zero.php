<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old constraint
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE ports DROP CONSTRAINT IF EXISTS ports_port_number_check');

            // Add new constraint that allows port 0 (port 0 is valid - reserved by IANA)
            DB::statement('ALTER TABLE ports ADD CONSTRAINT ports_port_number_check CHECK (port_number >= 0 AND port_number <= 65535)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the old constraint
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE ports DROP CONSTRAINT IF EXISTS ports_port_number_check');

            // Restore old constraint (port_number >= 1)
            DB::statement('ALTER TABLE ports ADD CONSTRAINT ports_port_number_check CHECK (port_number >= 1 AND port_number <= 65535)');
        }
    }
};
