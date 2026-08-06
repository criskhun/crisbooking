<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitImage extends Model
{
    use HasFactory;

    protected $fillable = ['path', 'sort_order'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
