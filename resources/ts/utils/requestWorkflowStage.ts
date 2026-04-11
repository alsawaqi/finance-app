import { i18n } from '@/i18n'

export type RequestWorkflowStageTone = 'slate' | 'blue' | 'amber' | 'purple' | 'green' | 'rose'

/** Values accepted by PATCH /api/admin/requests/{id}/workflow-stage (matches PHP enum). */
export const FINANCE_REQUEST_WORKFLOW_STAGES: string[] = [
  'questionnaire',
  'review',
  'contract',
  'document_collection',
  'awaiting_additional_documents',
  'ready_for_processing',
  'assigned_to_staff',
  'processing',
  'completed',
  'submitted_for_review',
  'admin_contract_preparation',
  'awaiting_client_signature',
  'awaiting_client_commercial_registration_upload',
  'awaiting_admin_commercial_registration_upload',
  'awaiting_staff_assignment',
  'awaiting_client_documents',
  'client_update_requested',
  'understudy',
  'awaiting_staff_answers',
  'awaiting_understudy_review',
  'awaiting_agent_assignment',
  'accepted',
  'rejected',
  'blocked',
]

const STAGE_META: Record<string, { en: string; ar: string; tone: RequestWorkflowStageTone }> = {
  questionnaire: { en: 'Questionnaire', ar: '\u0627\u0644\u0627\u0633\u062a\u0628\u064a\u0627\u0646', tone: 'slate' },
  review: { en: 'Review queue', ar: '\u0642\u0627\u0626\u0645\u0629 \u0627\u0644\u0645\u0631\u0627\u062c\u0639\u0629', tone: 'blue' },
  submitted_for_review: { en: 'Submitted for review', ar: '\u0645\u0631\u0633\u0644 \u0644\u0644\u0645\u0631\u0627\u062c\u0639\u0629', tone: 'blue' },
  admin_contract_preparation: { en: 'Admin contract preparation', ar: '\u0625\u0639\u062f\u0627\u062f \u0627\u0644\u0639\u0642\u062f \u0645\u0646 \u0627\u0644\u0625\u062f\u0627\u0631\u0629', tone: 'purple' },
  contract: { en: 'Contract stage', ar: '\u0645\u0631\u062d\u0644\u0629 \u0627\u0644\u0639\u0642\u062f', tone: 'purple' },
  awaiting_client_signature: { en: 'Awaiting client signature', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u062a\u0648\u0642\u064a\u0639 \u0627\u0644\u0639\u0645\u064a\u0644', tone: 'purple' },
  awaiting_client_commercial_registration_upload: { en: 'Awaiting client commercial registration upload', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u0631\u0641\u0639 \u062a\u0648\u062b\u064a\u0642 \u0627\u0644\u063a\u0631\u0641\u0629 \u0645\u0646 \u0627\u0644\u0639\u0645\u064a\u0644', tone: 'purple' },
  awaiting_admin_commercial_registration_upload: { en: 'Awaiting admin commercial registration upload', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u0631\u0641\u0639 \u062a\u0648\u062b\u064a\u0642 \u0627\u0644\u063a\u0631\u0641\u0629 \u0645\u0646 \u0627\u0644\u0625\u062f\u0627\u0631\u0629', tone: 'purple' },
  awaiting_staff_assignment: { en: 'Awaiting staff assignment', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u062a\u0639\u064a\u064a\u0646 \u0627\u0644\u0645\u0648\u0638\u0641', tone: 'blue' },
  document_collection: { en: 'Document collection', ar: '\u062c\u0645\u0639 \u0627\u0644\u0645\u0633\u062a\u0646\u062f\u0627\u062a', tone: 'amber' },
  awaiting_client_documents: { en: 'Awaiting client documents', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u0645\u0633\u062a\u0646\u062f\u0627\u062a \u0627\u0644\u0639\u0645\u064a\u0644', tone: 'amber' },
  awaiting_additional_documents: { en: 'Awaiting additional documents', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u0645\u0633\u062a\u0646\u062f\u0627\u062a \u0625\u0636\u0627\u0641\u064a\u0629', tone: 'amber' },
  client_update_requested: { en: 'Client update requested', ar: '\u062a\u0645 \u0637\u0644\u0628 \u062a\u062d\u062f\u064a\u062b \u0645\u0646 \u0627\u0644\u0639\u0645\u064a\u0644', tone: 'amber' },
  understudy: { en: 'Understudy', ar: '\u0627\u0644\u062f\u0631\u0627\u0633\u0629', tone: 'blue' },
  awaiting_staff_answers: { en: 'Awaiting staff answers', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u0625\u062c\u0627\u0628\u0627\u062a \u0627\u0644\u0645\u0648\u0638\u0641', tone: 'blue' },
  awaiting_answers: { en: 'Awaiting answers', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u0627\u0644\u0625\u062c\u0627\u0628\u0627\u062a', tone: 'blue' },
  awaiting_understudy_review: { en: 'Questions answered', ar: '\u062a\u0645\u062a \u0627\u0644\u0625\u062c\u0627\u0628\u0629 \u0639\u0644\u0649 \u0627\u0644\u0623\u0633\u0626\u0644\u0629', tone: 'blue' },
  ready_for_processing: { en: 'Ready for processing', ar: '\u062c\u0627\u0647\u0632 \u0644\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'green' },
  assigned_to_staff: { en: 'Assigned to staff', ar: '\u0645\u0633\u0646\u062f \u0625\u0644\u0649 \u0645\u0648\u0638\u0641', tone: 'green' },
  awaiting_agent_assignment: { en: 'Awaiting agent assignment', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u062a\u0639\u064a\u064a\u0646 \u0627\u0644\u0648\u0643\u064a\u0644', tone: 'green' },
  processing: { en: 'Processing', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'green' },
  accepted: { en: 'Accepted', ar: '\u0645\u0642\u0628\u0648\u0644', tone: 'green' },
  rejected: { en: 'Rejected', ar: '\u0645\u0631\u0641\u0648\u0636', tone: 'rose' },
  blocked: { en: 'Blocked', ar: '\u0645\u062d\u0638\u0648\u0631', tone: 'slate' },
  completed: { en: 'Completed', ar: '\u0645\u0643\u062a\u0645\u0644', tone: 'slate' },
  cancelled: { en: 'Cancelled', ar: '\u0645\u0644\u063a\u064a', tone: 'rose' },
}

function titleCase(value: string) {
  return value
    .split(/[_\s]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function isArabicLocale() {
  return String(i18n.global.locale.value || 'en').toLowerCase().startsWith('ar')
}

export function getRequestWorkflowStageMeta(stage: string | null | undefined) {
  const key = String(stage || '').trim().toLowerCase()
  const matched = STAGE_META[key]

  if (matched) {
    return {
      key: key || 'unknown',
      label: isArabicLocale() ? matched.ar : matched.en,
      tone: matched.tone,
    }
  }

  return {
    key: key || 'unknown',
    label: key ? titleCase(key) : (isArabicLocale() ? '\u063a\u064a\u0631 \u0645\u0628\u062f\u0648\u0621' : 'Not started'),
    tone: 'slate' as const,
  }
}
