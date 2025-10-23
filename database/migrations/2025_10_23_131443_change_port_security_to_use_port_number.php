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
        $driver = Schema::getConnection()->getDriverName();

        // Drop materialized views that depend on port_security.port_id
        if ($driver === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS popular_ports CASCADE');
        }

        if ($driver === 'sqlite') {
            // SQLite: Skip this migration entirely - recreate the table manually
            DB::statement('PRAGMA foreign_keys = OFF');

            // Rename old table
            DB::statement('ALTER TABLE port_security RENAME TO port_security_old');

            // Create new table with port_number instead of port_id (without indexes - they exist from old table)
            DB::statement('
                CREATE TABLE port_security (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    port_number INTEGER NOT NULL UNIQUE,
                    shodan_exposed_count INTEGER DEFAULT 0 NOT NULL,
                    shodan_updated_at DATETIME,
                    censys_exposed_count INTEGER DEFAULT 0 NOT NULL,
                    censys_updated_at DATETIME,
                    cve_count INTEGER DEFAULT 0 NOT NULL,
                    latest_cve VARCHAR(50),
                    cve_updated_at DATETIME,
                    top_countries TEXT,
                    security_recommendations TEXT,
                    top_products TEXT,
                    top_operating_systems TEXT,
                    top_organizations TEXT,
                    top_asns TEXT,
                    cve_critical_count INTEGER DEFAULT 0 NOT NULL,
                    cve_high_count INTEGER DEFAULT 0 NOT NULL,
                    cve_medium_count INTEGER DEFAULT 0 NOT NULL,
                    cve_low_count INTEGER DEFAULT 0 NOT NULL,
                    cve_avg_score NUMERIC(3, 1),
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ');

            // Copy data - group by port_number to eliminate duplicates
            DB::statement('
                INSERT INTO port_security
                SELECT
                    MIN(ps.id) as id,
                    p.port_number,
                    MAX(ps.shodan_exposed_count) as shodan_exposed_count,
                    MAX(ps.shodan_updated_at) as shodan_updated_at,
                    MAX(ps.censys_exposed_count) as censys_exposed_count,
                    MAX(ps.censys_updated_at) as censys_updated_at,
                    MAX(ps.cve_count) as cve_count,
                    MAX(ps.latest_cve) as latest_cve,
                    MAX(ps.cve_updated_at) as cve_updated_at,
                    ps.top_countries,
                    ps.security_recommendations,
                    ps.top_products,
                    ps.top_operating_systems,
                    ps.top_organizations,
                    ps.top_asns,
                    MAX(ps.cve_critical_count) as cve_critical_count,
                    MAX(ps.cve_high_count) as cve_high_count,
                    MAX(ps.cve_medium_count) as cve_medium_count,
                    MAX(ps.cve_low_count) as cve_low_count,
                    MAX(ps.cve_avg_score) as cve_avg_score,
                    MIN(ps.created_at) as created_at,
                    MAX(ps.updated_at) as updated_at
                FROM port_security_old ps
                INNER JOIN ports p ON ps.port_id = p.id
                GROUP BY p.port_number
            ');

            // Drop old table
            DB::statement('DROP TABLE port_security_old');

            // Create indexes
            DB::statement('CREATE INDEX port_security_port_number_index ON port_security (port_number)');
            DB::statement('CREATE INDEX port_security_shodan_updated_at_index ON port_security (shodan_updated_at)');
            DB::statement('CREATE INDEX port_security_censys_updated_at_index ON port_security (censys_updated_at)');
            DB::statement('CREATE INDEX port_security_cve_updated_at_index ON port_security (cve_updated_at)');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            // PostgreSQL/MySQL: Standard migration
            Schema::table('port_security', function (Blueprint $table) {
                $table->unsignedInteger('port_number')->nullable()->after('id');
            });

            // Add index early for UPDATE performance (will be recreated with unique constraint later)
            Schema::table('port_security', function (Blueprint $table) {
                $table->index('port_number');
            });

            DB::statement('
                UPDATE port_security
                SET port_number = ports.port_number
                FROM ports
                WHERE port_security.port_id = ports.id
            ');

            DB::statement('
                DELETE FROM port_security
                WHERE id NOT IN (
                    SELECT MIN(id)
                    FROM port_security
                    GROUP BY port_number
                )
            ');

            // Remove orphaned rows where we couldn't resolve a port_number
            DB::statement('DELETE FROM port_security WHERE port_number IS NULL');

            Schema::table('port_security', function (Blueprint $table) {
                $table->unsignedInteger('port_number')->nullable(false)->change();
                $table->unique('port_number');
                $table->index('port_number');
            });

            Schema::table('port_security', function (Blueprint $table) {
                $table->dropForeign(['port_id']);
                $table->dropColumn('port_id');
            });
        }

        // Recreate the popular_ports materialized view with port_number instead of port_id
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
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

            DB::statement("CREATE INDEX popular_ports_port_number_idx ON popular_ports (port_number)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the updated materialized view
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS popular_ports CASCADE');
        }

        Schema::table('port_security', function (Blueprint $table) {
            // Drop the port_number column and its constraints
            $table->dropUnique(['port_number']);
            $table->dropIndex(['port_number']);
            $table->dropColumn('port_number');

            // Restore port_id column
            $table->foreignId('port_id')->unique()->after('id')->constrained('ports')->onDelete('cascade');
        });

        // Recreate the old popular_ports materialized view with port_id
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
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
