<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    protected $fillable = ['purchase_request_id', 'product_id', 'product_name', 'price', 'quantity'];

    protected function casts(): array
    {
        return ['price' => 'float'];
    }
}
