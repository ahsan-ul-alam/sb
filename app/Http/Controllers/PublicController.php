<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Support\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicController extends Controller
{
    public function home()
    {
        $data = SiteContent::landing();
        $data['member'] = auth()->check();

        if ($data['member']) {
            $data['products'] = SiteContent::memberProducts(max(1, min(24, (int) $data['s']['store_count'])));
            $data['selected'] = \App\Models\Selection::where('user_id', auth()->id())->pluck('quantity', 'product_id')->all();
        }

        return view('landing', $data);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        Subscriber::firstOrCreate(['email' => strtolower($data['email'])]);

        return response()->json(['message' => 'Thanks! You are subscribed.']);
    }

    public function sitemap(): Response
    {
        $s = SiteContent::settings();
        $lastmod = now()->toAtomString();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .'<url><loc>'.e(url('/')).'</loc><lastmod>'.$lastmod.'</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>'
            .'</urlset>';

        return response($s['robots'] === 'noindex,nofollow' ? '<?xml version="1.0"?><urlset/>' : $xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $s = SiteContent::settings();
        $body = $s['robots'] === 'noindex,nofollow'
            ? "User-agent: *\nDisallow: /\n"
            : "User-agent: *\nAllow: /\nDisallow: /dashboard\nDisallow: /admin\nDisallow: /shop\nDisallow: /notifications\nDisallow: /login\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    public function ogImage()
    {
        $file = SiteContent::renderDefaultOg(SiteContent::settings());

        return response()->file($file, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }
}
