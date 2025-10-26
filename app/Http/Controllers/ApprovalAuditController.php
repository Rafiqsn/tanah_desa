<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class ApprovalAuditController extends Controller
{
    /**
     * GET /api/audit/approvals
     * Query (opsional):
     * status, module, action, submitted_by, reviewed_by(=ID|'me'),
     * month, year  ATAU  date_from, date_to, q, per_page, include_payload
     */
    public function index(Request $r)
    {
        // Base query + relasi user (nama & email)
        $base = ApprovalRequest::query()
            ->whereIn('status', ['approved', 'rejected'])
            ->with(['submitter:id,name,email', 'reviewer:id,name,email']);

        $this->applyFilters($base, $r);

        // ----- LIST (paginate) -----
        $perPage = max(1, min((int) $r->input('per_page', 12), 100));
        $listQ   = (clone $base)->orderByDesc('reviewed_at')->orderByDesc('id');
        $p       = $listQ->paginate($perPage);

        $includePayload = $r->boolean('include_payload', false);
        $rows = $p->getCollection()->values()->map(function (ApprovalRequest $a, $i) use ($p, $includePayload) {
            $labelMap = ['create' => 'Tambah', 'update' => 'Edit', 'delete' => 'Hapus'];

            return [
                'no'              => $p->firstItem() + $i,
                'id'              => $a->id,
                'module'          => $a->module,
                'action'          => $a->action,
                'jenis_perubahan' => $labelMap[$a->action] ?? strtoupper($a->action),
                'status'          => $a->status,
                'target_id'       => $a->target_id,
                'review_note'     => $a->review_note,
                'submitted_at'    => optional($a->created_at)?->toIso8601String(),
                'reviewed_at'     => optional($a->reviewed_at)?->toIso8601String(),
                'submitted_by'    => [
                    'id'    => $a->submitted_by,
                    'name'  => optional($a->submitter)->name,
                    'email' => optional($a->submitter)->email,
                ],
                'reviewed_by'     => [
                    'id'    => $a->reviewed_by,
                    'name'  => optional($a->reviewer)->name,
                    'email' => optional($a->reviewer)->email,
                ],
                'payload'         => $includePayload ? $a->payload : null,
            ];
        });

        // ----- STATS (untuk header ringkasan & grafik) -----
        $stats   = $this->computeStats($base);
        $summary = [
            'total'  => $stats['total'],
            'create' => $stats['by_action']['create'] ?? 0,
            'update' => $stats['by_action']['update'] ?? 0,
            'delete' => $stats['by_action']['delete'] ?? 0,
        ];

        return response()->json([
            'filters'    => [
                'module' => $r->input('module'),
                'action' => $r->input('action'),
                'status' => $r->input('status'),
                'month'  => $r->input('month'),
                'year'   => $r->input('year'),
                'date_from' => $r->input('date_from'),
                'date_to'   => $r->input('date_to'),
                'q'      => $r->input('q'),
            ],
            'summary'    => $summary,        // cocok untuk kalimat "Total X perubahan ..."
            'pagination' => [
                'current_page' => $p->currentPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'last_page'    => $p->lastPage(),
                'from'         => $p->firstItem(),
                'to'           => $p->lastItem(),
            ],
            'data'       => $rows,           // tabel
            'stats'      => $stats,          // untuk chart (by_action, by_status, per_day, dst)
        ]);
    }

    /** Terapkan semua filter yang sama di list & stats */
    private function applyFilters(Builder $q, Request $r): void
    {
        if ($r->filled('status')) {
            $statuses = collect(explode(',', $r->input('status')))
                ->map(fn ($s) => strtolower(trim($s)))
                ->intersect(['approved','rejected'])
                ->all();
            if ($statuses) $q->whereIn('status', $statuses);
        }

        if ($r->filled('module'))       $q->where('module', $r->input('module'));
        if ($r->filled('action'))       $q->where('action', $r->input('action'));
        if ($r->filled('submitted_by')) $q->where('submitted_by', (int) $r->input('submitted_by'));

        if ($r->filled('reviewed_by')) {
            $rb = $r->input('reviewed_by');
            $q->where('reviewed_by', $rb === 'me' ? $r->user()->id : (int) $rb);
        }

        // Periode bulan/tahun (seperti UI) atau rentang bebas
        if ($r->filled('month') && $r->filled('year')) {
            $q->whereYear('reviewed_at', (int) $r->input('year'))
              ->whereMonth('reviewed_at', (int) $r->input('month'));
        } else {
            if ($r->filled('date_from')) $q->whereDate('reviewed_at', '>=', $r->input('date_from'));
            if ($r->filled('date_to'))   $q->whereDate('reviewed_at', '<=', $r->input('date_to'));
        }

        if ($r->filled('q')) {
            $term = '%'.$r->input('q').'%';
            $q->where(function ($w) use ($term) {
                $w->where('module', 'like', $term)
                  ->orWhere('action', 'like', $term)
                  ->orWhere('review_note', 'like', $term);
            });
        }
    }

    /** Hitung statistik agregat untuk dashboard */
    private function computeStats(Builder $base): array
    {
        $total = (clone $base)->count();

        $byAction = (clone $base)
            ->select('action', DB::raw('COUNT(*) as total'))
            ->groupBy('action')->pluck('total','action')->toArray();

        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total','status')->toArray();

        // Reviewer (dengan nama)
        $byReviewer = (clone $base)
            ->leftJoin('users as ru', 'ru.id', '=', 'approval_requests.reviewed_by')
            ->select('approval_requests.reviewed_by as id', 'ru.name', DB::raw('COUNT(*) as total'))
            ->groupBy('approval_requests.reviewed_by', 'ru.name')
            ->orderByDesc('total')
            ->get();

        // Per hari (untuk grafik area/bar)
        $perDay = (clone $base)
            ->select(DB::raw('DATE(reviewed_at) as day'), 'status', DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw('DATE(reviewed_at)'), 'status')
            ->orderBy('day', 'asc')
            ->get();

        return [
            'total'       => $total,
            'by_action'   => $byAction,   // ['create'=>x,'update'=>y,'delete'=>z]
            'by_status'   => $byStatus,   // ['approved'=>a,'rejected'=>b]
            'by_reviewer' => $byReviewer, // [{id,name,total}, ...]
            'per_day'     => $perDay,     // [{day, status, total}, ...]
        ];
    }

    public function show(Request $r, $id)
    {
        $a = ApprovalRequest::whereIn('status', ['approved', 'rejected'])
            ->with(['submitter:id,name,email', 'reviewer:id,name,email'])
            ->findOrFail($id);

        $labelMap = ['create' => 'Tambah', 'update' => 'Edit', 'delete' => 'Hapus'];

        return response()->json([
            'id'               => $a->id,
            'module'           => $a->module,
            'action'           => $a->action,
            'jenis_perubahan'  => $labelMap[$a->action] ?? strtoupper($a->action),
            'status'           => $a->status,
            'target_id'        => $a->target_id,
            'payload'          => $r->boolean('include_payload', false) ? $a->payload : null,
            'review_note'      => $a->review_note,
            'submitted_at'     => optional($a->created_at)?->toIso8601String(),
            'reviewed_at'      => optional($a->reviewed_at)?->toIso8601String(),
            'submitted_by'     => [
                'id'    => $a->submitted_by,
                'name'  => optional($a->submitter)->name,
                'email' => optional($a->submitter)->email,
            ],
            'reviewed_by'      => [
                'id'    => $a->reviewed_by,
                'name'  => optional($a->reviewer)->name,
                'email' => optional($a->reviewer)->email,
            ],
        ]);
    }
}
