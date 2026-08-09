<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitDraft extends Model
{
    use HasFactory;

    protected $fillable = ['host_id', 'title', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array'];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
