<?php

namespace App\Http\Controllers;

use App\Support\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/**
 * Server maintenance without SSH: clear caches, storage link, migrate, composer install.
 *
 *  - Admins: POST /admin/maintenance/{task} (session + CSRF protected, used by the dashboard tab).
 *  - Token:  GET|POST /maintenance/{task}?token=... when MAINTENANCE_TOKEN is set in .env.
 */
class MaintenanceController extends Controller
{
    private const TASKS = ['key', 'cache', 'storage', 'migrate', 'seed', 'composer', 'artisan', 'all'];

    private bool $force = false;

    public function run(Request $request, string $task): JsonResponse
    {
        abort_unless(in_array($task, self::TASKS, true), 404);
        $this->authorizeRequest($request);
        $this->force = $request->boolean('force'); // only honoured after authorisation

        @set_time_limit(0);
        $steps = match ($task) {
            'all' => ['composer', 'key', 'migrate', 'storage', 'cache'],
            'artisan' => ['key', 'migrate', 'storage', 'cache'], // Artisan calls only, no composer
            default => [$task],
        };

        $results = [];
        foreach ($steps as $step) {
            try {
                $results[] = ['task' => $step] + $this->{$step}();
            } catch (\Throwable $e) {
                $results[] = ['task' => $step, 'ok' => false, 'output' => $e->getMessage()];
            }
            if (! end($results)['ok']) {
                break; // don't migrate on top of a failed composer install, etc.
            }
        }

        return response()->json(['ok' => ! in_array(false, array_column($results, 'ok'), true), 'results' => $results]);
    }

    private function authorizeRequest(Request $request): void
    {
        $user = $request->user();
        if ($user?->isAdmin() && $request->isMethod('post')) {
            return; // admin session: POST only, so CSRF protection applies
        }

        $secret = (string) config('maintenance.token');
        $given = (string) ($request->query('token') ?? $request->input('token', ''));
        abort_unless($secret !== '' && hash_equals($secret, $given), 403, 'Forbidden.');
    }

    /** Generates APP_KEY only when missing; regenerating logs everyone out and breaks encrypted data. */
    private function key(): array
    {
        $out = '';
        if (! is_file(base_path('.env')) && is_file(base_path('.env.example'))) {
            copy(base_path('.env.example'), base_path('.env'));
            $out = ".env created from .env.example\n";
        }

        if (config('app.key') && ! $this->force) {
            return ['ok' => true, 'output' => $out.'APP_KEY already set — skipped. Add &force=1 to regenerate it (logs everyone out and invalidates encrypted data).'];
        }

        Artisan::call('key:generate', ['--force' => true]);

        return ['ok' => true, 'output' => $out.trim(Artisan::output())];
    }

    private function cache(): array
    {
        Artisan::call('optimize:clear');
        SiteContent::flush();

        return ['ok' => true, 'output' => trim(Artisan::output())."\nLanding page cache cleared."];
    }

    private function storage(): array
    {
        if (is_link(public_path('storage')) || is_dir(public_path('storage'))) {
            return ['ok' => true, 'output' => 'public/storage already exists.'];
        }
        Artisan::call('storage:link');

        return ['ok' => true, 'output' => trim(Artisan::output())];
    }

    private function migrate(): array
    {
        Artisan::call('migrate', ['--force' => true]);

        return ['ok' => true, 'output' => trim(Artisan::output())];
    }

    /** Landing content + demo accounts/products. Idempotent: never overwrites existing rows. Not part of "all". */
    private function seed(): array
    {
        Artisan::call('db:seed', ['--force' => true]);

        return ['ok' => true, 'output' => trim(Artisan::output()) ?: 'Seeded.'];
    }

    private function composer(): array
    {
        $bin = is_file(base_path('composer.phar')) ? [PHP_BINARY, base_path('composer.phar')] : ['composer'];
        $args = ['install', '--no-interaction', '--no-progress', '--optimize-autoloader'];
        if (app()->isProduction()) {
            $args[] = '--no-dev';
        }

        $home = storage_path('composer');
        @mkdir($home, 0775, true);

        $process = new Process([...$bin, ...$args], base_path(), ['COMPOSER_HOME' => $home, 'HOME' => $home, 'COMPOSER_ALLOW_SUPERUSER' => '1']);
        $process->setTimeout(900);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => 'Could not run composer: '.$e->getMessage()];
        }

        return ['ok' => $process->isSuccessful(), 'output' => trim($process->getOutput()."\n".$process->getErrorOutput())];
    }
}
