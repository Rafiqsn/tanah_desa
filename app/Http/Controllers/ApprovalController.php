<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\ApplyApprovalService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    // GET /api/approvals?module=tanah&action=create
    public function index(Request $r)
    {
        $q = ApprovalRequest::where('status', 'pending');
        if ($r->filled('module')) $q->where('module', $r->input('module'));
        if ($r->filled('action')) $q->where('action', $r->input('action'));

        return response()->json($q->orderBy('id')->paginate(20));
    }

    // POST /api/approvals/{id}/approve
    public function approve(Request $r, $id, ApplyApprovalService $svc)
    {
        $ar = ApprovalRequest::findOrFail($id);
        if ($ar->status !== 'pending') {
            return response()->json(['message' => 'Sudah diproses'], 409);
        }

        return $svc->approveAndApply($ar, $r->user(), $r->input('note'));
    }

    // POST /api/approvals/{id}/reject
    public function reject(Request $r, $id)
    {
        $ar = ApprovalRequest::findOrFail($id);
        if ($ar->status !== 'pending') {
            return response()->json(['message' => 'Sudah diproses'], 409);
        }

        $ar->update([
            'status'      => 'rejected',
            'reviewed_by' => $r->user()->id,
            'reviewed_at' => now(),
            'review_note' => $r->input('note'),
        ]);

        return response()->json(['message' => 'Proposal ditolak']);
    }
}
