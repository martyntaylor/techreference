<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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
