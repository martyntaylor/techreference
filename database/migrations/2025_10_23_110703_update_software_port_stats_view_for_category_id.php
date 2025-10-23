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
        if (config('database.default') === 'pgsql') {
            // Drop and recreate software_port_stats view with category_id
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS software_port_stats CASCADE");

            DB::statement("
                CREATE MATERIALIZED VIEW software_port_stats AS
                SELECT
                    s.id,
                    s.name,
                    s.slug,
                    s.category_id,
                    c.name as category_name,
                    c.slug as category_slug,
                    COUNT(DISTINCT ps.port_id) as port_count,
                    STRING_AGG(p.port_number::text, ', ' ORDER BY p.port_number) as common_ports
                FROM software s
                LEFT JOIN categories c ON s.category_id = c.id
                LEFT JOIN port_software ps ON s.id = ps.software_id
                LEFT JOIN ports p ON ps.port_id = p.id
                WHERE s.is_active = true
                GROUP BY s.id, s.name, s.slug, s.category_id, c.name, c.slug
                HAVING COUNT(DISTINCT ps.port_id) > 0
                ORDER BY port_count DESC
            ");

            // Recreate indexes
            DB::statement("CREATE UNIQUE INDEX software_port_stats_id_idx ON software_port_stats (id)");
            DB::statement("CREATE INDEX software_port_stats_name_idx ON software_port_stats (name)");
            DB::statement("CREATE INDEX software_port_stats_category_id_idx ON software_port_stats (category_id)");
            DB::statement("CREATE INDEX software_port_stats_category_slug_idx ON software_port_stats (category_slug)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            // Restore old version with category string field
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS software_port_stats CASCADE");

            DB::statement("
                CREATE MATERIALIZED VIEW software_port_stats AS
                SELECT
                    s.id,
                    s.name,
                    s.slug,
                    s.category,
                    COUNT(DISTINCT ps.port_id) as port_count,
                    STRING_AGG(p.port_number::text, ', ' ORDER BY p.port_number) as common_ports
                FROM software s
                LEFT JOIN port_software ps ON s.id = ps.software_id
                LEFT JOIN ports p ON ps.port_id = p.id
                WHERE s.is_active = true
                GROUP BY s.id, s.name, s.slug, s.category
                HAVING COUNT(DISTINCT ps.port_id) > 0
                ORDER BY port_count DESC
            ");

            DB::statement("CREATE INDEX software_port_stats_name_idx ON software_port_stats (name)");
            DB::statement("CREATE INDEX software_port_stats_category_idx ON software_port_stats (category)");
        }
    }
};
