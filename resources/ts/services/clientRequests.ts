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
  approval_reference_number?: string | null
  status: string
  workflow_stage: string
  applicant_type?: string | null
  company_name?: string | null
  submitted_at?: string | null
  latest_activity_at?: string | null
  intake_details?: Record<string, unknown> | null
  intake_details_json?: Record<string, unknown> | null
  answers_count?: number
  attachments_count?: number
  current_contract?: {
    id: number
    version_no?: number | null
    status: string
    admin_signed_at?: string | null
    client_signed_at?: string | null
    contract_pdf_path?: string | null
  } | null
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
  shareholders?: Array<{
    id: number
    shareholder_name: string
    id_file_name: string
    id_file_path: string
    file_size?: number | null
    uploaded_at?: string | null
  }>
  required_documents?: Array<{
    document_upload_step_id: number
    code?: string | null
    name: string
    status: string
    is_required: boolean
    is_uploaded: boolean
    upload?: {
      id: number
      file_name: string
      file_path: string
      status: string
      uploaded_at?: string | null
    } | null
  }>
  additional_document_requests?: Array<{
    id: number
    title: string
    reason?: string | null
    status: string
    file_name?: string | null
    file_path?: string | null
    requested_at?: string | null
    uploaded_at?: string | null
    rejection_reason?: string | null
  }>
}

export type ClientRequestWizardPayload = {
  answers: Array<{ question_id: number; value: unknown }>
  details: {
    full_name: string
    country_code: string
    requested_amount: string | number
    finance_type: 'individual' | 'company'
    company_name?: string
    notes?: string
  }
  attachments: File[]
  company_cr?: File | null
  shareholders?: Array<{
    name: string
    id_file?: File | null
  }>
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

  payload.answers.forEach((answer, index) => {
    formData.append(`answers[${index}][question_id]`, String(answer.question_id))

    if (Array.isArray(answer.value)) {
      answer.value.forEach((item) => {
        formData.append(`answers[${index}][value][]`, String(item))
      })
    } else if (answer.value !== undefined && answer.value !== null) {
      formData.append(`answers[${index}][value]`, String(answer.value))
    }
  })

  formData.append('details[full_name]', payload.details.full_name)
  formData.append('details[country_code]', payload.details.country_code)
  formData.append('details[requested_amount]', String(payload.details.requested_amount))
  formData.append('details[finance_type]', payload.details.finance_type)
  formData.append('details[company_name]', payload.details.company_name ?? '')
  formData.append('details[notes]', payload.details.notes ?? '')

  payload.attachments.forEach((file) => {
    formData.append('attachments[]', file)
  })

  if (payload.company_cr) {
    formData.append('company_cr', payload.company_cr)
  }

  ;(payload.shareholders ?? []).forEach((shareholder, index) => {
    formData.append(`shareholders[${index}][name]`, shareholder.name)
    if (shareholder.id_file) {
      formData.append(`shareholders[${index}][id_file]`, shareholder.id_file)
    }
  })

  return api.post<{ message: string; request: ClientRequestDetails }>('/api/client/requests', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
}
