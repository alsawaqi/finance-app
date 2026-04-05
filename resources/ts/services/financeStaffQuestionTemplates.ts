import api from './api'
import type { PaginationMeta } from '@/types/pagination'

export type StaffQuestionType =
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

export interface FinanceStaffQuestionTemplateItem {
  id: number
  code: string | null
  question_text_en: string
  question_text_ar: string | null
  question_type: StaffQuestionType
  options_json: string[]
  options_count: number
  placeholder_en: string | null
  placeholder_ar: string | null
  help_text_en: string | null
  help_text_ar: string | null
  validation_rules: string | null
  is_required: boolean
  sort_order: number
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface FinanceStaffQuestionTemplatePayload {
  code?: string | null
  question_text_en: string
  question_text_ar?: string | null
  question_type: StaffQuestionType
  options_json?: string[] | null
  placeholder_en?: string | null
  placeholder_ar?: string | null
  help_text_en?: string | null
  help_text_ar?: string | null
  validation_rules?: string | null
  is_required?: boolean
  sort_order?: number
  is_active?: boolean
}

export async function listFinanceStaffQuestionTemplates(params?: { page?: number; per_page?: number }) {
  return api.get<{ data: FinanceStaffQuestionTemplateItem[]; pagination: PaginationMeta }>('/api/admin/staff-question-templates', { params })
}

export async function createFinanceStaffQuestionTemplate(payload: FinanceStaffQuestionTemplatePayload) {
  return api.post<{ message: string; data: FinanceStaffQuestionTemplateItem }>('/api/admin/staff-question-templates', payload)
}

export async function updateFinanceStaffQuestionTemplate(id: number, payload: FinanceStaffQuestionTemplatePayload) {
  return api.put<{ message: string; data: FinanceStaffQuestionTemplateItem }>(`/api/admin/staff-question-templates/${id}`, payload)
}

export async function toggleFinanceStaffQuestionTemplateActive(id: number) {
  return api.patch<{ message: string; data: FinanceStaffQuestionTemplateItem }>(`/api/admin/staff-question-templates/${id}/toggle-active`)
}

export async function reorderFinanceStaffQuestionTemplates(orderedIds: number[]) {
  return api.post<{ message: string; data: FinanceStaffQuestionTemplateItem[] }>('/api/admin/staff-question-templates/reorder', {
    ordered_ids: orderedIds,
  })
}
