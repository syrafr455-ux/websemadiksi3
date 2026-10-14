<?php

namespace App\Mail;

use App\Models\Kegiatan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SeatReselectionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Kegiatan $kegiatan,
        public string $recipientName,
        public ?string $deadlineWib = null,
        public ?string $reselectionUrl = null,
        public bool $isBooker = true
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isBooker
            ? 'Pemilihan Ulang Kursi: '.$this->kegiatan->nama_kegiatan.' (Perubahan Ruangan)'
            : 'Pemberitahuan Perubahan Ruangan: '.$this->kegiatan->nama_kegiatan;

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seat_reselection',
            with: [
                'kegiatan' => $this->kegiatan,
                'recipientName' => $this->recipientName,
                'deadlineWib' => $this->deadlineWib,
                'reselectionUrl' => $this->reselectionUrl,
                'isBooker' => $this->isBooker,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
