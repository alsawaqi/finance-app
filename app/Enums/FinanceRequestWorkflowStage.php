<?php

namespace App\Enums;

enum FinanceRequestWorkflowStage: string
{
    case QUESTIONNAIRE = 'questionnaire';
    case REVIEW = 'review';
    case CONTRACT = 'contract';
    case DOCUMENT_COLLECTION = 'document_collection';
    case READY_FOR_PROCESSING = 'ready_for_processing';
    case ASSIGNED_TO_STAFF = 'assigned_to_staff';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
}