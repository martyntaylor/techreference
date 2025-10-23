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
            $table->json('top_products')->nullable()->after('top_countries'); // Top 10 software products
            $table->json('top_organizations')->nullable()->after('top_products'); // Top 10 hosting orgs
            $table->json('top_operating_systems')->nullable()->after('top_organizations'); // Top 10 OS
            $table->json('top_asns')->nullable()->after('top_operating_systems'); // Top 10 ASNs
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('port_security', function (Blueprint $table) {
            $table->dropColumn(['top_products', 'top_organizations', 'top_operating_systems', 'top_asns']);
        });
    }
};
