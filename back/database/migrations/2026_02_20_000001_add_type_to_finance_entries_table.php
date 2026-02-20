<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_entries', function (Blueprint $table) {
            $table->enum('type', ['expense', 'income'])->default('expense')->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('finance_entries', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
