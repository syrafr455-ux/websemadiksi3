<?php

namespace App\Mail;

use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KegiatanUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Kegiatan $kegiatan;
    public User $user;
    public array $changedAttributes;

    public function __construct(Kegiatan $kegiatan, User $user, array $changedAttributes = [])
    {
        $this->kegiatan = $kegiatan;
        $this->user = $user;
        $this->changedAttributes = $changedAttributes;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pembaruan Jadwal / Lokasi Kegiatan: '.$this->kegiatan->nama_kegiatan,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.kegiatan_updated',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
