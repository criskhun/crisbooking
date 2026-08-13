<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryPriceProposal extends Model
{
    use HasFactory;

    protected $fillable = ['inquiry_id', 'proposed_by', 'amount', 'note', 'status', 'responded_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'responded_at' => 'datetime'];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }
}
