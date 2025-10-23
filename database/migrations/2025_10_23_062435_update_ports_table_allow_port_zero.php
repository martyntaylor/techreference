<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql' && Schema::hasTable('ports')) {
            DB::transaction(function () {
                DB::statement('ALTER TABLE ports DROP CONSTRAINT IF EXISTS ports_port_number_check');
                DB::statement('ALTER TABLE ports ADD CONSTRAINT ports_port_number_check CHECK (port_number >= 0 AND port_number <= 65535)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql' && Schema::hasTable('ports')) {
            DB::transaction(function () {
                DB::statement('ALTER TABLE ports DROP CONSTRAINT IF EXISTS ports_port_number_check');
                DB::statement('ALTER TABLE ports ADD CONSTRAINT ports_port_number_check CHECK (port_number >= 1 AND port_number <= 65535)');
            });
        }
    }
};
