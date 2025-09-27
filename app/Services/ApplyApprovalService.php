<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Tanah;
use App\Models\Warga;
use App\Models\Geojson;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApplyApprovalService
{
    public function approveAndApply(ApprovalRequest $ar, $approver, ?string $note = null)
    {
        DB::beginTransaction();
        try {
            $this->apply($ar); // terapkan ke tabel utama

            $ar->update([
                'status'      => 'approved',
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'applied_at'  => now(),
                'apply_error' => null,
            ]);

            // TODO: catat audit log di sini

            DB::commit();
            return response()->json(['message' => 'Approved & applied']);
        } catch (Throwable $e) {
            DB::rollBack();
            $ar->update([
                'status'      => 'rejected',
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
                'review_note' => 'Auto-reject: apply failed',
                'apply_error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Apply gagal, proposal ditolak',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function apply(ApprovalRequest $ar): void
    {
        match ($ar->module) {
            'tanah'  => $this->applyTanah($ar),
            'warga'  => $this->applyWarga($ar),
            'geojson'=> $this->applyGeo($ar),
            default  => throw new \InvalidArgumentException('Unknown module'),
        };
    }

    private function applyTanah(ApprovalRequest $ar): void
    {
        if ($ar->action === 'create') {
            $payload = $ar->payload;
            $geo     = $payload['geojson_boundary'] ?? null;
            unset($payload['geojson_boundary']);

            $tanah = Tanah::create($payload);

            if ($geo) {
                Geojson::create([
                    'tanah_id' => $tanah->id,
                    'feature'  => $geo, // simpan GeoJSON utuh
                ]);
            }

            $ar->target_id = $tanah->id;
            $ar->save();

        } elseif ($ar->action === 'update') {
            $tanah = Tanah::findOrFail($ar->target_id);
            $tanah->fill($ar->payload)->save();

        } elseif ($ar->action === 'delete') {
            $tanah = Tanah::findOrFail($ar->target_id);
            $tanah->delete(); // pakai SoftDeletes jika ingin diarsip
        }
    }

    private function applyWarga(ApprovalRequest $ar): void
    {
        if ($ar->action === 'create') {
            $w = Warga::create($ar->payload);
            $ar->target_id = $w->id;
            $ar->save();

        } elseif ($ar->action === 'update') {
            $w = Warga::findOrFail($ar->target_id);
            $w->fill($ar->payload)->save();

        } elseif ($ar->action === 'delete') {
            $w = Warga::findOrFail($ar->target_id);
            $w->delete();
        }
    }

    private function applyGeo(ApprovalRequest $ar): void
    {
        // contoh bila ada proposal khusus untuk boundary
        if ($ar->action === 'update') {
            $g = Geojson::where('tanah_id', $ar->target_id)->firstOrFail();
            $g->feature = $ar->payload['feature'];
            $g->save();
        }
    }
}
