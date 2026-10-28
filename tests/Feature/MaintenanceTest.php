<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_and_normal_users_are_blocked(): void
    {
        config(['maintenance.token' => 'secret-token']);

        $this->get('/maintenance/cache')->assertForbidden();
        $this->get('/maintenance/cache?token=wrong')->assertForbidden();
        $this->post('/maintenance/cache')->assertForbidden();
    }

    public function test_disabled_token_never_matches(): void
    {
        config(['maintenance.token' => '']);

        $this->get('/maintenance/cache?token=')->assertForbidden();
    }

    public function test_token_url_clears_cache(): void
    {
        config(['maintenance.token' => 'secret-token']);

        $this->getJson('/maintenance/cache?token=secret-token')->assertOk()->assertJsonPath('ok', true);
        $this->getJson('/maintenance/nope?token=secret-token')->assertNotFound();
    }

    public function test_artisan_task_runs_migrate_storage_and_cache(): void
    {
        config(['maintenance.token' => 'secret-token']);

        $this->getJson('/maintenance/artisan?token=secret-token')->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(4, 'results')
            ->assertJsonPath('results.0.task', 'key')
            ->assertJsonPath('results.1.task', 'migrate')
            ->assertJsonPath('results.2.task', 'storage')
            ->assertJsonPath('results.3.task', 'cache');
    }

    public function test_existing_app_key_is_never_overwritten_without_force(): void
    {
        config(['maintenance.token' => 'secret-token']);

        $this->getJson('/maintenance/key?token=secret-token')->assertOk()
            ->assertJsonPath('results.0.ok', true)
            ->assertJsonPath('results.0.output', fn ($o) => str_contains($o, 'already set'));
    }

    public function test_token_route_works_on_a_fresh_install_without_app_key(): void
    {
        config(['maintenance.token' => 'secret-token', 'app.key' => '']);

        // no cookies/session/CSRF involved, so a missing key must not break it
        $this->getJson('/maintenance/cache?token=secret-token')->assertOk()->assertJsonPath('ok', true);
    }

    public function test_admin_must_post_and_can_run_storage_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/maintenance/cache')->assertForbidden(); // token route ignores sessions
        $this->actingAs($admin)->getJson('/admin/maintenance/cache')->assertStatus(405); // admin route is POST only
        $this->actingAs($admin)->postJson('/admin/maintenance/storage')->assertOk()->assertJsonPath('ok', true);
        $this->actingAs(User::factory()->create())->postJson('/admin/maintenance/cache')->assertForbidden();
    }
}
