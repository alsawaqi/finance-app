import { i18n } from '@/i18n'

export type ClientStageTone = 'slate' | 'blue' | 'amber' | 'purple' | 'green' | 'rose'

const STAGE_META: Record<string, { en: string; ar: string; tone: ClientStageTone }> = {
  questionnaire: { en: 'Questionnaire', ar: '\u0627\u0644\u0627\u0633\u062a\u0628\u064a\u0627\u0646', tone: 'slate' },

  review: { en: 'Processing', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'blue' },
  submitted_for_review: { en: 'Processing', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'blue' },
  admin_contract_preparation: { en: 'Processing', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'blue' },

  contract: { en: 'Signature', ar: '\u0627\u0644\u062a\u0648\u0642\u064a\u0639', tone: 'purple' },
  awaiting_client_signature: { en: 'Signature', ar: '\u0627\u0644\u062a\u0648\u0642\u064a\u0639', tone: 'purple' },
  awaiting_client_commercial_registration_upload: { en: 'Commercial registration upload', ar: '\u0631\u0641\u0639 \u062a\u0648\u062b\u064a\u0642 \u0627\u0644\u063a\u0631\u0641\u0629', tone: 'purple' },
  awaiting_admin_commercial_registration_upload: { en: 'Pending admin commercial registration', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u062a\u0648\u062b\u064a\u0642 \u0627\u0644\u0625\u062f\u0627\u0631\u0629', tone: 'blue' },
  awaiting_staff_assignment: { en: 'Processing', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'blue' },

  document_collection: { en: 'Document collection', ar: '\u062c\u0645\u0639 \u0627\u0644\u0645\u0633\u062a\u0646\u062f\u0627\u062a', tone: 'amber' },
  awaiting_additional_documents: { en: 'Document collection', ar: '\u062c\u0645\u0639 \u0627\u0644\u0645\u0633\u062a\u0646\u062f\u0627\u062a', tone: 'amber' },
  awaiting_client_documents: { en: 'Document collection', ar: '\u062c\u0645\u0639 \u0627\u0644\u0645\u0633\u062a\u0646\u062f\u0627\u062a', tone: 'amber' },

  client_update_requested: { en: 'Please update', ar: '\u064a\u0631\u062c\u0649 \u0627\u0644\u062a\u062d\u062f\u064a\u062b', tone: 'amber' },

  understudy: { en: 'Understudy', ar: '\u0627\u0644\u062f\u0631\u0627\u0633\u0629', tone: 'blue' },
  awaiting_staff_answers: { en: 'Understudy', ar: '\u0627\u0644\u062f\u0631\u0627\u0633\u0629', tone: 'blue' },
  awaiting_understudy_review: { en: 'Understudy', ar: '\u0627\u0644\u062f\u0631\u0627\u0633\u0629', tone: 'blue' },

  ready_for_processing: { en: 'Processing', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'green' },
  assigned_to_staff: { en: 'Processing', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'green' },
  awaiting_agent_assignment: { en: 'Processing', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'green' },
  processing: { en: 'Processing', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0639\u0627\u0644\u062c\u0629', tone: 'green' },

  accepted: { en: 'Accepted', ar: '\u0645\u0642\u0628\u0648\u0644', tone: 'green' },
  completed: { en: 'Completed', ar: '\u0645\u0643\u062a\u0645\u0644', tone: 'green' },
  rejected: { en: 'Rejected', ar: '\u0645\u0631\u0641\u0648\u0636', tone: 'rose' },
  blocked: { en: 'Blocked', ar: '\u0645\u062d\u0638\u0648\u0631', tone: 'slate' },
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

export function getClientWorkflowStageMeta(stage: string | null | undefined) {
  const key = String(stage || '').trim().toLowerCase()
  const matched = STAGE_META[key]

  if (matched) {
    return {
      key: key || 'unknown',
      label: isArabicLocale() ? matched.ar : matched.en,
      tone: matched.tone,
      className: `client-stage-badge--${matched.tone}`,
    }
  }

  return {
    key: key || 'unknown',
    label: key ? titleCase(key) : (isArabicLocale() ? '\u063a\u064a\u0631 \u0645\u0628\u062f\u0648\u0621' : 'Not started'),
    tone: 'slate' as const,
    className: 'client-stage-badge--slate',
  }
}
