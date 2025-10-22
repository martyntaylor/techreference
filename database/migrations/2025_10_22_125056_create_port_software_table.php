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
        Schema::create('port_software', function (Blueprint $table) {
            $table->foreignId('port_id')->constrained('ports')->onDelete('cascade');
            $table->foreignId('software_id')->constrained('software')->onDelete('cascade');
            $table->boolean('is_default')->default(false); // Is this the default software for this port?
            $table->string('config_notes')->nullable();
            $table->timestamps();

            // Composite primary key
            $table->primary(['port_id', 'software_id']);

            // Indexes
            $table->index('port_id');
            $table->index('software_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_software');
    }
};
