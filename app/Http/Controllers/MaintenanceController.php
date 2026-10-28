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
    private const TASKS = ['cache', 'storage', 'migrate', 'composer', 'all'];

    public function run(Request $request, string $task): JsonResponse
    {
        abort_unless(in_array($task, self::TASKS, true), 404);
        $this->authorizeRequest($request);

        @set_time_limit(0);
        $steps = $task === 'all' ? ['composer', 'migrate', 'storage', 'cache'] : [$task];

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
