<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->boolean('is_not_for_public')->default(false)->after('final_url');
            $table->timestamp('published_at')->nullable()->after('is_not_for_public');
        });
    }

    public function down(): void
    {
        Schema::table('contents', fn (Blueprint $table) => $table->dropColumn([
            'is_not_for_public',
            'published_at',
        ]));
    }
};
