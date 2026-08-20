<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Department;
use App\Models\Format;
use App\Models\Pillar;
use App\Models\Platform;
use App\Models\Series;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MasterDataSeeder extends Seeder
{
    public const PERMISSIONS = [
        'dashboard.view', 'sales_dashboard.view', 'content_request.create',
        'content.view', 'content.create', 'content.edit', 'content.delete', 'content.change_status',
        'ideas.view_all', 'ideas.view_own', 'ideas.create', 'ideas.edit_own', 'ideas.edit_all',
        'ideas.delete', 'ideas.select', 'ideas.change_status', 'ideas.move_to_content',
        'ideas.convert', 'ideas.bulk_move_to_content', 'ideas.bulk_import',
        'calendar.view', 'calendar.edit', 'calendar.reschedule',
        'production.view', 'production.view_all_tasks', 'production.change_status', 'production.rollback_published',
        'published.view', 'assets.view', 'assets.create', 'assets.delete',
        'comments.create', 'comments.edit_own', 'comments.delete_own', 'comments.manage', 'comments.resolve',
        'team.view', 'users.view', 'users.create', 'users.edit', 'users.deactivate',
        'users.delete', 'users.change_password', 'users.change_status',
        'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
        'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete', 'accounts.change_status',
        'pillars.view', 'pillars.manage', 'series.manage', 'formats.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['Creative', 'Sales', 'HR', 'Finance', 'Product', 'Management', 'Other'] as $name) {
            Department::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]);
        }

        $taxonomy = [
            'Product' => ['Kolabo Daily Use', 'Kolabo Features'],
            'News' => ['Insider Update'],
            'Insight / Education' => ['Tips', 'Business 101', 'Trivia'],
            'Entertainment' => ['Office Life', 'Meme', 'POV'],
            'Community' => ['KolaboUpNext'],
            'Brand' => ['Inside Kolabo', 'Team BTS'],
        ];

        foreach ($taxonomy as $pillarName => $seriesNames) {
            $pillar = Pillar::firstOrCreate(
                ['slug' => Str::slug($pillarName)],
                ['name' => $pillarName, 'is_active' => true]
            );
            foreach ($seriesNames as $seriesName) {
                Series::firstOrCreate(
                    ['pillar_id' => $pillar->id, 'slug' => Str::slug($seriesName)],
                    ['name' => $seriesName, 'is_active' => true]
                );
            }
        }

        foreach (['Instagram', 'TikTok', 'Threads', 'X', 'LinkedIn', 'YouTube'] as $name) {
            Platform::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]);
        }
        foreach (['Reels', 'Carousel', 'Single Post', 'Story', 'Threads', 'Short Video', 'Long Video'] as $name) {
            Format::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]);
        }

        $accounts = [
            ['Instagram', 'Kolabo', '@kolabo.id', 'Kolabo.id'],
            ['Instagram', 'Kolabo Insider', '@kolabo.insider', 'Kolabo Insider'],
            ['TikTok', 'Kolabo', '@kolabo.id', 'TikTok Kolabo'],
            ['Threads', 'Kolabo', '@kolabo.id', 'Threads Kolabo'],
            ['X', 'Kolabo', '@kolabo_id', 'X Kolabo'],
            ['LinkedIn', 'Kolabo', 'Kolabo', 'LinkedIn Kolabo'],
            ['YouTube', 'Kolabo', 'Kolabo', 'YouTube Kolabo'],
        ];
        foreach ($accounts as [$platformName, $accountName, $username, $legacyName]) {
            $platform = Platform::where('name', $platformName)->firstOrFail();
            Account::withTrashed()->firstOrCreate(
                ['platform_id' => $platform->id, 'username' => $username],
                [
                    'name' => $legacyName,
                    'slug' => Str::slug($legacyName),
                    'account_name' => $accountName,
                    'is_active' => true,
                ]
            );
        }

        foreach (self::PERMISSIONS as $name) {
            Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'Super Admin' => self::PERMISSIONS,
            'Creative Lead' => array_values(array_filter(
                self::PERMISSIONS,
                fn (string $permission) => ! str_starts_with($permission, 'roles.')
                    && ! str_contains($permission, '.manage')
                    && ! str_starts_with($permission, 'pillars.')
            )),
            'Creative Member' => [
                'dashboard.view', 'content.view', 'content.create', 'content.edit', 'content.change_status',
                'ideas.view_all', 'ideas.create', 'ideas.edit_all', 'ideas.select', 'ideas.change_status',
                'ideas.move_to_content', 'ideas.convert', 'calendar.view', 'calendar.edit',
                'production.view', 'production.change_status', 'published.view', 'assets.view',
                'assets.create', 'comments.create', 'comments.resolve',
            ],
            'Sales Contributor' => [
                'sales_dashboard.view', 'content_request.create', 'ideas.view_own', 'ideas.create',
                'ideas.bulk_import', 'calendar.view',
            ],
            'Viewer' => ['dashboard.view', 'content.view', 'calendar.view', 'published.view'],
        ];

        foreach ($rolePermissions as $name => $permissions) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'description' => match ($name) {
                        'Super Admin' => 'Protected administrator role',
                        'Creative Lead' => 'Leads the creative workflow',
                        default => null,
                    },
                    'is_active' => true,
                ]
            );
            if ($role->wasRecentlyCreated || $name === 'Super Admin') {
                $role->syncPermissions($permissions);
            }
            if ($name === 'Creative Lead') {
                $role->givePermissionTo('production.view_all_tasks');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
