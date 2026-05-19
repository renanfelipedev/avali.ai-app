<?php

namespace App\Mail;

use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendanceReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $pdfContent;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public AttendanceSession $session,
        string $pdfContent
    ) {
        $this->pdfContent = base64_encode($pdfContent);
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lista de Presença Finalizada - Turma: '.$this->session->class_name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.attendance-report',
            with: [
                'session' => $this->session,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $safeClassName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>', ' '], '_', $this->session->class_name);

        return [
            Attachment::fromData(
                fn () => base64_decode($this->pdfContent),
                "chamada_{$safeClassName}_".$this->session->created_at->format('Y-m-d').'.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
