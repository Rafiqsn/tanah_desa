<?php
namespace App\Observers;

use App\Models\Bidang;

class BidangObserver
{
    public function created(Bidang $b){ optional($b->tanah)->recalcJumlahM2(); }
    public function updated(Bidang $b){ optional($b->tanah)->recalcJumlahM2(); }
    public function deleted(Bidang $b){ optional($b->tanah)->recalcJumlahM2(); }
    public function restored(Bidang $b){ optional($b->tanah)->recalcJumlahM2(); }
}
