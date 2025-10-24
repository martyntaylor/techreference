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
        Schema::create('port_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('port_number')->unique();
            $table->string('page_title');
            $table->string('meta_description', 160)->nullable();
            $table->string('heading');
            $table->json('content_blocks')->nullable(); // Array of {type, title, content, order}
            $table->json('faqs')->nullable(); // Array of {question, answer, order}
            $table->json('video_urls')->nullable(); // Array of {url, title, description}
            $table->timestamps();

            $table->index('port_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_pages');
    }
};
