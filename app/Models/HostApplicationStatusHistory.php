<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostApplicationStatusHistory extends Model
{
    protected $fillable = [
        'host_application_id',
        'actor_id',
        'from_status',
        'to_status',
        'note',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(HostApplication::class, 'host_application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
