<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_seeder_is_idempotent_and_does_not_create_demo_records(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->assertDatabaseCount('departments', 7);
        $this->assertDatabaseCount('pillars', 6);
        $this->assertDatabaseCount('platforms', 6);
        $this->assertDatabaseCount('formats', 7);
        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permissions', count(MasterDataSeeder::PERMISSIONS));
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('ideas', 0);
        $this->assertDatabaseCount('contents', 0);
    }

    public function test_database_seeder_keeps_demo_data_available_in_testing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@kolabo.id', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['email' => 'sales@kolabo.id', 'is_active' => true]);
        $this->assertDatabaseCount('contents', 10);
    }

    public function test_query_indexes_and_account_uniqueness_are_present(): void
    {
        $indexNames = fn (string $table) => collect(Schema::getIndexes($table))->pluck('name');

        $this->assertTrue($indexNames('contents')->contains('contents_status_publish_date_index'));
        $this->assertTrue($indexNames('contents')->contains('contents_pic_status_publish_date_index'));
        $this->assertTrue($indexNames('ideas')->contains('ideas_request_status_index'));
        $this->assertTrue($indexNames('content_comments')->contains('content_comments_content_status_index'));
        $this->assertTrue($indexNames('accounts')->contains('accounts_platform_username_unique'));
    }
}
