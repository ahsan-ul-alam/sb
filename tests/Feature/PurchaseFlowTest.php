<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_flow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Kit', 'price' => 100]);

        $this->actingAs($user)->getJson('/admin/stats')->assertForbidden();
        $this->actingAs($user)->getJson('/shop/products')->assertOk()->assertJsonCount(1);
        $this->actingAs($user)->putJson("/shop/selections/{$product->id}", ['quantity' => 2])->assertOk()->assertJsonPath('total', 200);

        $this->actingAs($admin)->getJson('/admin/selections')->assertOk()->assertJsonCount(1);

        $id = $this->actingAs($user)->postJson('/shop/requests', ['note' => 'hi'])->assertCreated()->json('id');
        $this->assertSame(1, $admin->unreadNotifications()->count());
        $this->actingAs($user)->getJson('/shop/selections')->assertJsonCount(0, 'items');

        $this->actingAs($admin)->putJson("/admin/requests/{$id}", ['status' => 'approved', 'admin_note' => 'ok'])
            ->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->actingAs($user)->postJson("/shop/requests/{$id}/cancel")->assertStatus(422);
    }
}
