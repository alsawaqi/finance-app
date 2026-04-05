import api from './api'
import type { PaginationMeta } from '@/types/pagination'

export interface FinanceRequestTypeItem {
  id: number
  slug: string
  name_en: string
  name_ar: string
  description_en: string | null
  description_ar: string | null
  is_active: boolean
  sort_order: number
  created_at: string | null
  updated_at: string | null
}

export interface FinanceRequestTypePayload {
  slug?: string | null
  name_en: string
  name_ar: string
  description_en?: string | null
  description_ar?: string | null
  is_active?: boolean
  sort_order?: number
}

export async function listFinanceRequestTypes(params?: { page?: number; per_page?: number }) {
  return api.get<{ data: FinanceRequestTypeItem[]; pagination: PaginationMeta }>('/api/admin/finance-request-types', { params })
}

export async function createFinanceRequestType(payload: FinanceRequestTypePayload) {
  return api.post<{ message: string; data: FinanceRequestTypeItem }>('/api/admin/finance-request-types', payload)
}

export async function updateFinanceRequestType(id: number, payload: FinanceRequestTypePayload) {
  return api.put<{ message: string; data: FinanceRequestTypeItem }>(`/api/admin/finance-request-types/${id}`, payload)
}

export async function toggleFinanceRequestTypeActive(id: number) {
  return api.patch<{ message: string; data: FinanceRequestTypeItem }>(`/api/admin/finance-request-types/${id}/toggle-active`)
}
