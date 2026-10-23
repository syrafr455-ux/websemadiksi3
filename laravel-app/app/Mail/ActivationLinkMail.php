<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link aktivasi akun mahasiswa UNUSA (bertanda tangan, sekali pakai), dikirim ke email kampus lewat antrean.
 */
class ActivationLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nama,
        public string $url,
        public int $berlakuMenit,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Link aktivasi akun SEMADIKSI');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.activation-link',
            text: 'emails.activation-link-text',
        );
    }
}
