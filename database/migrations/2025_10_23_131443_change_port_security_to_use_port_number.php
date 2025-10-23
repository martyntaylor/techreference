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
        // Drop materialized views that depend on port_security.port_id
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS popular_ports CASCADE');
        }

        // Step 1: Add port_number column (nullable initially to populate data)
        Schema::table('port_security', function (Blueprint $table) {
            $table->unsignedInteger('port_number')->nullable()->after('id');
        });

        // Step 2: Populate port_number from ports table via port_id
        DB::statement('
            UPDATE port_security
            SET port_number = ports.port_number
            FROM ports
            WHERE port_security.port_id = ports.id
        ');

        // Step 2b: Remove duplicate port_security records (keep only the first one per port_number)
        // This is needed because the old design had one record per port_id, but we want one per port_number
        DB::statement('
            DELETE FROM port_security
            WHERE id NOT IN (
                SELECT MIN(id)
                FROM port_security
                GROUP BY port_number
            )
        ');

        // Step 3: Make port_number NOT NULL and add constraints
        Schema::table('port_security', function (Blueprint $table) {
            // Make port_number required
            $table->unsignedInteger('port_number')->nullable(false)->change();

            // Add unique constraint on port_number (one security record per port number)
            $table->unique('port_number');

            // Add index for faster lookups (note: cannot add foreign key because ports.port_number is not unique)
            $table->index('port_number');
        });

        // Step 4: Drop the old port_id foreign key and column
        Schema::table('port_security', function (Blueprint $table) {
            $table->dropForeign(['port_id']);
            $table->dropColumn('port_id');
        });

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
