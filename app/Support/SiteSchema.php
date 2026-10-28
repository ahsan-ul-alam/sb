<?php

namespace App\Support;

/**
 * Single source of truth for everything editable on the landing page.
 * The admin form builder, validation rules, defaults and seed data all read from here.
 */
class SiteSchema
{
    /** Inner SVG markup (24x24, stroke icons) selectable for services. */
    public const ICONS = [
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'star' => '<path d="M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/>',
        'monitor' => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'box' => '<path d="M21 8l-9-5-9 5 9 5z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4z"/>',
        'trend' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'cart' => '<path d="M6 6h15l-1.5 9H8z"/><path d="M6 6L5 3H2"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>',
        'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'heart' => '<path d="M12 21s-8-5.5-8-11a4.5 4.5 0 018-2.8A4.5 4.5 0 0120 10c0 5.5-8 11-8 11z"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
    ];

    private static function f(string $key, string $label, string $type = 'text', mixed $default = '', array $extra = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'default' => $default] + $extra;
    }

    /** Settings groups (flattened by key when read). */
    public static function groups(): array
    {
        $f = [self::class, 'f'];

        return [
            'visibility' => ['label' => 'Show / hide sections', 'hint' => 'Switch a section off to hide it from the landing page. Changes save instantly.', 'fields' => [
                $f('show_hero', 'Hero (top banner)', 'bool', true),
                $f('show_industries', 'Industries strip', 'bool', true),
                $f('show_stats', 'Stats / numbers', 'bool', true),
                $f('show_services', 'Services', 'bool', true),
                $f('show_process', 'Process steps', 'bool', true),
                $f('show_store', 'Store / products', 'bool', true),
                $f('show_work', 'Results / case studies', 'bool', true),
                $f('show_testimonials', 'Testimonials', 'bool', true),
                $f('show_faq', 'FAQ', 'bool', true),
                $f('show_cta', 'Final call to action', 'bool', true),
                $f('show_newsletter', 'Footer newsletter signup', 'bool', true),
            ]],
            'general' => ['label' => 'General & contact', 'fields' => [
                $f('brand_name', 'Brand name', 'text', 'Sellbees'),
                $f('brand_icon', 'Brand emoji / icon (used if no logo)', 'text', '🐝'),
                $f('logo', 'Logo image (optional)', 'image'),
                $f('tagline', 'Tagline', 'text', 'Build the brand. Own the market.'),
                $f('email', 'Contact email', 'text', 'hello@sellbees.com'),
                $f('phone', 'Phone', 'text', '+1 (555) 010-2030'),
                $f('address', 'Address', 'text', '12 Hive Street, Brand City'),
                $f('currency_code', 'Currency code (ISO)', 'text', 'USD'),
                $f('currency_symbol', 'Currency symbol', 'text', '$'),
            ]],
            'social' => ['label' => 'Social links', 'fields' => [
                $f('social_facebook', 'Facebook URL', 'text'),
                $f('social_instagram', 'Instagram URL', 'text'),
                $f('social_x', 'X / Twitter URL', 'text'),
                $f('social_linkedin', 'LinkedIn URL', 'text'),
                $f('social_youtube', 'YouTube URL', 'text'),
                $f('social_tiktok', 'TikTok URL', 'text'),
            ]],
            'seo' => ['label' => 'SEO & sharing', 'fields' => [
                $f('meta_title', 'Page title (50–60 chars)', 'text', 'Sellbees — Business-Brand Building Agency', ['max' => 70]),
                $f('meta_description', 'Meta description (120–160 chars)', 'textarea', 'Sellbees is a business-brand building agency: strategy, identity, websites, packaging and marketing that turn businesses into brands people remember.', ['max' => 300]),
                $f('meta_keywords', 'Keywords (comma separated)', 'text', 'brand agency, branding, business branding, packaging design, web design, marketing agency'),
                $f('og_title', 'Social share title (falls back to page title)', 'text'),
                $f('og_description', 'Social share description (falls back to meta description)', 'textarea'),
                $f('og_image', 'Social share image — 1200×630 (auto-generated if empty)', 'image'),
                $f('twitter_handle', 'Twitter/X handle (e.g. @sellbees)', 'text'),
                $f('canonical_url', 'Canonical URL (leave empty to use site URL)', 'text'),
                $f('robots', 'Search engine indexing', 'select', 'index,follow', ['options' => ['index,follow' => 'Allow indexing (recommended)', 'noindex,nofollow' => 'Hide from search engines']]),
                $f('theme_color', 'Browser theme color', 'color', '#f5a300'),
                $f('locale', 'Locale', 'text', 'en_US'),
                $f('google_verification', 'Google Search Console verification code', 'text'),
                $f('bing_verification', 'Bing Webmaster verification code', 'text'),
            ]],
            'hero' => ['label' => 'Hero', 'hint' => 'Wrap a word in *asterisks* to highlight it.', 'fields' => [
                $f('hero_badge', 'Top chip — badge', 'text', 'NEW'),
                $f('hero_chip', 'Top chip — text', 'text', 'Brand + product store, under one roof'),
                $f('hero_title', 'Headline', 'text', 'We turn businesses into *brands* that sell.'),
                $f('hero_lead', 'Lead paragraph', 'textarea', 'Sellbees is a business-brand building agency. Strategy, identity, websites and marketing — plus a product catalog you can order from in a few clicks.'),
                $f('hero_cta1', 'Primary button label', 'text', 'Browse products →'),
                $f('hero_cta2', 'Secondary button label', 'text', 'Explore services'),
                $f('hero_proof', 'Social proof text', 'text', 'Trusted by 240+ growing businesses'),
                $f('hero_chip_a_icon', 'Floating chip A — icon', 'text', '📈'),
                $f('hero_chip_a_value', 'Floating chip A — value', 'text', '+212%'),
                $f('hero_chip_a_label', 'Floating chip A — label', 'text', 'revenue in 90 days'),
                $f('hero_chip_b_icon', 'Floating chip B — icon', 'text', '🏆'),
                $f('hero_chip_b_value', 'Floating chip B — value', 'text', '240+'),
                $f('hero_chip_b_label', 'Floating chip B — label', 'text', 'brands launched'),
                $f('hero_board_title', 'Brand-board title', 'text', 'Brand board'),
                $f('hero_board_font', 'Brand-board font note', 'text', 'Display · Inter Bold'),
                $f('hero_board_voice', 'Brand-board voice note', 'text', 'Voice: confident, warm, direct.'),
            ]],
            'sections' => ['label' => 'Section headings', 'hint' => 'Wrap a word in *asterisks* to highlight it.', 'fields' => [
                $f('services_eyebrow', 'Services — eyebrow', 'text', 'What we do'),
                $f('services_title', 'Services — title', 'text', 'Everything a business needs to become a *brand*'),
                $f('services_sub', 'Services — subtitle', 'textarea', 'One team, one voice. From the idea on a napkin to the shelf, the screen and the feed.'),
                $f('process_eyebrow', 'Process — eyebrow', 'text', 'How it works'),
                $f('process_title', 'Process — title', 'text', 'From first call to brand launch in four moves'),
                $f('process_sub', 'Process — subtitle', 'textarea', 'A clear, fast process with no mystery and no surprise invoices.'),
                $f('store_eyebrow', 'Store — eyebrow', 'text', 'Our store'),
                $f('store_title', 'Store — title', 'text', 'Products you can request today'),
                $f('store_button', 'Store — button label', 'text', 'See full catalog →'),
                $f('store_count', 'Store — number of products shown', 'number', 4),
                $f('work_eyebrow', 'Results — eyebrow', 'text', 'Results'),
                $f('work_title', 'Results — title', 'text', 'Brands that grew *with* us'),
                $f('testimonials_eyebrow', 'Testimonials — eyebrow', 'text', 'Kind words'),
                $f('testimonials_title', 'Testimonials — title', 'text', "Don't take our word for it"),
                $f('faq_eyebrow', 'FAQ — eyebrow', 'text', 'FAQ'),
                $f('faq_title', 'FAQ — title', 'text', 'Questions, answered'),
                $f('faq_sub', 'FAQ — subtitle', 'textarea', "Can't find yours? Log in and message us through a request note."),
            ]],
            'cta' => ['label' => 'Final call to action', 'fields' => [
                $f('cta_title', 'Title', 'text', 'Ready to build a brand that sells?'),
                $f('cta_text', 'Text', 'textarea', 'Create your free account, browse the catalog and send your first request in under a minute.'),
                $f('cta_btn1', 'Primary button', 'text', 'Get started free →'),
                $f('cta_btn2', 'Secondary button', 'text', 'Our services'),
            ]],
            'footer' => ['label' => 'Footer', 'fields' => [
                $f('footer_about', 'About text', 'textarea', 'A business-brand building agency. We turn businesses into brands people remember.'),
                $f('footer_news_title', 'Newsletter title', 'text', 'Get brand-building tips in your inbox'),
                $f('footer_news_text', 'Newsletter text', 'text', 'One short email a month. No spam, unsubscribe anytime.'),
                $f('footer_copyright', 'Copyright text', 'text', 'All rights reserved.'),
            ]],
        ];
    }

    /** Repeatable item types. `title` is the field shown in admin lists. */
    public static function items(): array
    {
        $f = [self::class, 'f'];
        $icons = array_combine(array_keys(self::ICONS), array_map('ucfirst', array_keys(self::ICONS)));

        return [
            'industry' => ['label' => 'Industries strip', 'title' => 'name', 'fields' => [
                $f('name', 'Industry', 'text', '', ['required' => true]),
            ]],
            'stat' => ['label' => 'Stats', 'title' => 'label', 'fields' => [
                $f('value', 'Number', 'number', 0, ['required' => true]),
                $f('suffix', 'Suffix (+, %, ×)', 'text'),
                $f('decimals', 'Decimals', 'number', 0),
                $f('label', 'Label', 'text', '', ['required' => true]),
            ]],
            'service' => ['label' => 'Services', 'title' => 'title', 'fields' => [
                $f('title', 'Title', 'text', '', ['required' => true]),
                $f('description', 'Description', 'textarea'),
                $f('tags', 'Tags (comma separated)', 'text'),
                $f('icon', 'Icon', 'select', 'star', ['options' => $icons]),
                $f('style', 'Tile style', 'select', 'light', ['options' => ['light' => 'Light', 'dark' => 'Dark', 'accent' => 'Accent (yellow)']]),
                $f('span', 'Tile width', 'select', '2', ['options' => ['2' => 'Small', '3' => 'Medium', '4' => 'Large']]),
            ]],
            'step' => ['label' => 'Process steps', 'title' => 'title', 'fields' => [
                $f('title', 'Title', 'text', '', ['required' => true]),
                $f('description', 'Description', 'textarea'),
                $f('timing', 'Timing (e.g. Week 1)', 'text'),
            ]],
            'case' => ['label' => 'Case studies', 'title' => 'title', 'fields' => [
                $f('category', 'Category', 'text'),
                $f('title', 'Client / title', 'text', '', ['required' => true]),
                $f('description', 'Description', 'textarea'),
                $f('metric', 'Big metric (e.g. 2.1×)', 'text'),
                $f('metric_label', 'Metric label', 'text'),
            ]],
            'testimonial' => ['label' => 'Testimonials', 'title' => 'name', 'fields' => [
                $f('quote', 'Quote', 'textarea', '', ['required' => true]),
                $f('name', 'Name / company', 'text', '', ['required' => true]),
                $f('role', 'Role / subtitle', 'text'),
                $f('featured', 'Show as the large featured quote', 'bool', false),
            ]],
            'faq' => ['label' => 'FAQ', 'title' => 'question', 'fields' => [
                $f('question', 'Question', 'text', '', ['required' => true]),
                $f('answer', 'Answer', 'textarea', '', ['required' => true]),
            ]],
            'footer_link' => ['label' => 'Footer links', 'title' => 'label', 'fields' => [
                $f('column', 'Column heading', 'text', '', ['required' => true]),
                $f('label', 'Link label', 'text', '', ['required' => true]),
                $f('url', 'URL (e.g. #faq, /login or https://…)', 'text', '', ['required' => true]),
            ]],
        ];
    }

    /** Flat key => default map for settings. */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::groups() as $g) {
            foreach ($g['fields'] as $field) {
                $out[$field['key']] = $field['default'];
            }
        }

        return $out;
    }

    /** Initial content so the page looks complete on a fresh install. */
    public static function seedItems(): array
    {
        return [
            'industry' => array_map(fn ($n) => ['name' => $n], ['Retail', 'Food & Beverage', 'Fashion', 'Tech Startups', 'Health & Wellness', 'Home & Living', 'Local Services', 'E-commerce']),
            'stat' => [
                ['value' => 240, 'suffix' => '+', 'decimals' => 0, 'label' => 'Brands launched'],
                ['value' => 12, 'suffix' => '', 'decimals' => 0, 'label' => 'Industries served'],
                ['value' => 3.4, 'suffix' => '×', 'decimals' => 1, 'label' => 'Average revenue lift'],
                ['value' => 98, 'suffix' => '%', 'decimals' => 0, 'label' => 'Clients who come back'],
            ],
            'service' => [
                ['title' => 'Brand Strategy', 'description' => 'Positioning, audience research and a story your customers repeat for you. The foundation every other decision stands on.', 'tags' => 'Market research, Positioning, Naming, Brand voice', 'icon' => 'target', 'style' => 'dark', 'span' => '4'],
                ['title' => 'Identity & Design', 'description' => 'Logos, color, type and guidelines that keep every touchpoint consistent.', 'tags' => '', 'icon' => 'star', 'style' => 'light', 'span' => '2'],
                ['title' => 'Websites & Stores', 'description' => 'Fast, conversion-minded sites that make you look established from day one.', 'tags' => '', 'icon' => 'monitor', 'style' => 'light', 'span' => '2'],
                ['title' => 'Packaging', 'description' => 'Unboxing moments and labels that justify a premium price.', 'tags' => '', 'icon' => 'box', 'style' => 'accent', 'span' => '2'],
                ['title' => 'Social & Content', 'description' => 'Content systems and calendars so you show up daily without burnout.', 'tags' => '', 'icon' => 'chat', 'style' => 'light', 'span' => '2'],
                ['title' => 'Growth Marketing', 'description' => 'Campaigns, ads and funnels measured by what matters: sales.', 'tags' => 'Paid ads, Email, SEO, Analytics', 'icon' => 'trend', 'style' => 'light', 'span' => '3'],
                ['title' => 'Product Sourcing & Supply', 'description' => 'Need the goods too? Browse our catalog and send a purchase request — we handle the rest.', 'tags' => 'Curated catalog, Request in clicks, Live status updates', 'icon' => 'cart', 'style' => 'light', 'span' => '3'],
            ],
            'step' => [
                ['title' => 'Discover', 'description' => 'We dig into your business, customers and competitors.', 'timing' => 'Week 1'],
                ['title' => 'Define', 'description' => 'Positioning, voice and a brand direction you can feel.', 'timing' => 'Week 2'],
                ['title' => 'Design', 'description' => 'Identity, assets and channels built as one system.', 'timing' => 'Weeks 3–4'],
                ['title' => 'Deliver', 'description' => 'Launch, measure and grow with ongoing support.', 'timing' => 'Week 5+'],
            ],
            'case' => [
                ['category' => 'Home goods · Rebrand', 'title' => 'Hive & Home', 'description' => 'New identity, packaging and web store for a family-run label.', 'metric' => '2.1×', 'metric_label' => 'sales in one quarter'],
                ['category' => 'Specialty foods · Packaging', 'title' => 'Golden Jar', 'description' => 'Shelf-ready packaging that won three new retail partners.', 'metric' => '+3', 'metric_label' => 'retail partners signed'],
                ['category' => 'Local services · Launch', 'title' => 'FixRight', 'description' => 'Brand, site and ads that filled the calendar before opening day.', 'metric' => '400', 'metric_label' => 'leads in first 60 days'],
            ],
            'testimonial' => [
                ['quote' => 'Our sales doubled within a quarter of the rebrand. Customers finally get who we are — and so do we.', 'name' => 'Founder, Hive & Home', 'role' => 'Home goods label', 'featured' => true],
                ['quote' => 'Packaging and a site in three weeks. Retailers noticed immediately.', 'name' => 'Owner, Golden Jar', 'role' => '', 'featured' => false],
                ['quote' => "Professional, fast and honest. The best agency decision we've made.", 'name' => 'CEO, FixRight', 'role' => '', 'featured' => false],
            ],
            'faq' => [
                ['question' => 'What does a brand project cost?', 'answer' => 'Projects are scoped to your goals and stage. Most starter brand builds take 4–6 weeks and we agree a fixed price up front.'],
                ['question' => 'How do I order products?', 'answer' => 'Create a free account, browse the catalog, select what you want and send one purchase request. Our team reviews it and updates the status as it progresses.'],
                ['question' => 'Will I be notified about my request?', 'answer' => 'Yes. You get an in-dashboard notification every time the status of your request changes.'],
                ['question' => 'Can I cancel a request?', 'answer' => 'You can cancel any request while it is still pending, straight from your dashboard.'],
                ['question' => 'Do you work with early-stage businesses?', 'answer' => 'Absolutely — about half our clients start with just an idea and a budget. We scale the work to fit.'],
            ],
            'footer_link' => [
                ['column' => 'Agency', 'label' => 'Services', 'url' => '#services'],
                ['column' => 'Agency', 'label' => 'Process', 'url' => '#process'],
                ['column' => 'Agency', 'label' => 'Results', 'url' => '#work'],
                ['column' => 'Store', 'label' => 'Products', 'url' => '#store'],
                ['column' => 'Store', 'label' => 'Login', 'url' => '/login'],
                ['column' => 'Store', 'label' => 'Register', 'url' => '/login'],
                ['column' => 'Help', 'label' => 'FAQ', 'url' => '#faq'],
                ['column' => 'Help', 'label' => 'Contact', 'url' => '#contact'],
            ],
        ];
    }
}
