<?php

namespace App\Listeners;

use App\Events\KegiatanUpdatedEvent;
use App\Mail\KegiatanUpdatedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendKegiatanUpdatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(KegiatanUpdatedEvent $event): void
    {
        $kegiatan = $event->kegiatan;
        $registrations = $kegiatan->registrations()->with('participantUser')->get();

        $sentEmails = [];
        foreach ($registrations as $reg) {
            $user = $reg->participantUser;
            if (! $user || ! $user->email) {
                continue;
            }

            if (in_array($user->email, $sentEmails, true)) {
                continue;
            }

            $sentEmails[] = $user->email;
            try {
                Mail::to($user->email)->send(new KegiatanUpdatedMail($kegiatan, $user, $event->changedAttributes));
            } catch (\Throwable $e) {
                // Log and continue
                logger()->error('Gagal mengirim email pembaruan kegiatan: '.$e->getMessage());
            }
        }
    }
}
