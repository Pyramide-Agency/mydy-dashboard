<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('canvas_id')->nullable();
            $table->foreignId('assignment_id')->constrained('lms_assignments')->cascadeOnDelete();
            $table->string('workflow_state')->default('unsubmitted'); // unsubmitted, submitted, graded, pending_review
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('grade', 8, 2)->nullable();
            $table->string('grade_str', 20)->nullable(); // letter grade: A, B+, etc.
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->boolean('late')->default(false);
            $table->boolean('missing')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_submissions');
    }
};
