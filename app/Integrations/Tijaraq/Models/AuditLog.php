<?php

namespace App\Integrations\Tijaraq\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $table = 'tijaraq_audit_logs';
    protected $guarded = ['id'];
    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];
}
