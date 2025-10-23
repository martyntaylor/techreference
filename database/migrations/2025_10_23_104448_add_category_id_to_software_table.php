<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add category_id column
        Schema::table('software', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('slug')->constrained('categories')->onDelete('set null');
            $table->index('category_id');
        });

        // Migrate existing string categories to category_id
        $this->migrateCategories();

        // After migration is successful, we can eventually drop the string category column
        // But keep it for now in case we need to reference it
    }

    /**
     * Migrate existing string categories to foreign key references.
     */
    private function migrateCategories(): void
    {
        $categoryMap = [
            'Web Server' => 'web-services',
            'Database' => 'database',
            'Remote Access' => 'remote-access',
            'File Transfer' => 'file-transfer',
            'Email' => 'email',
            'DNS' => 'dns',
            'CDN/Load Balancer' => 'web-services',
            'Other' => null, // Don't map "Other"
        ];

        foreach ($categoryMap as $stringCategory => $slug) {
            if ($slug === null) {
                continue;
            }

            $category = DB::table('categories')->where('slug', $slug)->first();

            if ($category) {
                DB::table('software')
                    ->where('category', $stringCategory)
                    ->update(['category_id' => $category->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('software', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
