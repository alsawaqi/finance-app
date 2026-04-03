export type ClientStageTone = 'slate' | 'blue' | 'amber' | 'purple' | 'green' | 'rose'

const STAGE_META: Record<string, { label: string; tone: ClientStageTone }> = {
  questionnaire: { label: 'Questionnaire', tone: 'slate' },

  review: { label: 'Processing', tone: 'blue' },
  submitted_for_review: { label: 'Processing', tone: 'blue' },
  admin_contract_preparation: { label: 'Processing', tone: 'blue' },

  contract: { label: 'Signature', tone: 'purple' },
  awaiting_client_signature: { label: 'Signature', tone: 'purple' },
  awaiting_staff_assignment: { label: 'Processing', tone: 'blue' },

  document_collection: { label: 'Document Collection', tone: 'amber' },
  awaiting_additional_documents: { label: 'Document Collection', tone: 'amber' },
  awaiting_client_documents: { label: 'Document Collection', tone: 'amber' },

  client_update_requested: { label: 'Please Update', tone: 'amber' },

  understudy: { label: 'Understudy', tone: 'blue' },
  awaiting_staff_answers: { label: 'Understudy', tone: 'blue' },
  awaiting_understudy_review: { label: 'Understudy', tone: 'blue' },

  ready_for_processing: { label: 'Processing', tone: 'green' },
  assigned_to_staff: { label: 'Processing', tone: 'green' },
  awaiting_agent_assignment: { label: 'Processing', tone: 'green' },
  processing: { label: 'Processing', tone: 'green' },

  accepted: { label: 'Accepted', tone: 'green' },
  completed: { label: 'Completed', tone: 'green' },
  rejected: { label: 'Rejected', tone: 'rose' },
  blocked: { label: 'Blocked', tone: 'slate' },
}

function titleCase(value: string) {
  return value
    .split(/[_\s]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

export function getClientWorkflowStageMeta(stage: string | null | undefined) {
  const key = String(stage || '').trim().toLowerCase()
  const matched = STAGE_META[key]

  if (matched) {
    return {
      key: key || 'unknown',
      label: matched.label,
      tone: matched.tone,
      className: `client-stage-badge--${matched.tone}`,
    }
  }

  return {
    key: key || 'unknown',
    label: key ? titleCase(key) : 'Not started',
    tone: 'slate' as const,
    className: 'client-stage-badge--slate',
  }
}