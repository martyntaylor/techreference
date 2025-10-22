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
            // Materialized view for category listings with port counts and stats
            DB::statement("
                CREATE MATERIALIZED VIEW category_port_stats AS
                SELECT
                    c.id,
                    c.name,
                    c.slug,
                    c.description,
                    c.icon,
                    c.color,
                    COUNT(DISTINCT pc.port_id) as port_count,
                    COUNT(DISTINCT CASE WHEN p.risk_level = 'High' THEN pc.port_id END) as high_risk_count,
                    COUNT(DISTINCT CASE WHEN p.risk_level = 'Medium' THEN pc.port_id END) as medium_risk_count,
                    COUNT(DISTINCT CASE WHEN p.risk_level = 'Low' THEN pc.port_id END) as low_risk_count,
                    COUNT(DISTINCT CASE WHEN p.iana_official = true THEN pc.port_id END) as official_port_count,
                    MAX(p.updated_at) as last_port_updated
                FROM categories c
                LEFT JOIN port_categories pc ON c.id = pc.category_id
                LEFT JOIN ports p ON pc.port_id = p.id
                WHERE c.is_active = true
                GROUP BY c.id, c.name, c.slug, c.description, c.icon, c.color
                ORDER BY c.display_order, c.name
            ");

            // Create unique index on materialized view
            DB::statement("CREATE UNIQUE INDEX category_port_stats_id_idx ON category_port_stats (id)");
            DB::statement("CREATE INDEX category_port_stats_slug_idx ON category_port_stats (slug)");

            // Materialized view for popular ports (top 100 by view count)
            DB::statement("
                CREATE MATERIALIZED VIEW popular_ports AS
                SELECT
                    p.id,
                    p.port_number,
                    p.protocol,
                    p.service_name,
                    p.description,
                    p.risk_level,
                    p.view_count,
                    ps.shodan_exposed_count,
                    COUNT(DISTINCT ps_soft.software_id) as software_count,
                    COUNT(DISTINCT pi.id) as issue_count
                FROM ports p
                LEFT JOIN port_security ps ON p.id = ps.port_id
                LEFT JOIN port_software ps_soft ON p.id = ps_soft.port_id
                LEFT JOIN port_issues pi ON p.id = pi.port_id AND pi.verified = true
                GROUP BY p.id, p.port_number, p.protocol, p.service_name, p.description,
                         p.risk_level, p.view_count, ps.shodan_exposed_count
                ORDER BY p.view_count DESC
                LIMIT 100
            ");

            // Create indexes on popular_ports view
            DB::statement("CREATE INDEX popular_ports_port_number_idx ON popular_ports (port_number)");

            // Materialized view for port statistics dashboard
            DB::statement("
                CREATE MATERIALIZED VIEW port_statistics AS
                SELECT
                    COUNT(*) as total_ports,
                    COUNT(CASE WHEN iana_official = true THEN 1 END) as official_ports,
                    COUNT(CASE WHEN risk_level = 'High' THEN 1 END) as high_risk_ports,
                    COUNT(CASE WHEN risk_level = 'Medium' THEN 1 END) as medium_risk_ports,
                    COUNT(CASE WHEN risk_level = 'Low' THEN 1 END) as low_risk_ports,
                    COUNT(CASE WHEN encrypted_default = true THEN 1 END) as encrypted_ports,
                    COUNT(DISTINCT protocol) as protocol_count,
                    SUM(view_count) as total_views,
                    MAX(updated_at) as last_updated
                FROM ports
            ");

            // Materialized view for software popularity
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

            // Create index on software_port_stats
            DB::statement("CREATE INDEX software_port_stats_name_idx ON software_port_stats (name)");
            DB::statement("CREATE INDEX software_port_stats_category_idx ON software_port_stats (category)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS category_port_stats CASCADE");
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS popular_ports CASCADE");
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS port_statistics CASCADE");
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS software_port_stats CASCADE");
        }
    }
};
