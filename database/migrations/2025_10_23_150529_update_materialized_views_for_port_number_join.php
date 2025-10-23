<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update materialized views to join port_security on port_number instead of port_id
     */
    public function up(): void
    {
        if (config('database.default') === 'pgsql') {
            // Drop and recreate popular_ports materialized view
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS popular_ports CASCADE");

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
                LEFT JOIN port_security ps ON p.port_number = ps.port_number
                LEFT JOIN port_software ps_soft ON p.id = ps_soft.port_id
                LEFT JOIN port_issues pi ON p.id = pi.port_id AND pi.verified = true
                GROUP BY p.id, p.port_number, p.protocol, p.service_name, p.description,
                         p.risk_level, p.view_count, ps.shodan_exposed_count
                ORDER BY p.view_count DESC
                LIMIT 100
            ");

            // Create indexes on popular_ports view
            DB::statement("CREATE INDEX popular_ports_port_number_idx ON popular_ports (port_number)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            // Recreate with old structure (port_id join)
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS popular_ports CASCADE");

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

            DB::statement("CREATE INDEX popular_ports_port_number_idx ON popular_ports (port_number)");
        }
    }
};
