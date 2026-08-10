<?php

namespace OTGH\AccessControl\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use OTGH\AccessControl\Core\Models\User;

class CreateInitialAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-initial-admin-user
                            {--name= : Initial admin full name}
                            {--email= : Initial admin email}
                            {--password= : Initial admin password}
                            {--update-existing : Update an existing user with matching email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the initial Aurora Access admin login user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = (string) $this->option('name');
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');

        if ($name === '' && $this->input->isInteractive()) {
            $name = (string) $this->ask('Initial admin name');
        }

        if ($email === '' && $this->input->isInteractive()) {
            $email = (string) $this->ask('Initial admin email');
        }

        if ($password === '' && $this->input->isInteractive()) {
            $password = (string) $this->secret('Initial admin password (minimum 8 characters)');
        }

        $validation = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $userModelClass = config('auth.providers.users.model', User::class);

        if (! is_string($userModelClass) || ! is_subclass_of($userModelClass, AuthenticatableUser::class)) {
            $this->error('Configured auth user model is invalid.');

            return self::FAILURE;
        }

        /** @var AuthenticatableUser|null $existing */
        $existing = $userModelClass::query()->where('email', $email)->first();

        $payload = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ];

        if ($existing instanceof AuthenticatableUser) {
            if (! (bool) $this->option('update-existing')) {
                $this->warn(sprintf('User with email %s already exists, skipping.', $email));

                return self::SUCCESS;
            }

            $existing->forceFill($payload);
            $existing->save();

            $this->info(sprintf('Updated existing admin user: %s', $email));

            return self::SUCCESS;
        }

        /** @var AuthenticatableUser $newUser */
        $newUser = new $userModelClass;
        $newUser->forceFill($payload);
        $newUser->save();

        $this->info(sprintf('Created initial admin user: %s', $email));

        return self::SUCCESS;
    }
}
