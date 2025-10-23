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
        Schema::create('port_cves', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('port_number')->index();
            $table->string('cve_id', 50)->index(); // e.g., CVE-2024-1234
            $table->text('description');
            $table->timestamp('published_date')->index();
            $table->timestamp('last_modified_date')->nullable();
            $table->decimal('cvss_score', 3, 1)->nullable()->index(); // 0.0 - 10.0
            $table->string('severity', 20)->nullable(); // CRITICAL, HIGH, MEDIUM, LOW
            $table->json('weakness_types')->nullable(); // CWE data
            $table->string('source', 50)->default('NVD'); // NVD, manual, etc.
            $table->timestamps();

            // Unique constraint: one CVE per port
            $table->unique(['port_number', 'cve_id']);

            // Index for querying recent CVEs by port
            $table->index(['port_number', 'published_date']);
            $table->index(['port_number', 'severity', 'published_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_cves');
    }
};
