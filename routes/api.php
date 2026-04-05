<?php

use App\Http\Controllers\Api\Admin\AdminAssignmentController;
use App\Http\Controllers\Api\Admin\AdminCategorizationController;
use App\Http\Controllers\Api\Admin\AdminContractController;
use App\Http\Controllers\Api\Admin\AdminFinanceRequestController;
use App\Http\Controllers\Api\Admin\AdminRequestFilteringController;
use App\Http\Controllers\Api\Admin\RequestFileDownloadController;
use App\Http\Controllers\Api\Admin\BankController;
use App\Http\Controllers\Api\Admin\AgentController;
use App\Http\Controllers\Api\Admin\DocumentUploadStepController;
use App\Http\Controllers\Api\Admin\FinanceRequestUpdateBatchController;
use App\Http\Controllers\Api\Admin\FinanceRequestAgentAssignmentController;
use App\Http\Controllers\Api\Admin\FinanceRequestTypeController;
use App\Http\Controllers\Api\Admin\FinanceStaffQuestionTemplateController;
use App\Http\Controllers\Api\Admin\RequestQuestionController;
use App\Http\Controllers\Api\Admin\StaffUserController;
use App\Http\Controllers\Api\Admin\StaffMailboxSettingsController;
use App\Http\Controllers\Api\Admin\AdminInboxController;
use App\Http\Controllers\Api\Staff\StaffInboxController;
use App\Http\Controllers\Api\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Client\ClientContractController;
use App\Http\Controllers\Api\Client\ClientRequestController;
use App\Http\Controllers\Api\Client\ClientRequestUpdateController;
use App\Http\Controllers\Api\Staff\StaffRequestWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('/register', RegisterController::class);
        Route::post('/login', LoginController::class);
        Route::post('/forgot-password', ForgotPasswordController::class);
        Route::post('/reset-password', ResetPasswordController::class);
    });

    Route::middleware(['auth:sanctum', 'active_user'])->group(function () {
        Route::get('/user', MeController::class);
        Route::post('/logout', LogoutController::class);
        Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
            ->middleware('throttle:6,1');
        Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware('signed')
            ->name('verification.verify');
    });
});

Route::middleware(['auth:sanctum', 'active_user'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notificationId}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
});

