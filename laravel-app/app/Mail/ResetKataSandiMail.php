<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link atur ulang kata sandi (berbatas waktu), dikirim ke email yang tercatat di akun.
 */
class ResetKataSandiMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nama,
        public string $url,
        public int $berlakuMenit,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Atur Ulang Kata Sandi SEMADIKSI');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reset_kata_sandi');
    }
}
