<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('canvas_id')->unique();
            $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('lock_at')->nullable();
            $table->decimal('points_possible', 8, 2)->nullable();
            $table->string('submission_types')->nullable(); // online_upload, online_text_entry, etc.
            $table->string('assignment_type')->default('assignment'); // assignment, quiz, discussion
            $table->string('workflow_state')->default('published');
            $table->string('html_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_assignments');
    }
};
