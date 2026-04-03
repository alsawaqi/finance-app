import api from './api'

export type MailboxSettings = {
  sender_email?: string | null
  sender_name?: string | null
  smtp_username?: string | null
  smtp_enabled?: boolean
  smtp_verified_at?: string | null
  has_smtp_password?: boolean
  smtp_last_error?: string | null
  smtp_host?: string | null
  smtp_port?: number | null
  smtp_encryption?: string | null
  default_sender_email?: string | null
}

export type StaffMailboxDirectoryItem = {
  id: number
  name: string
  email: string
  phone?: string | null
  is_active: boolean
  mailbox_settings?: {
    sender_email?: string | null
    sender_name?: string | null
    smtp_username?: string | null
    smtp_enabled?: boolean
    smtp_verified_at?: string | null
    has_smtp_password?: boolean
    smtp_last_error?: string | null
  } | null
}

export async function listStaffMailboxUsers() {
  const { data } = await api.get('/api/admin/staff-mailboxes')
  return data as { staff: StaffMailboxDirectoryItem[] }
}

export async function getMailboxSettings(staffUserId: number | string) {
  const { data } = await api.get(`/api/admin/staff-mailboxes/${staffUserId}`)
  return data as { settings: MailboxSettings; staff_user: StaffMailboxDirectoryItem }
}

export async function saveMailboxSettings(
  staffUserId: number | string,
  payload: {
    smtp_username?: string | null
    smtp_sender_name?: string | null
    smtp_password?: string | null
    remove_smtp_password?: boolean
  },
) {
  const { data } = await api.patch(`/api/admin/staff-mailboxes/${staffUserId}`, payload)
  return data as { message: string; settings: MailboxSettings; staff_user: StaffMailboxDirectoryItem }
}

export async function testMailboxSettings(staffUserId: number | string) {
  const { data } = await api.post(`/api/admin/staff-mailboxes/${staffUserId}/test`)
  return data as { message: string; settings: MailboxSettings; staff_user: StaffMailboxDirectoryItem }
}
