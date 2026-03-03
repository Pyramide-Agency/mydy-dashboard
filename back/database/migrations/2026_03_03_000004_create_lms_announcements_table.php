<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('canvas_id')->unique();
            $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('author')->nullable();
            $table->string('author_avatar_url')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('html_url')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_announcements');
    }
};
