<?php

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('staff:create {name : Full name} {email : Address they sign in with} {--role=owner : owner, manager, fulfilment or support}')]
#[Description('Create a staff account and print its one-time password')]
class CreateStaff extends Command
{
    public function handle(): int
    {
        $role = StaffRole::tryFrom($this->option('role'));

        if ($role === null) {
            $this->error('The role must be one of: '.implode(', ', array_column(StaffRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $email = Str::lower($this->argument('email'));

        if (User::query()->where('email', $email)->exists()) {
            $this->error("A staff account for {$email} already exists.");

            return self::FAILURE;
        }

        $password = Str::password(20, symbols: false);

        User::create([
            'name' => $this->argument('name'),
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);

        // The password is shown once and stored only as a hash.
        $this->line("Email: {$email}");
        $this->line("Password: {$password}");

        return self::SUCCESS;
    }
}
