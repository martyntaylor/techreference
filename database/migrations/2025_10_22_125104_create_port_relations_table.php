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
        Schema::create('port_relations', function (Blueprint $table) {
            $table->foreignId('port_id')->constrained('ports')->onDelete('cascade');
            $table->foreignId('related_port_id')->constrained('ports')->onDelete('cascade');
            $table->enum('relation_type', ['alternative', 'secure_version', 'deprecated_by', 'part_of_suite', 'conflicts_with']);
            $table->text('description')->nullable();
            $table->timestamps();

            // Composite primary key
            $table->primary(['port_id', 'related_port_id', 'relation_type'], 'port_relations_pk');

            // Indexes
            $table->index('port_id');
            $table->index('related_port_id');
            $table->index('relation_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_relations');
    }
};
