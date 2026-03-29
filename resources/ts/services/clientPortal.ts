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

export function clientContractDownloadUrl(id: number | string) {
  return `/api/client/requests/${id}/contract/download`
}
