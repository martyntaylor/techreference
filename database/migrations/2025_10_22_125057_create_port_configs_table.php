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
        Schema::create('port_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('port_id')->constrained('ports')->onDelete('cascade');
            $table->string('platform', 50); // Docker, Kubernetes, iptables, ufw, firewall-cmd, Windows Firewall, nginx, apache
            $table->string('config_type', 50); // firewall, proxy, server, container
            $table->string('title', 200);
            $table->text('code_snippet');
            $table->string('language', 20)->default('bash'); // For syntax highlighting
            $table->text('explanation')->nullable();
            $table->boolean('verified')->default(false);
            $table->unsignedInteger('upvotes')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('port_id');
            $table->index('platform');
            $table->index('config_type');
            $table->index(['port_id', 'platform']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_configs');
    }
};
