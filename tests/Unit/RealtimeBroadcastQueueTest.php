<?php

namespace Tests\Unit;

use App\Events\DisplayUpdated;
use App\Events\EssayDinilai;
use App\Events\PesanPengajar;
use App\Events\PesertaDikeluarkan;
use App\Events\PesertaDipaksaKumpul;
use App\Events\PesertaPeringatan;
use App\Events\PesertaStatusLogin;
use App\Events\PesertaWaktuDitambah;
use App\Events\SesiSelesai;
use App\Events\SesiStatusUpdated;
use App\Events\SessionMonitorUpdated;
use App\Events\SoalDibuka;
use App\Events\SoalDitutup;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use ReflectionClass;
use Tests\TestCase;

class RealtimeBroadcastQueueTest extends TestCase
{
    public function test_semua_event_ujian_menggunakan_queue_realtime(): void
    {
        $events = [
            DisplayUpdated::class,
            EssayDinilai::class,
            PesanPengajar::class,
            PesertaDikeluarkan::class,
            PesertaDipaksaKumpul::class,
            PesertaPeringatan::class,
            PesertaStatusLogin::class,
            PesertaWaktuDitambah::class,
            SesiSelesai::class,
            SesiStatusUpdated::class,
            SessionMonitorUpdated::class,
            SoalDibuka::class,
            SoalDitutup::class,
        ];

        foreach ($events as $eventClass) {
            $event = (new ReflectionClass($eventClass))->newInstanceWithoutConstructor();

            $this->assertInstanceOf(ShouldBroadcast::class, $event, $eventClass);
            $this->assertNotInstanceOf(ShouldBroadcastNow::class, $event, $eventClass);
            $this->assertSame('realtime', $event->broadcastQueue(), $eventClass);
        }
    }
}
