import api from './api'
import type { PaginationMeta } from '@/types/pagination'

export type AppNotificationTarget = {
  route_name?: string | null
  params?: Record<string, string | number | null | undefined> | null
  path?: string | null
}

export type AppNotificationItem = {
  id: string
  type?: string | null
  event_type?: string | null
  title_en?: string | null
  title_ar?: string | null
  description_en?: string | null
  description_ar?: string | null
  reference_number?: string | null
  approval_reference_number?: string | null
  company_name?: string | null
  workflow_stage?: string | null
  status?: string | null
  request_id?: number | null
  recipient_role?: string | null
  target?: AppNotificationTarget | null
  metadata?: Record<string, unknown> | null
  created_at?: string | null
  read_at?: string | null
  is_read: boolean
}

export type NotificationListResponse = {
  notifications: AppNotificationItem[]
  unread_count: number
  pagination: PaginationMeta
}

export async function listNotifications(params?: {
  page?: number
  per_page?: number
  unread_only?: boolean
}) {
  const { data } = await api.get('/api/notifications', { params })
  return data as NotificationListResponse
}

export async function markNotificationRead(notificationId: string) {
  const { data } = await api.patch(`/api/notifications/${notificationId}/read`)
  return data as {
    message: string
    notification: AppNotificationItem
    unread_count: number
  }
}

export async function markAllNotificationsRead() {
  const { data } = await api.post('/api/notifications/read-all')
  return data as {
    message: string
    updated_count: number
    unread_count: number
  }
}

