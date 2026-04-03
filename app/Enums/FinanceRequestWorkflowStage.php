<?php

namespace App\Enums;

enum FinanceRequestWorkflowStage: string
{
    case QUESTIONNAIRE = 'questionnaire';
    case REVIEW = 'review';
    case CONTRACT = 'contract';
    case DOCUMENT_COLLECTION = 'document_collection';
    case AWAITING_ADDITIONAL_DOCUMENTS = 'awaiting_additional_documents';
    case READY_FOR_PROCESSING = 'ready_for_processing';
    case ASSIGNED_TO_STAFF = 'assigned_to_staff';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';

    // New workflow foundation (kept additive for backward compatibility).
    case SUBMITTED_FOR_REVIEW = 'submitted_for_review';
    case ADMIN_CONTRACT_PREPARATION = 'admin_contract_preparation';
    case AWAITING_CLIENT_SIGNATURE = 'awaiting_client_signature';
    case AWAITING_STAFF_ASSIGNMENT = 'awaiting_staff_assignment';
    case AWAITING_CLIENT_DOCUMENTS = 'awaiting_client_documents';
    case CLIENT_UPDATE_REQUESTED = 'client_update_requested';
    case UNDERSTUDY = 'understudy';
    case AWAITING_STAFF_ANSWERS = 'awaiting_staff_answers';
    case AWAITING_UNDERSTUDY_REVIEW = 'awaiting_understudy_review';
    case AWAITING_AGENT_ASSIGNMENT = 'awaiting_agent_assignment';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case BLOCKED = 'blocked';
}