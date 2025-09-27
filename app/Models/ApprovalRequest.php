<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    protected $table = 'approval_requests';

    protected $fillable = [
        'module',
        'action',
        'target_id',
        'payload',
        'submitted_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'applied_at',
        'apply_error',
    ];

    protected $casts = [
        'payload'      => 'array',
        'reviewed_at'  => 'datetime',
        'applied_at'   => 'datetime',
    ];

    // (opsional) konstanta biar rapi saat dipakai di service/controller
    public const MODULE_TANAH  = 'tanah';
    public const MODULE_WARGA  = 'warga';
    public const MODULE_GEO    = 'geojson';

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** Pengaju (staff) */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** Reviewer (kepala) */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Scopes bantu */
    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeModule($q, string $module)
    {
        return $q->where('module', $module);
    }

    public function scopeAction($q, string $action)
    {
        return $q->where('action', $action);
    }

    public function scopeSubmittedBy($q, int $userId)
    {
        return $q->where('submitted_by', $userId);
    }

    /** Helper status */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
