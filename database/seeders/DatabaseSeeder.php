<?php

namespace Database\Seeders;

use App\Models\ContentItem;
use App\Models\Product;
use App\Support\SiteSchema;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate: re-running the seeder must never reset an existing account's password
        User::firstOrCreate(['email' => 'admin@sellbees.test'], ['name' => 'Sellbees Admin', 'password' => 'password', 'role' => 'admin']);
        User::firstOrCreate(['email' => 'user@sellbees.test'], ['name' => 'Demo User', 'password' => 'password', 'role' => 'user']);

        // landing page content (only on a fresh install, never overwrites admin edits)
        foreach (SiteSchema::seedItems() as $type => $rows) {
            if (ContentItem::where('type', $type)->exists()) {
                continue;
            }
            foreach ($rows as $i => $data) {
                ContentItem::create(['type' => $type, 'sort' => $i + 1, 'data' => $data]);
            }
        }

        if (Product::count() === 0) {
            foreach ([
                ['Wireless Bluetooth Headphones', 'Over-ear headphones with active noise cancelling and 30-hour battery life.', 89.99],
                ['Stainless Steel Water Bottle', 'Insulated 750ml bottle, keeps drinks cold 24h or hot 12h.', 24.5],
                ['Ergonomic Office Chair', 'Breathable mesh back, adjustable lumbar support and armrests.', 189],
                ['Smart Fitness Watch', 'Heart-rate, sleep and GPS tracking with a 7-day battery.', 59.9],
            ] as [$name, $desc, $price]) {
                Product::create(['name' => $name, 'description' => $desc, 'price' => $price]);
            }
        }
    }
}
