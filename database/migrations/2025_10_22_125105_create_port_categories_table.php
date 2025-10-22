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
        Schema::create('port_categories', function (Blueprint $table) {
            $table->foreignId('port_id')->constrained('ports')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->boolean('is_primary')->default(false); // Is this the primary category for this port?
            $table->timestamps();

            // Composite primary key
            $table->primary(['port_id', 'category_id']);

            // Indexes
            $table->index('port_id');
            $table->index('category_id');
            $table->index(['category_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_categories');
    }
};
