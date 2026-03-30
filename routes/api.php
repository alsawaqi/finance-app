<?php

use App\Http\Controllers\Api\Admin\AdminAssignmentController;
use App\Http\Controllers\Api\Admin\AdminCategorizationController;
use App\Http\Controllers\Api\Admin\AdminContractController;
use App\Http\Controllers\Api\Admin\AdminFinanceRequestController;
use App\Http\Controllers\Api\Admin\BankController;
use App\Http\Controllers\Api\Admin\AgentController;
use App\Http\Controllers\Api\Admin\DocumentUploadStepController;
use App\Http\Controllers\Api\Admin\RequestQuestionController;
use App\Http\Controllers\Api\Admin\StaffUserController;
use App\Http\Controllers\Api\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\Client\ClientContractController;
use App\Http\Controllers\Api\Client\ClientRequestController;
use App\Http\Controllers\Api\Staff\StaffRequestWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('/register', RegisterController::class);
        Route::post('/login', LoginController::class);
        Route::post('/forgot-password', ForgotPasswordController::class);
        Route::post('/reset-password', ResetPasswordController::class);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', MeController::class);
        Route::post('/logout', LogoutController::class);
        Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
            ->middleware('throttle:6,1');
        Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware('signed')
            ->name('verification.verify');
    });
});

Route::prefix('client')
    ->middleware(['auth:sanctum', 'role:client'])
    ->group(function () {
        Route::get('/request-questions', [ClientRequestController::class, 'questions']);
        Route::post('/requests', [ClientRequestController::class, 'store']);
        Route::get('/requests', [ClientRequestController::class, 'index']);
        Route::get('/requests/{financeRequest}', [ClientRequestController::class, 'show']);
        Route::post('/requests/{financeRequest}/documents', [ClientRequestController::class, 'uploadRequiredDocument']);
        Route::post('/requests/{financeRequest}/additional-documents/{additionalDocument}/upload', [ClientRequestController::class, 'uploadAdditionalDocument']);
        Route::get('/requests/{financeRequest}/contract', [ClientContractController::class, 'show']);
        Route::post('/requests/{financeRequest}/contract/sign', [ClientContractController::class, 'sign']);
        Route::get('/requests/{financeRequest}/contract/download', [ClientContractController::class, 'downloadPdf']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin', 'permission:assign staff'])
    ->group(function () {
        Route::get('/requests/ready-to-assign', [AdminAssignmentController::class, 'indexReady']);
        Route::get('/staff-directory', [AdminAssignmentController::class, 'staffDirectory']);
        Route::post('/requests/{financeRequest}/assign-staff', [AdminAssignmentController::class, 'assign']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin|staff'])
    ->group(function () {
        Route::get('/requests/new', [AdminFinanceRequestController::class, 'indexNew']);
        Route::get('/requests/{financeRequest}', [AdminFinanceRequestController::class, 'show']);
        Route::post('/requests/{financeRequest}/approve', [AdminFinanceRequestController::class, 'approve']);
        Route::get('/requests/{financeRequest}/contract', [AdminContractController::class, 'show']);
        Route::post('/requests/{financeRequest}/contract', [AdminContractController::class, 'storeAndSend']);
        Route::get('/requests/{financeRequest}/contract/download', [AdminContractController::class, 'downloadPdf']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {
        Route::get('/categorization', AdminCategorizationController::class);
        Route::post('/banks', [BankController::class, 'store']);
        Route::put('/banks/{bank}', [BankController::class, 'update']);
        Route::patch('/banks/{bank}/toggle-active', [BankController::class, 'toggleActive']);

        Route::get('/document-upload-steps', [DocumentUploadStepController::class, 'index']);
        Route::post('/document-upload-steps', [DocumentUploadStepController::class, 'store']);
        Route::put('/document-upload-steps/{documentUploadStep}', [DocumentUploadStepController::class, 'update']);
        Route::delete('/document-upload-steps/{documentUploadStep}', [DocumentUploadStepController::class, 'destroy']);
        Route::patch('/document-upload-steps/{documentUploadStep}/toggle-active', [DocumentUploadStepController::class, 'toggleActive']);
        Route::post('/document-upload-steps/reorder', [DocumentUploadStepController::class, 'reorder']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin|staff'])
    ->group(function () {
        Route::get('/request-questions', [RequestQuestionController::class, 'index']);
        Route::post('/request-questions', [RequestQuestionController::class, 'store']);
        Route::put('/request-questions/{requestQuestion}', [RequestQuestionController::class, 'update']);
        Route::patch('/request-questions/{requestQuestion}/toggle-active', [RequestQuestionController::class, 'toggleActive']);
        Route::post('/request-questions/reorder', [RequestQuestionController::class, 'reorder']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin|staff', 'permission:manage staff'])
    ->group(function () {
        Route::get('/staff-users', [StaffUserController::class, 'index']);
        Route::post('/staff-users', [StaffUserController::class, 'store']);
        Route::put('/staff-users/{staffUser}', [StaffUserController::class, 'update']);
        Route::patch('/staff-users/{staffUser}/toggle-active', [StaffUserController::class, 'toggleActive']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin|staff', 'permission:manage agents'])
    ->group(function () {
        Route::get('/banks', [BankController::class, 'index']);
        Route::get('/agents', [AgentController::class, 'index']);
        Route::post('/agents', [AgentController::class, 'store']);
        Route::put('/agents/{agent}', [AgentController::class, 'update']);
        Route::patch('/agents/{agent}/toggle-active', [AgentController::class, 'toggleActive']);
    });

Route::prefix('staff')
    ->middleware(['auth:sanctum', 'role:admin|staff'])
    ->group(function () {
        Route::get('/requests', [StaffRequestWorkspaceController::class, 'index']);
        Route::get('/requests/{financeRequest}', [StaffRequestWorkspaceController::class, 'show']);
        Route::post('/requests/{financeRequest}/comments', [StaffRequestWorkspaceController::class, 'storeComment']);
        Route::post('/requests/{financeRequest}/additional-documents', [StaffRequestWorkspaceController::class, 'storeAdditionalDocument']);
        Route::get('/agents', [StaffRequestWorkspaceController::class, 'agents']);
    });
