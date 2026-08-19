<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->index(['is_content_request', 'status'], 'ideas_request_status_index');
            $table->index(['submitted_by', 'is_content_request'], 'ideas_submitter_request_index');
            $table->index(['is_urgent', 'needed_at'], 'ideas_urgent_needed_at_index');
        });
        Schema::table('contents', function (Blueprint $table) {
            $table->index(['status', 'publish_date'], 'contents_status_publish_date_index');
            $table->index(['pic_user_id', 'status', 'publish_date'], 'contents_pic_status_publish_date_index');
        });
        Schema::table('content_comments', function (Blueprint $table) {
            $table->index(['content_id', 'status'], 'content_comments_content_status_index');
        });
        Schema::table('accounts', function (Blueprint $table) {
            $table->unique(['platform_id', 'username'], 'accounts_platform_username_unique');
        });
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id', 'created_at'], 'activity_log_subject_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', fn (Blueprint $table) => $table->dropIndex('activity_log_subject_created_index'));
        Schema::table('accounts', fn (Blueprint $table) => $table->dropUnique('accounts_platform_username_unique'));
        Schema::table('content_comments', fn (Blueprint $table) => $table->dropIndex('content_comments_content_status_index'));
        Schema::table('contents', function (Blueprint $table) {
            $table->dropIndex('contents_status_publish_date_index');
            $table->dropIndex('contents_pic_status_publish_date_index');
        });
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropIndex('ideas_request_status_index');
            $table->dropIndex('ideas_submitter_request_index');
            $table->dropIndex('ideas_urgent_needed_at_index');
        });
    }
};
