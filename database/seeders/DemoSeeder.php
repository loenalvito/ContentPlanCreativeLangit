<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\Department;
use App\Models\Format;
use App\Models\Idea;
use App\Models\Pillar;
use App\Models\Platform;
use App\Models\Series;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['Rayhan Admin', 'admin@kolabo.id', 'Creative', 'Super Admin'],
            ['Dina Lead', 'lead@kolabo.id', 'Creative', 'Creative Lead'],
            ['Fadly Creative', 'fadly@kolabo.id', 'Creative', 'Creative Member'],
            ['Nabila Creative', 'nabila@kolabo.id', 'Creative', 'Creative Member'],
            ['Andi Sales', 'sales@kolabo.id', 'Sales', 'Sales Contributor'],
        ];
        $users = [];
        foreach ($definitions as [$name, $email, $department, $role]) {
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'department_id' => Department::where('name', $department)->value('id'),
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
            $user->syncRoles([$role]);
            $users[] = $user;
        }

        $titles = [
            'Kolabo Daily Use — Finance', 'Insider Update — AI Rp66 Triliun',
            'Office Life — Meeting Be Like', 'Business 101 — Cash Flow',
            'Kolabo Features — Approval', 'Tips — Cara Kelola Stok',
            'POV — Admin Banyak Kerjaan', 'KolaboUpNext — Teaser',
            'Inside Kolabo — Office Culture', 'Team BTS — Shooting Day',
        ];
        $statuses = ['scheduled', 'review', 'in_production', 'approved', 'scheduled', 'in_production', 'planned', 'scheduled', 'published', 'published'];

        foreach ($titles as $index => $title) {
            $series = Series::where('name', explode(' — ', $title)[0])->first() ?? Series::firstOrFail();
            $content = Content::updateOrCreate(
                ['title' => $title],
                [
                    'publish_date' => today()->addDays($index - 2),
                    'account_id' => Account::firstOrFail()->id,
                    'pillar_id' => $series->pillar_id,
                    'series_id' => $series->id,
                    'format_id' => Format::orderBy('id')->skip($index % Format::count())->value('id'),
                    'pic_user_id' => $users[2 + ($index % 2)]->id,
                    'status' => $statuses[$index],
                    'final_url' => $statuses[$index] === 'published' ? 'https://instagram.com/kolabo.id' : null,
                    'created_by' => $users[0]->id,
                    'updated_by' => $users[0]->id,
                    'deleted_at' => null,
                ]
            );
            $platform = Platform::orderBy('id')->skip($index % Platform::count())->firstOrFail();
            $account = Account::where('platform_id', $platform->id)->where('is_active', true)->first();
            $content->platforms()->sync([$platform->id => ['account_id' => $account?->id]]);
            ContentBrief::updateOrCreate(
                ['content_id' => $content->id],
                [
                    'hook' => 'Buka dengan pertanyaan yang relevan untuk audiens.',
                    'angle' => 'Praktis, ringkas, dan mudah diterapkan.',
                    'key_message' => 'Kolabo membantu tim bekerja lebih rapi.',
                    'cta' => 'Simpan dan bagikan posting ini.',
                ]
            );
        }

        $idea = Idea::updateOrCreate(
            ['idea' => 'Kenapa banyak lead berhenti setelah quotation?'],
            [
                'pillar_id' => Pillar::where('name', 'Insight / Education')->value('id'),
                'series_id' => Series::where('name', 'Business 101')->value('id'),
                'format_id' => Format::where('name', 'Reels')->value('id'),
                'submitted_by' => $users[4]->id,
                'source_department_id' => Department::where('name', 'Sales')->value('id'),
                'status' => 'new',
                'deleted_at' => null,
            ]
        );
        $idea->platforms()->sync([Platform::where('name', 'Instagram')->value('id')]);
    }
}
