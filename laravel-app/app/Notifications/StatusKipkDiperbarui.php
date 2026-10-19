<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan perubahan status penerima KIP Kuliah pada akun. Tidak memuat nomor pendaftaran.
 */
class StatusKipkDiperbarui extends Notification
{
    use Queueable;

    public function __construct(private readonly bool $penerima, private readonly string $keterangan) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->penerima ? 'Status KIP Kuliah Anda telah dikonfirmasi' : 'Status KIP Kuliah Anda diperbarui')
            ->greeting("Halo, {$notifiable->name}")
            ->line($this->keterangan)
            ->action('Buka Dashboard SEMADIKSI', route('dashboard.index'))
            ->line('Bila Anda merasa tidak melakukan perubahan ini, hubungi admin SEMADIKSI.');
    }
}
