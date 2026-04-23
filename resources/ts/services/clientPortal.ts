import type { AxiosRequestConfig } from 'axios'
import api from './api'
import type { PaginationMeta } from '@/types/pagination'

type ClientUploadRequestOptions = {
  timeoutMs?: number
  skipProgress?: boolean
  skipTransactionOverlay?: boolean
}

function multipartRequestConfig(options?: ClientUploadRequestOptions): AxiosRequestConfig {
  return {
    headers: {
      'Content-Type': 'multipart/form-data',
      ...(options?.skipProgress ? { 'X-Skip-Progress': '1' } : {}),
      ...(options?.skipTransactionOverlay ? { 'X-Skip-Transaction-Overlay': '1' } : {}),
    },
    timeout: options?.timeoutMs,
  }
}

export async function listClientRequests(params?: { page?: number; per_page?: number }) {
  const { data } = await api.get('/api/client/requests', { params })
  return data as {
    requests: any[]
    pagination: PaginationMeta
  }
}

export async function getClientRequest(id: number | string) {
  const { data } = await api.get(`/api/client/requests/${id}`)
  return data
}

export async function getClientContract(id: number | string) {
  const { data } = await api.get(`/api/client/requests/${id}/contract`)
  return data
}

export async function signClientContract(id: number | string, payload: { signature_data_url: string }) {
  try {
    const { data } = await api.post(`/api/client/requests/${id}/contract/sign`, payload)
    return data
  } catch (error: any) {
    const status = Number(error?.response?.status)
    // Backward compatibility for projects still exposing /sign.
    if (status === 404 || status === 405) {
      const { data } = await api.post(`/api/client/requests/${id}/sign`, payload)
      return data
    }

    throw error
  }
}

export async function uploadClientCommercialContract(
  id: number | string,
  payload: { file: File },
  options?: ClientUploadRequestOptions,
) {
  const formData = new FormData()
  formData.append('file', payload.file)

  const { data } = await api.post(
    `/api/client/requests/${id}/contract/commercial-registration`,
    formData,
    multipartRequestConfig(options),
  )

  return data
}

export async function uploadClientRequiredDocument(
  id: number | string,
  payload: { document_upload_step_id: number; file: File },
  options?: ClientUploadRequestOptions,
) {
  const formData = new FormData()
  formData.append('document_upload_step_id', String(payload.document_upload_step_id))
  formData.append('file', payload.file)

  const { data } = await api.post(
    `/api/client/requests/${id}/documents`,
    formData,
    multipartRequestConfig(options),
  )

  return data
}

export async function uploadClientAdditionalDocument(
  id: number | string,
  additionalDocumentId: number | string,
  payload: { file: File },
  options?: ClientUploadRequestOptions,
) {
  const formData = new FormData()
  formData.append('file', payload.file)

  const { data } = await api.post(
    `/api/client/requests/${id}/additional-documents/${additionalDocumentId}/upload`,
    formData,
    multipartRequestConfig(options),
  )

  return data
}

export async function submitClientUpdateValue(
  id: number | string,
  updateItemId: number | string,
  payload: { value: unknown },
) {
  const { data } = await api.patch(`/api/client/requests/${id}/update-items/${updateItemId}/value`, payload)
  return data
}

export async function submitClientUpdateFile(
  id: number | string,
  updateItemId: number | string,
  payload: { file: File },
  options?: ClientUploadRequestOptions,
) {
  const formData = new FormData()
  formData.append('file', payload.file)

  const { data } = await api.post(
    `/api/client/requests/${id}/update-items/${updateItemId}/file`,
    formData,
    multipartRequestConfig(options),
  )

  return data
}

export function clientContractDownloadUrl(id: number | string) {
  return `/api/client/requests/${id}/contract/download`
}

export async function changeClientPassword(payload: {
  current_password: string
  password: string
  password_confirmation: string
}) {
  const { data } = await api.post('/api/client/change-password', payload)
  return data as { message: string }
}
