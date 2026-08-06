<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryMessage extends Model
{
    protected $fillable = ['sender_id', 'body', 'attachment_path', 'attachment_name', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function inquiry(): BelongsTo { return $this->belongsTo(Inquiry::class); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
}
