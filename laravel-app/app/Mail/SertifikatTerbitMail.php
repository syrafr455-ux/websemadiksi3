<?php

namespace App\Mail;

use App\Models\Sertifikat;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SertifikatTerbitMail extends Mailable
{
    use Queueable, SerializesModels;

    public Sertifikat $sertifikat;

    public bool $isCritical = false;

    public function __construct(Sertifikat $sertifikat)
    {
        $this->sertifikat = $sertifikat->loadMissing(['kegiatan', 'user']);
    }

    public function envelope(): Envelope
    {
        $namaKegiatan = $this->sertifikat->kegiatan?->nama_kegiatan ?? 'Kegiatan SEMADIKSI';
        $nomor = $this->sertifikat->nomor_sertifikat;

        return new Envelope(
            subject: "Sertifikat Resmi Terbit: {$nomor} - {$namaKegiatan}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sertifikat_terbit',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
