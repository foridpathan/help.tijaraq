<?php

namespace App\Integrations\Tijaraq\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    public const PENDING = 'pending';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';

    protected $table = 'tijaraq_webhook_deliveries';
    protected $guarded = ['id'];
    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'delivered_at' => 'datetime',
    ];
}
