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
        Schema::table('ports', function (Blueprint $table) {
            // Add data_source column to track where port data originated
            // IANA is the official source, others are supplementary data providers
            $table->string('data_source', 50)->nullable()->after('iana_updated_at');
            $table->index('data_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->dropIndex(['data_source']);
            $table->dropColumn('data_source');
        });
    }
};
