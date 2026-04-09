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

class ImportSummaryEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;

    public string $htmlContent;

    public string $trackingPixelUrl;

    public function __construct(public Notification $notification, public User $user)
    {
        $this->subjectLine = (string) ($notification->data['subject'] ?? 'Import Summary');
        $this->htmlContent = (string) ($notification->data['html_message'] ?? '');
        $this->trackingPixelUrl = URL::temporarySignedRoute(
            'notifications.email-open',
            now()->addDays(30),
            [
                'notification' => $notification->id,
                'user' => $user->id,
            ]
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.import-summary',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
