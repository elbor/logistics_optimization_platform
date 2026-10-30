<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Замість make:filament-user: задає роль явно (у колонки role немає default).
 * Пароль — лише через prompt, щоб не потрапив в історію shell.
 */
#[Signature('app:create-admin')]
#[Description('Create an active administrator account')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $input = [
            'email' => mb_strtolower(trim(text('Email', required: true))),
            'name' => trim(text('Name', required: true)),
            'password' => password('Password', required: true),
            'password_confirmation' => password('Repeat password', required: true),
        ];

        $validator = Validator::make($input, [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = new User([
            'email' => $input['email'],
            'name' => $input['name'],
            'password' => $input['password'],
        ]);
        $admin->role = UserRole::Admin;
        $admin->is_active = true;
        $admin->save();

        $this->info("Administrator {$admin->email} created.");

        return self::SUCCESS;
    }
}
