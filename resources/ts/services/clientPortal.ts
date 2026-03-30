import api from './api'

export async function listClientRequests() {
  const { data } = await api.get('/api/client/requests')
  return data
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
  const { data } = await api.post(`/api/client/requests/${id}/contract/sign`, payload)
  return data
}

export async function uploadClientRequiredDocument(id: number | string, payload: { document_upload_step_id: number; file: File }) {
  const formData = new FormData()
  formData.append('document_upload_step_id', String(payload.document_upload_step_id))
  formData.append('file', payload.file)

  const { data } = await api.post(`/api/client/requests/${id}/documents`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

  return data
}

export async function uploadClientAdditionalDocument(
  id: number | string,
  additionalDocumentId: number | string,
  payload: { file: File },
) {
  const formData = new FormData()
  formData.append('file', payload.file)

  const { data } = await api.post(`/api/client/requests/${id}/additional-documents/${additionalDocumentId}/upload`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

  return data
}

export function clientContractDownloadUrl(id: number | string) {
  return `/api/client/requests/${id}/contract/download`
}
