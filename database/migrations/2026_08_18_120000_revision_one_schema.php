<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ideas', fn (Blueprint $table) => $table->foreignId('format_id')->nullable()->after('series_id')->constrained('formats')->nullOnDelete());
        Schema::table('content_briefs', fn (Blueprint $table) => $table->text('main_copy')->nullable()->after('notes'));
        Schema::table('assets', fn (Blueprint $table) => $table->text('notes')->nullable()->after('url'));
        Schema::create('content_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['content_id', 'created_at']);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
        });

        DB::table('contents')->where('status', 'idea')->update(['status' => 'planned']);
        DB::table('contents')->where('status', 'on_progress')->update(['status' => 'in_production']);
    }

    public function down(): void
    {
        DB::table('contents')->where('status', 'planned')->update(['status' => 'idea']);
        DB::table('contents')->where('status', 'in_production')->update(['status' => 'on_progress']);
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn(['description', 'is_active']));
        Schema::dropIfExists('content_comments');
        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn('notes'));
        Schema::table('content_briefs', fn (Blueprint $table) => $table->dropColumn('main_copy'));
        Schema::table('ideas', fn (Blueprint $table) => $table->dropConstrainedForeignId('format_id'));
    }
};
