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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // created, updated, deleted, login, logout, etc.

            // Polymorphic target; both nullable for non-model events (e.g., login_failed)
            $table->nullableMorphs('auditable');

            // Store structured data as JSON (PostgreSQL uses jsonb, others use json)
            $connection = Schema::getConnection();
            $driver = $connection->getDriverName();

            if ($driver === 'pgsql') {
                $connection->statement('ALTER TABLE audit_logs ADD COLUMN old_values jsonb NULL');
                $connection->statement('ALTER TABLE audit_logs ADD COLUMN new_values jsonb NULL');
            } else {
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
            }

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            // Note: nullableMorphs() already creates an index on auditable_type and auditable_id
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
