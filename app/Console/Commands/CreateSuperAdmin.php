<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{
    protected $signature = 'kolabo:create-super-admin {email? : Super Admin email address} {--name= : Display name}';

    protected $description = 'Create or promote a Super Admin using an interactively entered password';

    public function handle(): int
    {
        if (! Role::where('name', 'Super Admin')->exists()) {
            $this->error('Super Admin role is missing. Run the MasterDataSeeder first.');

            return self::FAILURE;
        }

        $email = (string) ($this->argument('email') ?: $this->ask('Email'));
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            compact('email', 'name', 'password', 'confirmation'),
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', Password::min(12), 'same:confirmation'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $department = Department::where('name', 'Creative')->firstOrFail();
        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'department_id' => $department->id,
                'password' => Hash::make($password),
                'is_active' => true,
                'deleted_at' => null,
            ]
        );
        $user->syncRoles(['Super Admin']);

        $this->info("Super Admin {$email} is ready.");

        return self::SUCCESS;
    }
}
