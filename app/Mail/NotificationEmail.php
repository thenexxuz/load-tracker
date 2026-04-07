<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class NotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;

    public string $messageBody;

    public string $trackingPixelUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(public Notification $notification, public User $user)
    {
        $this->subjectLine = (string) ($notification->data['subject'] ?? 'Notification');
        $this->messageBody = (string) ($notification->data['message'] ?? '');
        $this->trackingPixelUrl = URL::temporarySignedRoute(
            'notifications.email-open',
            now()->addDays(30),
            [
                'notification' => $notification->id,
                'user' => $user->id,
            ]
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
