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
        Schema::table('port_security', function (Blueprint $table) {
            // Severity breakdown counts
            $table->unsignedInteger('cve_critical_count')->default(0)->after('cve_count');
            $table->unsignedInteger('cve_high_count')->default(0)->after('cve_critical_count');
            $table->unsignedInteger('cve_medium_count')->default(0)->after('cve_high_count');
            $table->unsignedInteger('cve_low_count')->default(0)->after('cve_medium_count');

            // Average CVSS score
            $table->decimal('cve_avg_score', 3, 1)->nullable()->after('cve_low_count');

            // Top recent critical/high CVEs with details (JSON array)
            $table->json('cve_critical_recent')->nullable()->after('latest_cve');

            // Common weakness types (CWE)
            $table->json('cve_weakness_types')->nullable()->after('cve_critical_recent');

            // Index for severity counts for querying
            $table->index('cve_critical_count');
            $table->index('cve_high_count');
            $table->index('cve_avg_score');
        });

        // PostgreSQL-specific improvements: data integrity and JSON query performance
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Add CHECK constraint for cve_avg_score range (CVSS scores are 0-10)
            DB::statement('ALTER TABLE port_security ADD CONSTRAINT cve_avg_score_range CHECK (cve_avg_score IS NULL OR (cve_avg_score >= 0 AND cve_avg_score <= 10))');

            // Optional: GIN indexes for JSON fields to improve query performance
            // Uncomment if you plan to query JSON contents frequently
            // DB::statement('CREATE INDEX port_security_cve_critical_recent_gin ON port_security USING GIN (cve_critical_recent jsonb_path_ops)');
            // DB::statement('CREATE INDEX port_security_cve_weakness_types_gin ON port_security USING GIN (cve_weakness_types jsonb_path_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop PostgreSQL-specific constraints and indexes
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Drop CHECK constraint
            DB::statement('ALTER TABLE port_security DROP CONSTRAINT IF EXISTS cve_avg_score_range');

            // Drop GIN indexes if they were created
            // DB::statement('DROP INDEX IF EXISTS port_security_cve_critical_recent_gin');
            // DB::statement('DROP INDEX IF EXISTS port_security_cve_weakness_types_gin');
        }

        Schema::table('port_security', function (Blueprint $table) {
            $table->dropIndex(['cve_critical_count']);
            $table->dropIndex(['cve_high_count']);
            $table->dropIndex(['cve_avg_score']);

            $table->dropColumn([
                'cve_critical_count',
                'cve_high_count',
                'cve_medium_count',
                'cve_low_count',
                'cve_avg_score',
                'cve_critical_recent',
                'cve_weakness_types',
            ]);
        });
    }
};
