import api from './api'

export type InboxListItem = {
  id: number
  user_id?: number
  user_name?: string | null
  user_email?: string | null
  folder_name: string
  subject: string | null
  from_email: string | null
  from_name: string | null
  received_at: string | null
  is_read: boolean
  has_attachments: boolean
  attachment_count: number
  preview: string
}

export type InboxAttachment = {
  id: number
  file_name: string
  mime_type?: string | null
  file_extension?: string | null
  file_size?: number | null
  download_url: string
}

export type InboxMessageDetail = InboxListItem & {
  to_emails: Array<{ email: string; name?: string | null }>
  cc_emails: Array<{ email: string; name?: string | null }>
  message_id?: string | null
  in_reply_to?: string | null
  references_header?: string | null
  body_text?: string | null
  body_html?: string | null
  attachments: InboxAttachment[]
}

export type InboxStaffUser = {
  id: number
  name: string
  email: string
}

export async function listInboxMessages(
  isAdmin: boolean,
  params?: {
    user_id?: number | null
    only_unread?: boolean
    search?: string
    page?: number
    per_page?: number
  },
) {
  const url = isAdmin ? '/api/admin/inbox' : '/api/staff/inbox'
  const { data } = await api.get(url, { params })
  return data as {
    messages: InboxListItem[]
    pagination: {
      current_page: number
      last_page: number
      per_page: number
      total: number
      from: number | null
      to: number | null
    }
    staff_users?: InboxStaffUser[]
  }
}

export async function getInboxMessage(isAdmin: boolean, messageId: number | string) {
  const url = isAdmin
    ? `/api/admin/inbox/messages/${messageId}`
    : `/api/staff/inbox/messages/${messageId}`

  const { data } = await api.get(url)
  return data as { message: InboxMessageDetail }
}

export async function syncInboxMessages(
  isAdmin: boolean,
  payload?: {
    user_id?: number | null
    limit?: number | null
    folder?: string | null
  },
) {
  const url = isAdmin ? '/api/admin/inbox/sync' : '/api/staff/inbox/sync'
  const { data } = await api.post(url, payload ?? {})
  return data
}

export function inboxAttachmentDownloadUrl(isAdmin: boolean, attachmentId: number | string) {
  return isAdmin
    ? `/api/admin/inbox/attachments/${attachmentId}/download`
    : `/api/staff/inbox/attachments/${attachmentId}/download`
}
