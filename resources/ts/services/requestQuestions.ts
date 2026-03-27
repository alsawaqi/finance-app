import api from './api'

export type QuestionType =
  | 'text'
  | 'textarea'
  | 'select'
  | 'radio'
  | 'checkbox'
  | 'number'
  | 'date'
  | 'email'
  | 'phone'
  | 'currency'

export interface RequestQuestionItem {
  id: number
  code: string | null
  question_text: string
  question_type: QuestionType
  options_json: string[]
  options_count: number
  placeholder: string | null
  help_text: string | null
  validation_rules: string | null
  is_required: boolean
  sort_order: number
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface RequestQuestionPayload {
  code?: string | null
  question_text: string
  question_type: QuestionType
  options_json?: string[] | null
  placeholder?: string | null
  help_text?: string | null
  validation_rules?: string | null
  is_required?: boolean
  sort_order?: number
  is_active?: boolean
}

export async function listRequestQuestions() {
  return api.get<{ data: RequestQuestionItem[] }>('/api/admin/request-questions')
}

export async function createRequestQuestion(payload: RequestQuestionPayload) {
  return api.post<{ message: string; data: RequestQuestionItem }>('/api/admin/request-questions', payload)
}

export async function updateRequestQuestion(id: number, payload: RequestQuestionPayload) {
  return api.put<{ message: string; data: RequestQuestionItem }>(`/api/admin/request-questions/${id}`, payload)
}

export async function toggleRequestQuestionActive(id: number) {
  return api.patch<{ message: string; data: RequestQuestionItem }>(`/api/admin/request-questions/${id}/toggle-active`)
}

export async function reorderRequestQuestions(orderedIds: number[]) {
  return api.post<{ message: string; data: RequestQuestionItem[] }>('/api/admin/request-questions/reorder', {
    ordered_ids: orderedIds,
  })
}
