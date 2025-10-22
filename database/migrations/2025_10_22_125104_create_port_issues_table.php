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
        Schema::create('port_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('port_id')->constrained('ports')->onDelete('cascade');
            $table->string('issue_title', 200);
            $table->text('symptoms');
            $table->text('solution');
            $table->string('error_code', 50)->nullable();
            $table->string('platform', 50)->nullable(); // Windows, Linux, macOS, Docker, etc.
            $table->boolean('verified')->default(false);
            $table->unsignedInteger('upvotes')->default(0);
            $table->string('contributor_name', 100)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('port_id');
            $table->index('verified');
            $table->index('upvotes');
            $table->index(['port_id', 'verified']);
        });

        // Add full-text search index for PostgreSQL
        if (config('database.default') === 'pgsql') {
            DB::statement('CREATE INDEX port_issues_search_idx ON port_issues USING GIN(to_tsvector(\'english\', COALESCE(issue_title, \'\') || \' \' || COALESCE(symptoms, \'\') || \' \' || COALESCE(solution, \'\')))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_issues');
    }
};
