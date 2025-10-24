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
        // Add view_count to port_pages table
        Schema::table('port_pages', function (Blueprint $table) {
            $table->unsignedInteger('view_count')->default(0)->after('video_urls');
            $table->index('view_count');
        });

        // Migrate existing view counts from ports to port_pages
        // Sum up all protocol variants for each port_number
        // Use MAX(service_name) to pick one service name per port
        if (config('database.default') === 'pgsql') {
            DB::statement("
                INSERT INTO port_pages (port_number, page_title, heading, view_count, created_at, updated_at)
                SELECT
                    p.port_number,
                    CONCAT('Port ', p.port_number, COALESCE(CONCAT(' - ', MAX(p.service_name)), '')) as page_title,
                    CONCAT('Port ', p.port_number, COALESCE(CONCAT(' - ', MAX(p.service_name)), '')) as heading,
                    SUM(p.view_count) as view_count,
                    NOW() as created_at,
                    NOW() as updated_at
                FROM ports p
                WHERE p.view_count > 0
                GROUP BY p.port_number
                ON CONFLICT (port_number)
                DO UPDATE SET view_count = EXCLUDED.view_count
            ");

            // Drop dependent materialized views if they exist (PostgreSQL only)
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS port_statistics CASCADE');
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS popular_ports CASCADE');
        } else {
            // SQLite version
            DB::statement("
                INSERT INTO port_pages (port_number, page_title, heading, view_count, created_at, updated_at)
                SELECT
                    p.port_number,
                    'Port ' || p.port_number || COALESCE(' - ' || MAX(p.service_name), '') as page_title,
                    'Port ' || p.port_number || COALESCE(' - ' || MAX(p.service_name), '') as heading,
                    SUM(p.view_count) as view_count,
                    datetime('now') as created_at,
                    datetime('now') as updated_at
                FROM ports p
                WHERE p.view_count > 0
                GROUP BY p.port_number
                ON CONFLICT (port_number)
                DO UPDATE SET view_count = EXCLUDED.view_count
            ");
        }

        // Remove view_count from ports table
        Schema::table('ports', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });

        // Recreate popular_ports materialized view (PostgreSQL only)
        if (config('database.default') === 'pgsql') {
            DB::statement("
                CREATE MATERIALIZED VIEW IF NOT EXISTS popular_ports AS
                SELECT
                    p.*,
                    pp.view_count
                FROM ports p
                JOIN port_pages pp ON p.port_number = pp.port_number
                ORDER BY pp.view_count DESC
                LIMIT 100
            ");

            // Create index on the materialized view
            DB::statement('CREATE INDEX IF NOT EXISTS popular_ports_view_count_idx ON popular_ports (view_count DESC)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add view_count back to ports table
        Schema::table('ports', function (Blueprint $table) {
            $table->unsignedInteger('view_count')->default(0);
        });

        // Remove view_count from port_pages table
        Schema::table('port_pages', function (Blueprint $table) {
            $table->dropIndex(['view_count']);
            $table->dropColumn('view_count');
        });
    }
};
