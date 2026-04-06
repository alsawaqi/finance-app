 import api from './api'

export type ClientQuestion = {
  id: number
  code?: string | null
  question_text: string
  question_type: string
  finance_type?: 'all' | 'individual' | 'company'
  options_json?: string[] | null
  placeholder?: string | null
  help_text?: string | null
  validation_rules?: string | null
  is_required: boolean
  sort_order?: number | null
}


export type FinanceRequestTypeOption = {
  id: number
  slug: string
  name_en: string
  name_ar: string
  description_en?: string | null
  description_ar?: string | null
  is_active: boolean
  sort_order: number
}

export type ClientRequestSummary = {
  id: number
  reference_number: string
  approval_reference_number?: string | null
  status: string
  workflow_stage: string
  applicant_type?: string | null
  company_name?: string | null
  country_code?: string | null
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
    phone_country_code?: string | null
    phone_number?: string | null
    id_number?: string | null
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
    is_multiple?: boolean
    is_uploaded: boolean
    uploads_count?: number
    accepted_uploads_count?: number
    upload?: {
      id: number
      file_name: string
      file_path: string
      status: string
      uploaded_at?: string | null
    } | null
    uploads?: Array<{
      id: number
      file_name: string
      file_path: string
      status: string
      uploaded_at?: string | null
    }>
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
      finance_request_type_id: number | ''
      country: string
      requested_amount: string | number
      finance_type: 'individual' | 'company'
      company_name?: string
      company_cr_number?: string
      email: string
      phone_country_code: string
      phone_number: string
      unified_number: string
      national_address_number: string
      address: string
      notes?: string
    }
  attachments: File[]
  national_address_attachment: File
  company_cr?: File | null
  shareholders?: Array<{
    name: string
    phone_country_code: string
    phone_number: string
    id_number: string
    id_file?: File | null
  }>
}

export async function getRequestQuestions(financeType?: 'individual' | 'company') {
  return api.get<{
    questions: ClientQuestion[]
    finance_request_types: FinanceRequestTypeOption[]
  }>('/api/client/request-questions', {
    params: financeType ? { finance_type: financeType } : undefined,
  })
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

  formData.append('details[finance_request_type_id]', String(payload.details.finance_request_type_id))
  formData.append('details[country]', payload.details.country)
  formData.append('details[requested_amount]', String(payload.details.requested_amount))
  formData.append('details[finance_type]', payload.details.finance_type)
  formData.append('details[company_name]', payload.details.company_name ?? '')
  formData.append('details[company_cr_number]', payload.details.company_cr_number ?? '')
  formData.append('details[email]', payload.details.email)
  formData.append('details[phone_country_code]', payload.details.phone_country_code)
  formData.append('details[phone_number]', payload.details.phone_number)
  formData.append('details[unified_number]', payload.details.unified_number)
  formData.append('details[national_address_number]', payload.details.national_address_number)
  formData.append('details[address]', payload.details.address)
  formData.append('details[notes]', payload.details.notes ?? '')

  payload.attachments.forEach((file) => {
    formData.append('attachments[]', file)
  })

  formData.append('national_address_attachment', payload.national_address_attachment)

  if (payload.company_cr) {
    formData.append('company_cr', payload.company_cr)
  }

  ;(payload.shareholders ?? []).forEach((shareholder, index) => {
    formData.append(`shareholders[${index}][name]`, shareholder.name)
    formData.append(`shareholders[${index}][phone_country_code]`, shareholder.phone_country_code)
    formData.append(`shareholders[${index}][phone_number]`, shareholder.phone_number)
    formData.append(`shareholders[${index}][id_number]`, shareholder.id_number)

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
