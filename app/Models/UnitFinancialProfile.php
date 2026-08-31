<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitFinancialProfile extends Model
{
    protected $fillable = [
        'unit_id', 'management_type', 'owner_name', 'owner_share_percentage',
        'manager_share_percentage', 'share_basis', 'initial_asset_value',
    ];

    protected function casts(): array
    {
        return [
            'owner_share_percentage' => 'decimal:2',
            'manager_share_percentage' => 'decimal:2',
            'initial_asset_value' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
