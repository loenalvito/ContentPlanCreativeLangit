<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('content_comments', function (Blueprint $table) {
            $table->string('status', 20)->default('open')->after('body')->index();
            $table->foreignId('resolved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('content_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'resolved_at']);
        });
    }
};
