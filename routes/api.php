<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TanahReadController;
use App\Http\Controllers\WargaReadController;
use App\Http\Controllers\StaffProposalController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\PublicInfografisController;
use App\Http\Controllers\ApprovalAuditController;
use App\Http\Controllers\MapBoundariesController;

Route::post('/login', [AuthController::class, 'login'])->name('login');



Route::get('/public/infografis/summary', [PublicInfografisController::class, 'summary'])
     ->middleware('throttle:30,1');
Route::get('/public/map', [MapBoundariesController::class, 'bidang']);



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/tanah',      [TanahReadController::class, 'index']);
    Route::get('/tanah/{id}', [TanahReadController::class, 'show']);
    Route::get('/warga',      [WargaReadController::class, 'index']);
    Route::get('/warga/{id}', [WargaReadController::class, 'show']);

    Route::middleware(['role:kepala'])->prefix('kepala')->group(function () {
        Route::get ('/approvals',               [ApprovalController::class, 'index']);      // list pending
        Route::post('/approvals/{id}/approve',  [ApprovalController::class, 'approve']);    // terapkan
        Route::post('/approvals/{id}/reject',   [ApprovalController::class, 'reject']);     // tolak

        Route::get('/approvals/audit',   [ApprovalAuditController::class, 'index']);   // list + filter;   // ringkasan
        Route::get('/approvals/{id}',   [ApprovalAuditController::class, 'show']);    // detail
     });

    //Penyedia Restoran field
    Route::middleware(['role:staff'])->prefix('staff')->group(function () {
        // Tanah
        Route::post  ('/proposals/tanah',       [StaffProposalController::class, 'proposeTanahCreate']);
        Route::patch ('/proposals/tanah/{id}',  [StaffProposalController::class, 'proposeTanahUpdate']);
        Route::delete('/proposals/tanah/{id}',  [StaffProposalController::class, 'proposeTanahDelete']);
        // BIDANG (proposals)
        Route::post  ('/proposals/tanah/{tanah}/bidang', [StaffProposalController::class, 'proposeBidangCreate']);
        Route::put ('/proposals/bidang/{id}',          [StaffProposalController::class, 'proposeBidangUpdate']);
        Route::delete('/proposals/bidang/{id}',          [StaffProposalController::class, 'proposeBidangDelete']);

        // Warga
        Route::post  ('/proposals/warga',       [StaffProposalController::class, 'proposeWargaCreate']);
        Route::patch ('/proposals/warga/{id}',  [StaffProposalController::class, 'proposeWargaUpdate']);
        Route::delete('/proposals/warga/{id}',  [StaffProposalController::class, 'proposeWargaDelete']);

        Route::get('/proposals/my', [StaffProposalController::class, 'myProposals']);

    });

});

