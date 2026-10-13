<?php

namespace App\Events;

use App\Models\Kegiatan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KegiatanUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Kegiatan $kegiatan;
    public array $changedAttributes;

    public function __construct(Kegiatan $kegiatan, array $changedAttributes = [])
    {
        $this->kegiatan = $kegiatan;
        $this->changedAttributes = $changedAttributes;
    }
}
