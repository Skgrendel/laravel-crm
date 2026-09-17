<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webkul\User\Repositories\UserRepository;

/**
 * Issues a Sanctum token for the reporting API. There is no screen for this
 * in Krayin, and the token is shown once — it is hashed in the database, so
 * it cannot be read back later.
 */
class IssueApiToken extends Command
{
    protected $signature = 'crm:api-token {email} {--name=reports} {--revoke : Revoke this user\'s existing tokens with the same name first}';

    protected $description = 'Issue a Sanctum API token for the reporting endpoints';

    public function handle(UserRepository $users): int
    {
        $user = $users->findOneWhere(['email' => $this->argument('email')]);

        if (! $user) {
            $this->error("No existe un usuario con el email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $name = $this->option('name');

        if ($this->option('revoke')) {
            $revoked = $user->tokens()->where('name', $name)->delete();

            $this->warn("Tokens revocados con el nombre \"{$name}\": {$revoked}");
        }

        $token = $user->createToken($name)->plainTextToken;

        $this->newLine();
        $this->info("Token para {$user->email} (\"{$name}\"):");
        $this->line($token);
        $this->newLine();
        $this->warn('Guardalo ahora: se muestra una sola vez, en base queda hasheado.');
        $this->newLine();
        $this->line('Uso:');
        $this->line('  curl -H "Authorization: Bearer <token>" -H "Accept: application/json" \\');
        $this->line('       '.url('/api/reports/leads'));

        return self::SUCCESS;
    }
}
