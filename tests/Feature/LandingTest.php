<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        SiteContent::flush();
    }

    public function test_guest_sees_image_and_name_but_never_real_details(): void
    {
        Product::query()->delete();
        Product::create(['name' => 'Secret Gadget', 'description' => 'TOPSECRET-DESCRIPTION', 'price' => 1234.56]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Secret Gadget', $html);
        $this->assertStringContainsString('Members only', $html);
        $this->assertStringNotContainsString('TOPSECRET-DESCRIPTION', $html);
        $this->assertStringNotContainsString('1,234.56', $html);
        $this->assertStringNotContainsString('1234.56', $html);
    }

    public function test_member_sees_real_details_on_landing(): void
    {
        Product::query()->delete();
        Product::create(['name' => 'Secret Gadget', 'description' => 'TOPSECRET-DESCRIPTION', 'price' => 1234.56]);

        $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()->assertSee('TOPSECRET-DESCRIPTION')->assertSee('1,234.56');
    }

    public function test_seo_tags_schema_and_endpoints(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['<title>', 'name="description"', 'rel="canonical"', 'property="og:image"', 'name="twitter:card"', 'application/ld+json', '"@type":"FAQPage"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:');
        $this->get('/sitemap.xml')->assertOk()->assertSee('<urlset', false);
        $this->get('/og-image.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_admin_edits_appear_on_landing_and_users_cannot_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs(User::factory()->create())->postJson('/admin/site/settings/hero', [])->assertForbidden();

        $this->actingAs($admin)->postJson('/admin/site/settings/hero', ['hero_title' => 'Brand new *headline* here'])->assertOk();
        $this->actingAs($admin)->postJson('/admin/site/settings/seo', ['meta_title' => 'Custom SEO Title', 'robots' => 'noindex,nofollow'])->assertOk();
        $this->actingAs($admin)->postJson('/admin/site/items/faq', ['question' => 'Is this dynamic?', 'answer' => 'Yes it is.'])->assertOk();

        $this->get('/')->assertOk()
            ->assertSee('Custom SEO Title')->assertSee('noindex,nofollow')
            ->assertSee('Is this dynamic?')->assertSee('<span class="mark">headline</span>', false);
        $this->get('/robots.txt')->assertSee('Disallow: /');
    }

    public function test_admin_can_hide_any_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // marker text for each section => the switch that controls it
        $sections = [
            'show_services' => 'id="services"',
            'show_process' => 'id="process"',
            'show_store' => 'id="store"',
            'show_work' => 'id="work"',
            'show_faq' => 'id="faq"',
            'show_cta' => 'class="fin"',
            'show_stats' => 'class="wrap kp"',
            'show_industries' => 'class="marq"',
            'show_hero' => 'class="lp-hero"',
            'show_newsletter' => 'id="newsForm"',
            'show_testimonials' => 'class="ts"',
        ];

        $html = $this->get('/')->getContent();
        foreach ($sections as $marker) {
            $this->assertStringContainsString($marker, $html, "$marker should show by default");
        }

        // everything off (checkboxes that are not sent count as off)
        $this->actingAs($admin)->postJson('/admin/site/settings/visibility', [])->assertOk();
        $html = $this->get('/')->assertOk()->getContent();
        foreach ($sections as $marker) {
            $this->assertStringNotContainsString($marker, $html, "$marker should be hidden");
        }
        $this->assertStringNotContainsString('"@type":"FAQPage"', $html);
        $this->assertStringNotContainsString('"@type":"ItemList"', $html);

        // switching one back on shows only that one
        $this->actingAs($admin)->postJson('/admin/site/settings/visibility', ['show_faq' => '1'])->assertOk();
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('id="faq"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringNotContainsString('id="services"', $html);
    }

    public function test_theme_is_light_by_default_with_manual_dark_toggle_only(): void
    {
        foreach (['app', 'landing'] as $file) {
            $css = file_get_contents(public_path("css/$file.css"));
            $this->assertStringNotContainsString('prefers-color-scheme', $css, "$file.css must not follow the OS theme");
        }
        $this->assertStringContainsString('[data-theme="dark"]', file_get_contents(public_path('css/app.css')));

        $this->get('/')->assertOk()->assertSee('data-theme-toggle', false)->assertDontSee('data-theme="dark"', false);
        $this->get('/login')->assertOk()->assertSee('data-theme-toggle', false);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertSee('data-theme-toggle', false);
    }

    public function test_assets_are_versioned_relative_and_https_aware_in_production(): void
    {
        // every page links cache-busted, relative asset URLs (no http:// mixed content behind a proxy)
        $pages = [
            $this->get('/')->getContent(),
            $this->get('/login')->getContent(),
            $this->actingAs(User::factory()->create())->get('/dashboard')->getContent(),
        ];
        foreach ($pages as $html) {
            $this->assertMatchesRegularExpression('~href="/css/app\.css\?v=\d+"~', $html);
            $this->assertMatchesRegularExpression('~src="/js/theme\.js\?v=\d+"~', $html);
        }
        $this->assertMatchesRegularExpression('~href="/css/landing\.css\?v=\d+"~', $pages[0]);
        $this->assertMatchesRegularExpression('~src="/js/dashboard\.js\?v=\d+"~', $pages[2]);

        // behind an https proxy, generated absolute URLs (og:url, canonical, schema) must be https
        $html = $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'example.com', 'X-Forwarded-Port' => '443'])->getContent();
        $this->assertStringContainsString('property="og:url" content="https://example.com', $html);
        $this->assertStringNotContainsString('http://example.com', $html);

        // a static public/robots.txt would shadow the dynamic one (with the sitemap line)
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_landing_has_a_mobile_menu_and_viewport(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('name="viewport" content="width=device-width,initial-scale=1"', false)
            ->assertSee('id="menuBtn"', false)
            ->assertSee('id="navList"', false)
            ->assertSee('id="menuClose"', false)   // left drawer: close button
            ->assertSee('id="navOverlay"', false); // left drawer: dim overlay
    }

    public function test_newsletter_subscribe(): void
    {
        $this->postJson('/subscribe', ['email' => 'a@b.co'])->assertOk();
        $this->postJson('/subscribe', ['email' => 'nope'])->assertStatus(422);
        $this->assertDatabaseCount('subscribers', 1);
    }
}
