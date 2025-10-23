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
        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('port_number');
            $table->string('protocol', 10); // TCP, UDP, SCTP
            $table->string('service_name', 100)->nullable();
            $table->string('transport_protocol', 10)->nullable(); // For display
            $table->text('description')->nullable();
            $table->boolean('iana_official')->default(false);
            $table->string('iana_status', 50)->nullable(); // Official, Unofficial, Reserved
            $table->timestamp('iana_updated_at')->nullable();
            $table->enum('risk_level', ['High', 'Medium', 'Low'])->default('Low');
            $table->text('security_notes')->nullable();
            $table->boolean('encrypted_default')->default(false);
            $table->text('common_uses')->nullable();
            $table->text('historical_context')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->text('search_vector')->nullable(); // For PostgreSQL full-text search
            $table->timestamps();

            // Composite unique constraint: same port number can exist for different protocols
            $table->unique(['port_number', 'protocol'], 'ports_port_number_protocol_unique');

            // Indexes
            $table->index('protocol');
            $table->index('service_name');
            $table->index('risk_level');
            $table->index('iana_official');
        });

        // Add constraints and indexes based on database driver
        if (config('database.default') === 'pgsql') {
            // Add check constraint for PostgreSQL (port 0 is valid - reserved by IANA)
            DB::statement('ALTER TABLE ports ADD CONSTRAINT ports_port_number_check CHECK (port_number >= 0 AND port_number <= 65535)');

            // Add full-text search index for PostgreSQL
            DB::statement('CREATE INDEX ports_search_vector_idx ON ports USING GIN(to_tsvector(\'english\', COALESCE(service_name, \'\') || \' \' || COALESCE(description, \'\')))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ports');
    }
};
