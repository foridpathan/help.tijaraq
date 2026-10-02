<?php

namespace App\Integrations\Tijaraq\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public $timestamps = false;
    protected $table = 'tijaraq_idempotency_keys';
    protected $guarded = ['id'];
    protected $casts = [
        'response_body' => 'array',
        'response_status' => 'integer',
        'created_at' => 'datetime',
    ];
}
