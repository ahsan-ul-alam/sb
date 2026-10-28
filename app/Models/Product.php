<?php

namespace App\Models;

use App\Support\SiteContent;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'description', 'price', 'image_path', 'is_active'];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'float'];
    }

    protected static function booted(): void
    {
        // the landing page shows featured products from a cached payload
        static::saved(fn () => SiteContent::flush());
        static::deleted(fn () => SiteContent::flush());
    }

    public function getImageUrlAttribute(): ?string
    {
        // relative on purpose: works on any host/port, unlike APP_URL-based disk URLs
        return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
    }
}
