<?php

namespace App\Models;

use App\Support\SiteContent;
use Illuminate\Database\Eloquent\Model;

class ContentItem extends Model
{
    protected $fillable = ['type', 'sort', 'is_active', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => SiteContent::flush());
        static::deleted(fn () => SiteContent::flush());
    }
}
