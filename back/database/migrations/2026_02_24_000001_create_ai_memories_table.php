<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('ai_memories', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->string('category')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE ai_memories ADD COLUMN embedding vector(1024)');
        DB::statement('CREATE INDEX ai_memories_embedding_idx ON ai_memories USING ivfflat (embedding vector_cosine_ops) WITH (lists = 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_memories');
    }
};
