import api from './api'

export type ClientQuestion = {
  id: number
  code?: string | null
  question_text: string
  question_type: string
  options_json?: string[] | null
  placeholder?: string | null
  help_text?: string | null
  validation_rules?: string | null
  is_required: boolean
  sort_order?: number | null
}

export type ClientRequestSummary = {
  id: number
  reference_number: string
  status: string
  workflow_stage: string
  submitted_at?: string | null
  latest_activity_at?: string | null
  intake_details?: {
    name?: string
    country?: string
    requested_amount?: number | string
    finance_type?: string
    notes?: string | null
  } | null
  answers_count?: number
  attachments_count?: number
}

export type ClientRequestDetails = ClientRequestSummary & {
  priority?: string | null
  approved_at?: string | null
  can_sign?: boolean
  can_upload_documents?: boolean
  answers: Array<{
    id: number
    question_id: number
    question_text?: string | null
    question_type?: string | null
    answer_text?: string | null
    answer_value_json?: unknown
  }>
  attachments: Array<{
    id: number
    category: string
    file_name: string
    file_path: string
    mime_type?: string | null
    file_extension?: string | null
    file_size?: number | null
    uploaded_at?: string | null
  }>
  timeline: Array<{
    id: number
    event_type: string
    event_title?: string | null
    event_description?: string | null
    created_at?: string | null
  }>
}

export type ClientRequestWizardPayload = {
  answers: Array<{ question_id: number; value: unknown }>
  details: {
    name: string
    country: string
    requested_amount: string | number
    finance_type: 'individual' | 'company'
    notes?: string
  }
  attachments: File[]
}

export async function getRequestQuestions() {
  return api.get<{ questions: ClientQuestion[] }>('/api/client/request-questions')
}

export async function getClientRequests() {
  return api.get<{ requests: ClientRequestSummary[] }>('/api/client/requests')
}

export async function getClientRequest(id: string | number) {
  return api.get<{ request: ClientRequestDetails }>(`/api/client/requests/${id}`)
}

export async function submitClientRequest(payload: ClientRequestWizardPayload) {
  const formData = new FormData()
  formData.append('answers_json', JSON.stringify(payload.answers))
  formData.append('details[name]', payload.details.name)
  formData.append('details[country]', payload.details.country)
  formData.append('details[requested_amount]', String(payload.details.requested_amount))
  formData.append('details[finance_type]', payload.details.finance_type)
  formData.append('details[notes]', payload.details.notes ?? '')

  payload.attachments.forEach((file) => {
    formData.append('attachments[]', file)
  })

  return api.post<{ message: string; request: ClientRequestDetails }>('/api/client/requests', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
}
