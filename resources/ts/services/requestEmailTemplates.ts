import api from './api'
import type { PaginationMeta } from '@/types/pagination'

export type RequestEmailTemplateFieldType = 'text' | 'textarea' | 'number' | 'date' | 'email' | 'phone'

export type RequestEmailTemplateField = {
  key: string
  label: string
  type: RequestEmailTemplateFieldType
  required: boolean
  placeholder?: string | null
  help_text?: string | null
}

export type RequestEmailTemplateItem = {
  id: number
  name: string
  code?: string | null
  subject: string
  body: string
  fields_json: RequestEmailTemplateField[]
  fields_count: number
  sort_order: number
  is_active: boolean
  created_by?: number | null
  creator?: {
    id: number
    name: string
    email?: string | null
  } | null
  created_at?: string | null
  updated_at?: string | null
}

export type RequestEmailTemplatePayload = {
  name: string
  code?: string | null
  subject: string
  body: string
  fields_json?: RequestEmailTemplateField[] | null
  sort_order?: number
  is_active?: boolean
}

export async function listRequestEmailTemplates(params?: { page?: number; per_page?: number; active_only?: boolean }) {
  return api.get<{ data: RequestEmailTemplateItem[]; pagination: PaginationMeta }>('/api/admin/request-email-templates', { params })
}

export async function createRequestEmailTemplate(payload: RequestEmailTemplatePayload) {
  return api.post<{ message: string; data: RequestEmailTemplateItem }>('/api/admin/request-email-templates', payload)
}

export async function updateRequestEmailTemplate(id: number, payload: RequestEmailTemplatePayload) {
  return api.put<{ message: string; data: RequestEmailTemplateItem }>(`/api/admin/request-email-templates/${id}`, payload)
}

export async function toggleRequestEmailTemplateActive(id: number) {
  return api.patch<{ message: string; data: RequestEmailTemplateItem }>(`/api/admin/request-email-templates/${id}/toggle-active`)
}
