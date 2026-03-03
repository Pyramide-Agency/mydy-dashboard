<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_courses', function (Blueprint $table) {
            $table->id();
            $table->string('canvas_id')->unique();
            $table->string('name');
            $table->string('course_code')->nullable();
            $table->text('description')->nullable();
            $table->string('instructor')->nullable();
            $table->string('workflow_state')->default('available'); // available, completed, deleted
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('image_download_url')->nullable();
            $table->string('color', 20)->nullable(); // user-assigned color
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_courses');
    }
};
