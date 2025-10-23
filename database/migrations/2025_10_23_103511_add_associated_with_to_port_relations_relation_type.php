<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run for PostgreSQL (SQLite doesn't support ALTER TYPE for enums)
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Drop the existing enum constraint
            DB::statement("ALTER TABLE port_relations DROP CONSTRAINT IF EXISTS port_relations_relation_type_check");

            // Recreate with new value
            DB::statement("ALTER TABLE port_relations ADD CONSTRAINT port_relations_relation_type_check CHECK (relation_type::text = ANY (ARRAY['alternative'::character varying, 'secure_version'::character varying, 'deprecated_by'::character varying, 'part_of_suite'::character varying, 'conflicts_with'::character varying, 'complementary'::character varying, 'associated_with'::character varying]::text[]))");
        }
        // SQLite stores enum as string, no constraint modification needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only run for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Drop the constraint
            DB::statement("ALTER TABLE port_relations DROP CONSTRAINT IF EXISTS port_relations_relation_type_check");

            // Recreate without associated_with
            DB::statement("ALTER TABLE port_relations ADD CONSTRAINT port_relations_relation_type_check CHECK (relation_type::text = ANY (ARRAY['alternative'::character varying, 'secure_version'::character varying, 'deprecated_by'::character varying, 'part_of_suite'::character varying, 'conflicts_with'::character varying, 'complementary'::character varying]::text[]))");
        }
    }
};
