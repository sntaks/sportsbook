<?php

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('admin:create {--name= : Full name} {--email= : Login email address} {--role= : One of super-admin, manager, trader, finance, support}')]
#[Description('Create a back-office admin account with a role')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $input = [
            'name' => $this->option('name') ?? text(label: 'Name', required: true),
            'email' => $this->option('email') ?? text(label: 'Email', required: true),
            'role' => $this->option('role') ?? select(label: 'Role', options: AdminRole::options(), default: AdminRole::SuperAdmin->value),
            'password' => password(label: 'Password', required: true),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(Admin::class)],
            'role' => ['required', Rule::enum(AdminRole::class)],
            'password' => ['required', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->safe();
        $role = AdminRole::from($validated->string('role')->toString());

        app(AdminRoleSeeder::class)->run();

        $admin = Admin::create([
            'name' => $validated->string('name')->toString(),
            'email' => $validated->string('email')->toString(),
            'password' => $validated->string('password')->toString(),
        ]);

        $admin->assignRole($role->value);

        $this->info("Admin {$admin->email} created with the {$role->value} role.");
        $this->line('They must set up an authenticator app the first time they sign in.');

        return self::SUCCESS;
    }
}
