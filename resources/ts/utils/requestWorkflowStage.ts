export type RequestWorkflowStageTone = 'slate' | 'blue' | 'amber' | 'purple' | 'green' | 'rose'

const STAGE_META: Record<string, { label: string; tone: RequestWorkflowStageTone }> = {
  questionnaire: { label: 'Questionnaire', tone: 'slate' },
  review: { label: 'Review queue', tone: 'blue' },
  submitted_for_review: { label: 'Submitted for review', tone: 'blue' },
  admin_contract_preparation: { label: 'Admin contract preparation', tone: 'purple' },
  contract: { label: 'Contract stage', tone: 'purple' },
  awaiting_client_signature: { label: 'Awaiting client signature', tone: 'purple' },
  awaiting_staff_assignment: { label: 'Awaiting staff assignment', tone: 'blue' },
  document_collection: { label: 'Document collection', tone: 'amber' },
  awaiting_client_documents: { label: 'Awaiting client documents', tone: 'amber' },
  awaiting_additional_documents: { label: 'Awaiting additional documents', tone: 'amber' },
  client_update_requested: { label: 'Client update requested', tone: 'amber' },
  understudy: { label: 'Understudy', tone: 'blue' },
  awaiting_staff_answers: { label: 'Awaiting staff answers', tone: 'blue' },
  awaiting_understudy_review: { label: 'Questions answered', tone: 'blue' },
  ready_for_processing: { label: 'Ready for processing', tone: 'green' },
  assigned_to_staff: { label: 'Assigned to staff', tone: 'green' },
  awaiting_agent_assignment: { label: 'Awaiting agent assignment', tone: 'green' },
  processing: { label: 'Processing', tone: 'green' },
  accepted: { label: 'Accepted', tone: 'green' },
  rejected: { label: 'Rejected', tone: 'rose' },
  blocked: { label: 'Blocked', tone: 'slate' },
  completed: { label: 'Completed', tone: 'slate' },
}

function titleCase(value: string) {
  return value
    .split(/[_\s]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

export function getRequestWorkflowStageMeta(stage: string | null | undefined) {
  const key = String(stage || '').trim().toLowerCase()
  const matched = STAGE_META[key]

  if (matched) {
    return {
      key: key || 'unknown',
      label: matched.label,
      tone: matched.tone,
    }
  }

  return {
    key: key || 'unknown',
    label: key ? titleCase(key) : 'Not started',
    tone: 'slate' as const,
  }
}
