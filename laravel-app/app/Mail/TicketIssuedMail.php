<?php

namespace App\Mail;

use App\Models\KegiatanRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public KegiatanRegistration $registration;

    public function __construct(KegiatanRegistration $registration)
    {
        $this->registration = $registration->loadMissing(['kegiatan', 'seat', 'participantUser', 'bookerUser']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'E-Tiket Resmi SEMADIKSI: '.$this->registration->nomor_tiket.' - '.$this->registration->kegiatan->nama_kegiatan,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket_issued',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
