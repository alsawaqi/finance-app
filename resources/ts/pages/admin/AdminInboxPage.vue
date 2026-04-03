<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  getInboxMessage,
  inboxAttachmentDownloadUrl,
  listInboxMessages,
  syncInboxMessages,
  type InboxListItem,
  type InboxMessageDetail,
  type InboxStaffUser,
} from '@/services/inbox'

const auth = useAuthStore()

const loading = ref(true)
const syncing = ref(false)
const messageLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const messages = ref<InboxListItem[]>([])
const selectedMessage = ref<InboxMessageDetail | null>(null)
const selectedMessageId = ref<number | null>(null)

const search = ref('')
const onlyUnread = ref(false)
const selectedStaffUserId = ref<number | null>(null)
const staffUsers = ref<InboxStaffUser[]>([])

const isAdminView = computed(() => auth.isAdmin)

onMounted(async () => {
  await loadMessages()
})

watch([onlyUnread, selectedStaffUserId], async () => {
  await loadMessages()
})

async function loadMessages() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await listInboxMessages(isAdminView.value, {
      user_id: isAdminView.value ? selectedStaffUserId.value : null,
      only_unread: onlyUnread.value,
      search: search.value.trim() || undefined,
    })

    messages.value = response.messages ?? []
    staffUsers.value = response.staff_users ?? []

    if (messages.value.length > 0) {
      const firstId = selectedMessageId.value && messages.value.some((row) => row.id === selectedMessageId.value)
        ? selectedMessageId.value
        : messages.value[0].id

      await openMessage(firstId)
    } else {
      selectedMessageId.value = null
      selectedMessage.value = null
    }
  } catch (error) {
    errorMessage.value = extractError(error, 'Failed to load inbox messages.')
  } finally {
    loading.value = false
  }
}

async function openMessage(id: number) {
  selectedMessageId.value = id
  messageLoading.value = true
  errorMessage.value = ''

  try {
    const { message } = await getInboxMessage(isAdminView.value, id)
    selectedMessage.value = message

    const row = messages.value.find((item) => item.id === id)
    if (row) {
      row.is_read = true
    }
  } catch (error) {
    errorMessage.value = extractError(error, 'Failed to load the selected message.')
  } finally {
    messageLoading.value = false
  }
}

async function syncNow() {
  syncing.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await syncInboxMessages(isAdminView.value, {
      user_id: isAdminView.value ? selectedStaffUserId.value : null,
    })

    successMessage.value = 'Inbox synchronization completed.'
    await loadMessages()
  } catch (error) {
    errorMessage.value = extractError(error, 'Inbox synchronization failed.')
  } finally {
    syncing.value = false
  }
}

function attachmentUrl(id: number) {
  return inboxAttachmentDownloadUrl(isAdminView.value, id)
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleString()
}

function applySearch() {
  loadMessages()
}

function extractError(error: unknown, fallback: string) {
  if (axios.isAxiosError(error)) {
    return error.response?.data?.message ?? fallback
  }

  return fallback
}
</script>

