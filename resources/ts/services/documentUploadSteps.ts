import api from './api'
import type { PaginationMeta } from '@/types/pagination'

export interface DocumentUploadStepItem {
  id: number
  code: string | null
  name: string
  description: string | null
  is_required: boolean
  is_multiple: boolean
  allowed_file_types_json: string[]
  allowed_file_types_count: number
  max_file_size_mb: number | null
  sort_order: number
  is_active: boolean
  request_document_uploads_count: number
  created_at: string | null
  updated_at: string | null
}

export interface DocumentUploadStepPayload {
  code?: string | null
  name: string
  description?: string | null
  is_required?: boolean
  is_multiple?: boolean
  allowed_file_types_json?: string[] | null
  max_file_size_mb?: number | null
  sort_order?: number
  is_active?: boolean
}

export async function listDocumentUploadSteps(params?: { page?: number; per_page?: number }) {
  return api.get<{ data: DocumentUploadStepItem[]; pagination: PaginationMeta }>('/api/admin/document-upload-steps', { params })
}

export async function createDocumentUploadStep(payload: DocumentUploadStepPayload) {
  return api.post<{ message: string; data: DocumentUploadStepItem }>('/api/admin/document-upload-steps', payload)
}

export async function updateDocumentUploadStep(id: number, payload: DocumentUploadStepPayload) {
  return api.put<{ message: string; data: DocumentUploadStepItem }>(`/api/admin/document-upload-steps/${id}`, payload)
}

export async function toggleDocumentUploadStepActive(id: number) {
  return api.patch<{ message: string; data: DocumentUploadStepItem }>(`/api/admin/document-upload-steps/${id}/toggle-active`)
}

export async function deleteDocumentUploadStep(id: number) {
  return api.delete<{ message: string }>(`/api/admin/document-upload-steps/${id}`)
}

export async function reorderDocumentUploadSteps(orderedIds: number[]) {
  return api.post<{ message: string; data: DocumentUploadStepItem[] }>('/api/admin/document-upload-steps/reorder', {
    ordered_ids: orderedIds,
  })
}
