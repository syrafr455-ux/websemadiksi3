<?php

namespace App\Mail;

use App\Mail\Traits\ProtectedEmail;
use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KegiatanDiajukanMail extends Mailable implements ShouldQueue
{
    use ProtectedEmail, Queueable, SerializesModels;

    public function __construct(
        public Kegiatan $kegiatan,
        public User $penyetuju
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Permohonan Persetujuan Kegiatan: '.$this->kegiatan->nama_kegiatan,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.kegiatan_diajukan',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