<template>
  <div class="admin-question-page">
    <section class="admin-hero admin-reveal-up">
      <div class="admin-hero__content">
        <span class="admin-hero__eyebrow">{{ isAdminView ? 'Admin mailbox workspace' : 'My mailbox workspace' }}</span>
        <h2>{{ isAdminView ? 'Staff inbox review' : 'My inbox' }}</h2>
        <p>
          {{ isAdminView
            ? 'Review synced inbox messages for staff mailboxes and inspect incoming attachments.'
            : 'Review the emails that were received in your verified mailbox.' }}
        </p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="syncing" @click="syncNow">
          {{ syncing ? 'Syncing…' : 'Sync inbox now' }}
        </button>
      </div>
    </section>

    <div v-if="errorMessage" class="admin-alert admin-alert--error">{{ errorMessage }}</div>
    <div v-if="successMessage" class="admin-alert admin-alert--success">{{ successMessage }}</div>

    <section class="admin-panel admin-reveal-up admin-reveal-delay-1" style="margin-bottom: 1rem;">
      <div class="admin-form-grid">
        <label class="admin-field">
          <span>Search</span>
          <input v-model="search" type="text" class="admin-input" placeholder="Subject, sender, or email body" @keyup.enter="applySearch" />
        </label>

        <label v-if="isAdminView" class="admin-field">
          <span>Staff mailbox</span>
          <select v-model="selectedStaffUserId" class="admin-select">
            <option :value="null">All verified staff inboxes</option>
            <option v-for="staff in staffUsers" :key="staff.id" :value="staff.id">
              {{ staff.name }} · {{ staff.email }}
            </option>
          </select>
        </label>

        <label class="admin-field">
          <span>Unread filter</span>
          <select v-model="onlyUnread" class="admin-select">
            <option :value="false">All messages</option>
            <option :value="true">Unread only</option>
          </select>
        </label>

        <div class="admin-field">
          <span>&nbsp;</span>
          <button type="button" class="admin-secondary-btn" @click="applySearch">Apply filters</button>
        </div>
      </div>
    </section>

    <div class="admin-question-builder-grid">
      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">Messages</span>
            <h2>Inbox list</h2>
          </div>
        </div>

        <div v-if="loading" class="empty-state">Loading inbox messages…</div>

        <div v-else-if="!messages.length" class="empty-state">
          No inbox messages were found yet. Sync the mailbox first.
        </div>

        <div v-else class="faq-list">
          <button
            v-for="item in messages"
            :key="item.id"
            type="button"
            class="faq-item"
            :class="{ 'faq-item--active': item.id === selectedMessageId }"
            style="width: 100%; text-align: start;"
            @click="openMessage(item.id)"
          >
            <div class="faq-item__question" style="display: block;">
              <div style="display: flex; justify-content: space-between; gap: 1rem; align-items: center;">
                <div>
                  <strong>{{ item.subject || '(No subject)' }}</strong>
                  <div style="margin-top: 0.35rem; color: #64748b; font-size: 0.9rem;">
                    {{ item.from_name || item.from_email || 'Unknown sender' }}
                    <template v-if="isAdminView && item.user_name">
                      • {{ item.user_name }}
                    </template>
                  </div>
                </div>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end;">
                  <span class="client-badge" :class="item.is_read ? 'client-badge--green' : 'client-badge--amber'">
                    {{ item.is_read ? 'Read' : 'Unread' }}
                  </span>
                  <span v-if="item.has_attachments" class="client-badge client-badge--blue">
                    {{ item.attachment_count }} attachment(s)
                  </span>
                </div>
              </div>

              <p style="margin-top: 0.75rem; color: #475569;">
                {{ item.preview || 'No preview available.' }}
              </p>

              <small style="color: #94a3b8;">{{ formatDate(item.received_at) }}</small>
            </div>
          </button>
        </div>
      </section>

      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">Message detail</span>
            <h2>Selected email</h2>
          </div>
        </div>

        <div v-if="messageLoading" class="empty-state">Loading selected message…</div>
        <div v-else-if="!selectedMessage" class="empty-state">Choose a message from the inbox list.</div>
        <div v-else class="faq-item faq-item--active">
          <div class="faq-item__question" style="display: block;">
            <div class="summary-grid summary-grid--tight" style="margin-bottom: 1rem;">
              <div><span>Subject</span><strong>{{ selectedMessage.subject || '(No subject)' }}</strong></div>
              <div><span>Received</span><strong>{{ formatDate(selectedMessage.received_at) }}</strong></div>
              <div><span>From</span><strong>{{ selectedMessage.from_name || selectedMessage.from_email || 'Unknown sender' }}</strong></div>
              <div v-if="isAdminView"><span>Mailbox</span><strong>{{ selectedMessage.user_name || selectedMessage.user_email || '—' }}</strong></div>
            </div>

            <div style="margin-bottom: 1rem;">
              <strong>To:</strong>
              <span v-if="selectedMessage.to_emails?.length">
                {{ selectedMessage.to_emails.map((row) => row.name ? `${row.name} <${row.email}>` : row.email).join(', ') }}
              </span>
              <span v-else>—</span>
            </div>

            <div v-if="selectedMessage.cc_emails?.length" style="margin-bottom: 1rem;">
              <strong>CC:</strong>
              {{ selectedMessage.cc_emails.map((row) => row.name ? `${row.name} <${row.email}>` : row.email).join(', ') }}
            </div>

            <div v-if="selectedMessage.attachments?.length" style="margin-bottom: 1rem;">
              <strong>Attachments</strong>
              <div class="upload-list" style="margin-top: 0.75rem;">
                <div v-for="attachment in selectedMessage.attachments" :key="attachment.id" class="upload-card">
                  <div>
                    <strong>{{ attachment.file_name }}</strong>
                    <p>{{ attachment.mime_type || 'Unknown type' }}</p>
                  </div>
                  <div>
                    <a class="admin-secondary-btn" :href="attachmentUrl(attachment.id)" target="_blank" rel="noopener">
                      Download
                    </a>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="selectedMessage.body_html" class="admin-panel" style="padding: 1rem; background: #fff; margin-top: 1rem;">
              <div v-html="selectedMessage.body_html"></div>
            </div>

            <pre
              v-else
              style="margin-top: 1rem; white-space: pre-wrap; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1rem; color: #334155;"
            >{{ selectedMessage.body_text || 'No body content available.' }}</pre>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>