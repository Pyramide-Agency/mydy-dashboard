<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_entries', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->date('date');
            $table->enum('source', ['web', 'telegram'])->default('web');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_entries');
    }
};
