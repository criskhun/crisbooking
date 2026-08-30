<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\SystemBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnseenNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $recipient, public UserNotification $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alert->title.' — '.app(SystemBranding::class)->settings()->site_name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.unseen-notification');
    }
}
