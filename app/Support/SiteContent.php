<?php

namespace App\Support;

use App\Models\ContentItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class SiteContent
{
    private const KEY = 'site.landing.v1';

    public static function flush(): void
    {
        Cache::forget(self::KEY);
        Cache::forget('site.brand');
        foreach (range(1, 24) as $n) {
            Cache::forget('site.products.full.'.$n);
        }
        @unlink(storage_path('app/og-default.png'));
    }

    /** Whole landing payload in one cache hit: settings + items + featured products. */
    public static function landing(): array
    {
        return Cache::rememberForever(self::KEY, function () {
            $s = self::settings();

            $items = ContentItem::where('is_active', true)->orderBy('sort')->orderBy('id')->get()
                ->groupBy('type')
                ->map(fn ($g) => $g->map(fn ($i) => $i->data)->values()->all())
                ->all();

            // Public payload: real price/description are never included for guests,
            // only scrambled placeholders that the page renders blurred.
            $products = Product::where('is_active', true)->latest()->take(max(1, (int) $s['store_count']))->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'image_url' => $p->image_url,
                    'locked_price' => self::scramble(6),
                    'locked_desc' => self::scramble(88),
                ])->all();

            return ['s' => $s, 'items' => $items, 'products' => $products];
        });
    }

    /** Full product details for signed-in members (cached separately). */
    public static function memberProducts(int $limit): array
    {
        return Cache::rememberForever('site.products.full.'.$limit, fn () => Product::where('is_active', true)->latest()->take($limit)->get()->toArray());
    }

    /** Random-looking text of a given length, with spaces so it wraps like prose. */
    private static function scramble(int $len): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789#%&@';
        $out = '';
        while (strlen($out) < $len) {
            $out .= substr(str_shuffle($chars), 0, random_int(3, 8)).' ';
        }

        return rtrim(substr($out, 0, $len));
    }

    /** Flat settings merged over schema defaults, with resolved image URLs. */
    public static function settings(): array
    {
        $stored = Setting::all()->flatMap(fn ($row) => $row->value)->all();
        $s = array_replace(SiteSchema::defaults(), array_filter($stored, fn ($v) => $v !== null));

        foreach (['logo', 'og_image'] as $img) {
            // relative path (cache-safe across hosts); absolutised with url() where needed (OG tags, schema)
            $s[$img.'_url'] = $s[$img] ? '/storage/'.ltrim($s[$img], '/') : null;
        }

        return $s;
    }

    /** Tiny cached subset used by auth/dashboard screens. */
    public static function brand(): array
    {
        return Cache::rememberForever('site.brand', function () {
            $s = self::settings();

            return ['name' => $s['brand_name'], 'icon' => $s['brand_icon'], 'logo' => $s['logo_url'], 'currency' => $s['currency_symbol'], 'theme' => $s['theme_color']];
        });
    }

    /** Escape, then turn *word* into highlighted markup. */
    public static function emph(?string $text, string $open = '<em>', string $close = '</em>'): HtmlString
    {
        $safe = e($text ?? '');

        return new HtmlString(preg_replace('/\*(.+?)\*/u', $open.'$1'.$close, $safe));
    }

    public static function plain(?string $text): string
    {
        return str_replace('*', '', $text ?? '');
    }

    public static function ogImage(array $s): string
    {
        return url($s['og_image_url'] ?: '/og-image.png');
    }

    /** schema.org graph: Organization/ProfessionalService, WebSite, WebPage, Products, FAQ. */
    public static function jsonLd(array $data): string
    {
        ['s' => $s, 'items' => $items, 'products' => $products] = $data;
        $home = url('/');
        $logo = $s['logo_url'] ? url($s['logo_url']) : self::ogImage($s);

        $sameAs = array_values(array_filter(array_map(fn ($k) => $s[$k] ?? null, [
            'social_facebook', 'social_instagram', 'social_x', 'social_linkedin', 'social_youtube', 'social_tiktok',
        ])));

        $org = array_filter([
            '@type' => ['Organization', 'ProfessionalService'],
            '@id' => $home.'#organization',
            'name' => $s['brand_name'],
            'url' => $home,
            'logo' => ['@type' => 'ImageObject', 'url' => $logo],
            'image' => self::ogImage($s),
            'description' => $s['meta_description'],
            'slogan' => $s['tagline'],
            'email' => $s['email'],
            'telephone' => $s['phone'],
            'address' => $s['address'] ? ['@type' => 'PostalAddress', 'streetAddress' => $s['address']] : null,
            'sameAs' => $sameAs ?: null,
            'contactPoint' => ($s['email'] || $s['phone']) ? array_filter(['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => $s['email'], 'telephone' => $s['phone']]) : null,
            'hasOfferCatalog' => ! empty($items['service']) ? [
                '@type' => 'OfferCatalog',
                'name' => $s['brand_name'].' services',
                'itemListElement' => array_map(fn ($sv) => [
                    '@type' => 'Offer',
                    'itemOffered' => ['@type' => 'Service', 'name' => $sv['title'], 'description' => $sv['description'] ?? ''],
                ], $items['service']),
            ] : null,
        ]);

        $graph = [
            $org,
            [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'url' => $home,
                'name' => $s['brand_name'],
                'description' => $s['meta_description'],
                'inLanguage' => str_replace('_', '-', $s['locale']),
                'publisher' => ['@id' => $home.'#organization'],
            ],
            [
                '@type' => 'WebPage',
                '@id' => $home.'#webpage',
                'url' => $home,
                'name' => self::plain($s['meta_title']),
                'description' => $s['meta_description'],
                'isPartOf' => ['@id' => $home.'#website'],
                'about' => ['@id' => $home.'#organization'],
                'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => self::ogImage($s)],
            ],
        ];

        if ($products) {
            $graph[] = [
                '@type' => 'ItemList',
                'name' => $s['brand_name'].' products',
                'itemListElement' => array_map(fn ($p, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    // members-only details (price, description) are intentionally not exposed here
                    'item' => array_filter([
                        '@type' => 'Product',
                        'name' => $p['name'],
                        'image' => $p['image_url'] ? url($p['image_url']) : null,
                        'brand' => ['@type' => 'Brand', 'name' => $s['brand_name']],
                        'url' => $home.'#store',
                    ]),
                ], $products, array_keys($products)),
            ];
        }

        if (! empty($items['faq'])) {
            $graph[] = [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($q) => [
                    '@type' => 'Question',
                    'name' => $q['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['answer']],
                ], $items['faq']),
            ];
        }

        return json_encode(['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /** Render a 1200x630 PNG with GD when no OG image was uploaded. Cached on disk. */
    public static function renderDefaultOg(array $s): string
    {
        $file = storage_path('app/og-default.png');
        if (is_file($file)) {
            return $file;
        }

        $im = imagecreatetruecolor(1200, 630);
        $dark = imagecolorallocate($im, 23, 19, 12);
        $amber = imagecolorallocate($im, 245, 163, 0);
        $cream = imagecolorallocate($im, 255, 241, 204);
        $muted = imagecolorallocate($im, 216, 205, 180);
        imagefill($im, 0, 0, $dark);

        // hexagon accent, top right
        $hex = [];
        foreach (range(0, 5) as $i) {
            $a = deg2rad(60 * $i - 30);
            $hex[] = (int) (980 + 300 * cos($a));
            $hex[] = (int) (140 + 300 * sin($a));
        }
        imagefilledpolygon($im, $hex, $amber);
        imagefilledrectangle($im, 0, 622, 1200, 630, $amber);

        $bold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $reg = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $title = self::plain($s['hero_title']);

        if (function_exists('imagettftext') && is_file($bold)) {
            imagettftext($im, 30, 0, 80, 110, $amber, $bold, mb_strtoupper($s['brand_name']));
            $y = 250;
            foreach (explode("\n", wordwrap($title, 24, "\n", true)) as $line) {
                imagettftext($im, 54, 0, 80, $y, $cream, $bold, $line);
                $y += 76;
            }
            imagettftext($im, 26, 0, 80, 560, $muted, is_file($reg) ? $reg : $bold, $s['tagline']);
        } else {
            imagestring($im, 5, 80, 80, $s['brand_name'], $amber);
            imagestring($im, 5, 80, 300, $title, $cream);
        }

        imagepng($im, $file, 6);
        imagedestroy($im);

        return $file;
    }
}
