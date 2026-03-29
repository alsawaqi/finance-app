import api from './api'

export interface StaffUserItem {
  id: number
  name: string
  email: string
  phone: string | null
  account_type: string | null
  is_active: boolean
  last_login_at: string | null
  role_names: string[]
  permission_names: string[]
  all_permission_names: string[]
  permissions_count: number
  all_permissions_count: number
  created_at: string | null
  updated_at: string | null
}

export interface StaffUserPayload {
  name: string
  email: string
  phone?: string | null
  password?: string | null
  password_confirmation?: string | null
  is_active?: boolean
  permission_names?: string[]
}

export interface StaffUserListResponse {
  data: StaffUserItem[]
  meta: {
    available_permissions: string[]
  }
}

export async function listStaffUsers() {
  return api.get<StaffUserListResponse>('/api/admin/staff-users')
}

export async function createStaffUser(payload: StaffUserPayload) {
  return api.post<{ message: string; data: StaffUserItem }>('/api/admin/staff-users', payload)
}

export async function updateStaffUser(id: number, payload: StaffUserPayload) {
  return api.put<{ message: string; data: StaffUserItem }>(`/api/admin/staff-users/${id}`, payload)
}

export async function toggleStaffUserActive(id: number) {
  return api.patch<{ message: string; data: StaffUserItem }>(`/api/admin/staff-users/${id}/toggle-active`)
}
