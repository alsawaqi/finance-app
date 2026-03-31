export type ClientStageTone = 'slate' | 'blue' | 'amber' | 'purple' | 'green'

const STAGE_META: Record<string, { label: string; tone: ClientStageTone }> = {
  questionnaire: { label: 'Questionnaire', tone: 'slate' },
  review: { label: 'Under review', tone: 'blue' },
  contract: { label: 'Contract stage', tone: 'purple' },
  document_collection: { label: 'Upload documents', tone: 'amber' },
  awaiting_additional_documents: { label: 'More documents requested', tone: 'amber' },
  ready_for_processing: { label: 'Ready for processing', tone: 'blue' },
  assigned_to_staff: { label: 'Assigned to staff', tone: 'blue' },
  processing: { label: 'In processing', tone: 'green' },
  completed: { label: 'Completed', tone: 'green' },
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