Route::prefix('client')
    ->middleware(['auth:sanctum', 'active_user', 'role:client'])
    ->group(function () {
        Route::get('/request-questions', [ClientRequestController::class, 'questions']);
        Route::get('/finance-request-types', [FinanceRequestTypeController::class, 'clientIndex']);
        Route::post('/requests', [ClientRequestController::class, 'store']);
        Route::get('/requests', [ClientRequestController::class, 'index']);
        Route::get('/requests/{financeRequest}', [ClientRequestController::class, 'show']);
        Route::post('/requests/{financeRequest}/documents', [ClientRequestController::class, 'uploadRequiredDocument']);
        Route::post('/requests/{financeRequest}/additional-documents/{additionalDocument}/upload', [ClientRequestController::class, 'uploadAdditionalDocument']);
        Route::patch('/requests/{financeRequest}/update-items/{updateItem}/value', [ClientRequestUpdateController::class, 'submitValue']);
        Route::post('/requests/{financeRequest}/update-items/{updateItem}/file', [ClientRequestUpdateController::class, 'submitFile']);
        Route::get('/requests/{financeRequest}/contract', [ClientContractController::class, 'show']);
        Route::post('/requests/{financeRequest}/sign', [ClientContractController::class, 'sign']);
        Route::post('/requests/{financeRequest}/contract/sign', [ClientContractController::class, 'sign']);
        Route::get('/requests/{financeRequest}/contract/download', [ClientContractController::class, 'downloadPdf']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'active_user', 'role:admin', 'permission:assign staff'])
    ->group(function () {
        Route::get('/requests/ready-to-assign', [AdminAssignmentController::class, 'indexReady']);
        Route::get('/staff-directory', [AdminAssignmentController::class, 'staffDirectory']);
        Route::post('/requests/{financeRequest}/assign-staff', [AdminAssignmentController::class, 'assign']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'active_user', 'role:admin|staff'])
    ->group(function () {
        Route::get('/requests/new', [AdminFinanceRequestController::class, 'indexNew']);
        Route::get('/requests/{financeRequest}', [AdminFinanceRequestController::class, 'show']);
        Route::post('/requests/{financeRequest}/approve', [AdminFinanceRequestController::class, 'approve']);
        Route::post('/requests/{financeRequest}/reject', [AdminFinanceRequestController::class, 'reject']);
        Route::get('/requests/{financeRequest}/contract', [AdminContractController::class, 'show']);
        Route::post('/requests/{financeRequest}/contract', [AdminContractController::class, 'storeAndSend']);
        Route::get('/requests/{financeRequest}/contract/download', [AdminContractController::class, 'downloadPdf']);
        Route::get('/requests/{financeRequest}/attachments/{attachment}/download', [RequestFileDownloadController::class, 'attachment']);
        Route::get('/requests/{financeRequest}/shareholders/{shareholder}/id-file/download', [RequestFileDownloadController::class, 'shareholderId']);
        Route::get('/requests/{financeRequest}/required-documents/{requestDocumentUpload}/download', [RequestFileDownloadController::class, 'requiredDocument']);
        Route::get('/requests/{financeRequest}/additional-documents/{additionalDocument}/download', [RequestFileDownloadController::class, 'additionalDocument']);
        Route::get('/requests/{financeRequest}/emails/{requestEmail}/attachments/{requestEmailAttachment}/download', [RequestFileDownloadController::class, 'emailAttachment']);
        
        
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'active_user', 'role:admin'])
    ->group(function () {
        Route::get('/categorization', AdminCategorizationController::class);
        Route::get('/request-filters', [AdminRequestFilteringController::class, 'requests']);
        Route::get('/clients-overview', [AdminRequestFilteringController::class, 'clients']);
        Route::get('/clients-overview/{client}/requests', [AdminRequestFilteringController::class, 'clientRequests']);
        Route::patch('/clients-overview/{client}/toggle-active', [AdminRequestFilteringController::class, 'toggleClientActive']);
        Route::get('/finance-request-types', [FinanceRequestTypeController::class, 'index']);
        Route::post('/finance-request-types', [FinanceRequestTypeController::class, 'store']);
        Route::put('/finance-request-types/{financeRequestType}', [FinanceRequestTypeController::class, 'update']);
        Route::patch('/finance-request-types/{financeRequestType}/toggle-active', [FinanceRequestTypeController::class, 'toggleActive']);

        Route::get('/staff-question-templates', [FinanceStaffQuestionTemplateController::class, 'index']);
        Route::post('/staff-question-templates', [FinanceStaffQuestionTemplateController::class, 'store']);
        Route::put('/staff-question-templates/{financeStaffQuestionTemplate}', [FinanceStaffQuestionTemplateController::class, 'update']);
        Route::patch('/staff-question-templates/{financeStaffQuestionTemplate}/toggle-active', [FinanceStaffQuestionTemplateController::class, 'toggleActive']);
        Route::post('/staff-question-templates/reorder', [FinanceStaffQuestionTemplateController::class, 'reorder']);

        Route::post('/banks', [BankController::class, 'store']);
        Route::put('/banks/{bank}', [BankController::class, 'update']);
        Route::patch('/banks/{bank}/toggle-active', [BankController::class, 'toggleActive']);

        Route::get('/document-upload-steps', [DocumentUploadStepController::class, 'index']);
        Route::post('/document-upload-steps', [DocumentUploadStepController::class, 'store']);
        Route::put('/document-upload-steps/{documentUploadStep}', [DocumentUploadStepController::class, 'update']);
        Route::delete('/document-upload-steps/{documentUploadStep}', [DocumentUploadStepController::class, 'destroy']);
        Route::patch('/document-upload-steps/{documentUploadStep}/toggle-active', [DocumentUploadStepController::class, 'toggleActive']);
        Route::post('/document-upload-steps/reorder', [DocumentUploadStepController::class, 'reorder']);
        Route::patch('/requests/{financeRequest}/staff-questions/{staffQuestion}/review', [AdminFinanceRequestController::class, 'reviewStaffQuestion']);
        Route::post('/requests/{financeRequest}/understudy-review', [AdminFinanceRequestController::class, 'reviewUnderstudy']);
        Route::post('/requests/{financeRequest}/advance-understudy', [AdminFinanceRequestController::class, 'advanceFromUnderstudy']);
        Route::get('/requests/{financeRequest}/agent-assignment-options', [FinanceRequestAgentAssignmentController::class, 'options']);
        Route::post('/requests/{financeRequest}/agent-assignments', [FinanceRequestAgentAssignmentController::class, 'store']);
        Route::post('/requests/{financeRequest}/update-batches', [FinanceRequestUpdateBatchController::class, 'store']);
        Route::patch('/requests/{financeRequest}/update-batches/{updateBatch}/cancel', [FinanceRequestUpdateBatchController::class, 'cancel']);
        Route::patch('/requests/{financeRequest}/update-items/{updateItem}/review', [FinanceRequestUpdateBatchController::class, 'review']);
        Route::get('/staff-mailboxes', [StaffMailboxSettingsController::class, 'index']);
        Route::get('/staff-mailboxes/{staffUser}', [StaffMailboxSettingsController::class, 'show']);
        Route::patch('/staff-mailboxes/{staffUser}', [StaffMailboxSettingsController::class, 'update']);
        Route::post('/staff-mailboxes/{staffUser}/test', [StaffMailboxSettingsController::class, 'test']);
        Route::get('/inbox', [AdminInboxController::class, 'index']);
        Route::get('/inbox/messages/{mailboxMessage}', [AdminInboxController::class, 'show']);
        Route::post('/inbox/sync', [AdminInboxController::class, 'sync']);
        Route::get('/inbox/attachments/{attachment}/download', [AdminInboxController::class, 'downloadAttachment']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'active_user', 'role:admin|staff'])
    ->group(function () {
        Route::get('/request-questions', [RequestQuestionController::class, 'index']);
        Route::post('/request-questions', [RequestQuestionController::class, 'store']);
        Route::put('/request-questions/{requestQuestion}', [RequestQuestionController::class, 'update']);
        Route::patch('/request-questions/{requestQuestion}/toggle-active', [RequestQuestionController::class, 'toggleActive']);
        Route::post('/request-questions/reorder', [RequestQuestionController::class, 'reorder']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'active_user', 'role:admin|staff', 'permission:manage staff'])
    ->group(function () {
        Route::get('/staff-users', [StaffUserController::class, 'index']);
        Route::post('/staff-users', [StaffUserController::class, 'store']);
        Route::put('/staff-users/{staffUser}', [StaffUserController::class, 'update']);
        Route::patch('/staff-users/{staffUser}/toggle-active', [StaffUserController::class, 'toggleActive']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'active_user', 'role:admin|staff', 'permission:manage agents'])
    ->group(function () {
        Route::get('/banks', [BankController::class, 'index']);
        Route::get('/agents', [AgentController::class, 'index']);
        Route::post('/agents', [AgentController::class, 'store']);
        Route::put('/agents/{agent}', [AgentController::class, 'update']);
        Route::patch('/agents/{agent}/toggle-active', [AgentController::class, 'toggleActive']);
    });

Route::prefix('staff')
    ->middleware(['auth:sanctum', 'active_user', 'role:admin|staff'])
    ->group(function () {
        Route::get('/requests', [StaffRequestWorkspaceController::class, 'index']);
        Route::get('/requests/{financeRequest}', [StaffRequestWorkspaceController::class, 'show']);
        Route::post('/requests/{financeRequest}/comments', [StaffRequestWorkspaceController::class, 'storeComment']);
        Route::post('/requests/{financeRequest}/required-documents/{documentUploadStep}/request-change', [StaffRequestWorkspaceController::class, 'requestRequiredDocumentChange']);
        Route::post('/requests/{financeRequest}/additional-documents', [StaffRequestWorkspaceController::class, 'storeAdditionalDocument']);
        Route::get('/agents', [StaffRequestWorkspaceController::class, 'agents']);
        Route::get('/requests/{financeRequest}/email-options', [StaffRequestWorkspaceController::class, 'emailOptions']);
        Route::post('/requests/{financeRequest}/send-email', [StaffRequestWorkspaceController::class, 'sendEmail']);
        Route::get('/inbox', [StaffInboxController::class, 'index']);
        Route::get('/inbox/messages/{mailboxMessage}', [StaffInboxController::class, 'show']);
        Route::post('/inbox/sync', [StaffInboxController::class, 'sync']);
        Route::get('/inbox/attachments/{attachment}/download', [StaffInboxController::class, 'downloadAttachment']);
        Route::patch('/requests/{financeRequest}/staff-questions/{staffQuestion}/answer', [StaffRequestWorkspaceController::class, 'answerStaffQuestion']);
        Route::patch('/requests/{financeRequest}/understudy-draft', [StaffRequestWorkspaceController::class, 'saveUnderstudyDraft']);
        Route::post('/requests/{financeRequest}/understudy-submit', [StaffRequestWorkspaceController::class, 'submitUnderstudy']);
    });
