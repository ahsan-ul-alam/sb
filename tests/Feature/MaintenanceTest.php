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
        $this->actingAs(User::factory()->create())->post('/maintenance/cache')->assertForbidden();
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

    public function test_admin_must_post_and_can_run_storage_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/maintenance/cache')->assertForbidden(); // GET with only a session is refused
        $this->actingAs($admin)->postJson('/maintenance/storage')->assertOk()->assertJsonPath('ok', true);
    }
}
