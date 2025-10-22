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
        if (config('database.default') === 'pgsql') {
            // Partial indexes for frequently filtered queries
            // Index for high-risk ports only (smaller, faster for security queries)
            DB::statement("CREATE INDEX ports_high_risk_idx ON ports (port_number, service_name) WHERE risk_level = 'High'");

            // Index for official IANA ports
            DB::statement("CREATE INDEX ports_iana_official_idx ON ports (port_number) WHERE iana_official = true");

            // Index for encrypted ports
            DB::statement("CREATE INDEX ports_encrypted_idx ON ports (port_number, protocol) WHERE encrypted_default = true");

            // Composite indexes for common query patterns
            // Category filtering with risk level
            DB::statement("CREATE INDEX port_categories_with_risk_idx ON port_categories (category_id, port_id)");

            // Port configs by platform (for quick platform-specific config lookups)
            DB::statement("CREATE INDEX port_configs_platform_port_idx ON port_configs (platform, port_id) WHERE verified = true");

            // Verified issues ordered by popularity
            DB::statement("CREATE INDEX port_issues_verified_popular_idx ON port_issues (port_id, upvotes DESC) WHERE verified = true");

            // Security data with recent updates
            DB::statement("CREATE INDEX port_security_recent_idx ON port_security (port_id, shodan_updated_at DESC)");

            // Covering indexes (include columns to avoid table lookups)
            DB::statement("CREATE INDEX ports_list_covering_idx ON ports (port_number) INCLUDE (service_name, protocol, risk_level)");

            // Index for port relations lookup
            DB::statement("CREATE INDEX port_relations_type_idx ON port_relations (port_id, relation_type, related_port_id)");

            // Expression indexes for case-insensitive searches
            DB::statement("CREATE INDEX ports_service_name_lower_idx ON ports (LOWER(service_name))");
            DB::statement("CREATE INDEX software_name_lower_idx ON software (LOWER(name))");

            // BRIN index for timestamp columns (efficient for time-series data)
            DB::statement("CREATE INDEX ports_created_at_brin_idx ON ports USING BRIN (created_at)");
            DB::statement("CREATE INDEX port_security_shodan_updated_brin_idx ON port_security USING BRIN (shodan_updated_at)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS ports_high_risk_idx");
            DB::statement("DROP INDEX IF EXISTS ports_iana_official_idx");
            DB::statement("DROP INDEX IF EXISTS ports_encrypted_idx");
            DB::statement("DROP INDEX IF EXISTS port_categories_with_risk_idx");
            DB::statement("DROP INDEX IF EXISTS port_configs_platform_port_idx");
            DB::statement("DROP INDEX IF EXISTS port_issues_verified_popular_idx");
            DB::statement("DROP INDEX IF EXISTS port_security_recent_idx");
            DB::statement("DROP INDEX IF EXISTS ports_list_covering_idx");
            DB::statement("DROP INDEX IF EXISTS port_relations_type_idx");
            DB::statement("DROP INDEX IF EXISTS ports_service_name_lower_idx");
            DB::statement("DROP INDEX IF EXISTS software_name_lower_idx");
            DB::statement("DROP INDEX IF EXISTS ports_created_at_brin_idx");
            DB::statement("DROP INDEX IF EXISTS port_security_shodan_updated_brin_idx");
        }
    }
};
