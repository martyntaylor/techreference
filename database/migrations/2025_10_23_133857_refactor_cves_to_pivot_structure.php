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
        // Drop the old port_cves table
        Schema::dropIfExists('port_cves');

        // Create master CVEs table (one record per CVE globally)
        Schema::create('cves', function (Blueprint $table) {
            $table->id();
            $table->string('cve_id', 50)->unique()->index(); // e.g., CVE-2024-1234
            $table->text('description');
            $table->timestamp('published_date')->index();
            $table->timestamp('last_modified_date')->nullable();
            $table->decimal('cvss_score', 3, 1)->nullable()->index(); // 0.0 - 10.0
            $table->string('severity', 20)->nullable()->index(); // CRITICAL, HIGH, MEDIUM, LOW
            $table->json('weakness_types')->nullable(); // CWE data
            $table->json('references')->nullable(); // External links
            $table->string('source', 50)->default('NVD'); // NVD, manual, etc.
            $table->timestamps();
        });

        // Create pivot table linking CVEs to ports
        Schema::create('cve_port', function (Blueprint $table) {
            $table->id();
            $table->string('cve_id', 50)->index();
            $table->unsignedInteger('port_number')->index();
            $table->tinyInteger('relevance_score')->nullable(); // 1-10, how relevant is this CVE to this port
            $table->timestamps();

            // Foreign keys
            $table->foreign('cve_id')->references('cve_id')->on('cves')->onDelete('cascade');

            // Unique: one CVE can only be linked to a port once
            $table->unique(['cve_id', 'port_number']);

            // Composite indexes for queries
            $table->index(['port_number', 'cve_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cve_port');
        Schema::dropIfExists('cves');

        // Recreate old structure
        Schema::create('port_cves', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('port_number')->index();
            $table->string('cve_id', 50)->index();
            $table->text('description');
            $table->timestamp('published_date')->index();
            $table->timestamp('last_modified_date')->nullable();
            $table->decimal('cvss_score', 3, 1)->nullable()->index();
            $table->string('severity', 20)->nullable();
            $table->json('weakness_types')->nullable();
            $table->string('source', 50)->default('NVD');
            $table->timestamps();
            $table->unique(['port_number', 'cve_id']);
        });
    }
};
