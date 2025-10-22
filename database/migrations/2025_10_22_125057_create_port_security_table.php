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
        Schema::create('port_security', function (Blueprint $table) {
            $table->id();
            $table->foreignId('port_id')->unique()->constrained('ports')->onDelete('cascade');
            $table->unsignedBigInteger('shodan_exposed_count')->default(0);
            $table->timestamp('shodan_updated_at')->nullable();
            $table->unsignedBigInteger('censys_exposed_count')->default(0);
            $table->timestamp('censys_updated_at')->nullable();
            $table->unsignedInteger('cve_count')->default(0);
            $table->string('latest_cve', 50)->nullable();
            $table->timestamp('cve_updated_at')->nullable();
            $table->json('top_countries')->nullable(); // Top 5 countries by exposure
            $table->text('security_recommendations')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('port_id');
            $table->index('shodan_updated_at');
            $table->index('censys_updated_at');
            $table->index('cve_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_security');
    }
};
