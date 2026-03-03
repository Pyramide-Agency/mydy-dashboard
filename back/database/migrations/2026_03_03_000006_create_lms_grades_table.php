<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->string('current_score', 20)->nullable();  // e.g. "85.5"
            $table->string('final_score', 20)->nullable();
            $table->string('current_grade', 20)->nullable();  // e.g. "B+"
            $table->string('final_grade', 20)->nullable();
            $table->decimal('current_points', 8, 2)->nullable();
            $table->decimal('final_points', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_grades');
    }
};
