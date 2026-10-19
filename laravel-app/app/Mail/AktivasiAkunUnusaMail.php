<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link aktivasi akun jalur Mahasiswa UNUSA (bertanda tangan & berbatas waktu), dikirim ke email kampus.
 */
class AktivasiAkunUnusaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nama,
        public string $url,
        public int $berlakuMenit,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Aktivasi Akun SEMADIKSI - Mahasiswa UNUSA');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.aktivasi_unusa');
    }
}
