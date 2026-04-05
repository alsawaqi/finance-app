<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppFilePreviewModal from '@/components/AppFilePreviewModal.vue'
import AppPagination from '@/components/AppPagination.vue'
import { useAuthStore } from '@/stores/auth'
import { buildPreviewUrl } from '@/utils/filePreview'
import { formatDateTime } from '@/utils/dateTime'
import {
  getInboxMessage,
  inboxAttachmentDownloadUrl,
  listInboxMessages,
  syncInboxMessages,
  type InboxListItem,
  type InboxMessageDetail,
  type InboxStaffUser,
} from '@/services/inbox'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'

const auth = useAuthStore()
const { locale } = useI18n()

const loading = ref(true)
const syncing = ref(false)
const messageLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const messages = ref<InboxListItem[]>([])
const selectedMessage = ref<InboxMessageDetail | null>(null)
const selectedMessageId = ref<number | null>(null)
const filePreviewOpen = ref(false)
const filePreviewName = ref('')
const filePreviewMime = ref('')
const filePreviewUrl = ref('')
const fileDownloadUrl = ref('')

const search = ref('')
const onlyUnread = ref(false)
const selectedStaffUserId = ref<number | null>(null)
const staffUsers = ref<InboxStaffUser[]>([])
const pagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 25 })

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

const isAdminView = computed(() => auth.isAdmin)
const stats = computed(() => {
  const total = pagination.value.total
  const unread = messages.value.filter((item) => !item.is_read).length
  const withAttachments = messages.value.filter((item) => item.has_attachments).length
  const mailboxCount = staffUsers.value.length

  return [
    { label: uiText('Inbox messages', 'رسائل البريد الوارد'), value: String(total), tone: 'violet' },
    { label: uiText('Unread', 'غير المقروء'), value: String(unread), tone: unread > 0 ? 'amber' : 'emerald' },
    { label: uiText('With attachments', 'بمرفقات'), value: String(withAttachments), tone: 'blue' },
    {
      label: isAdminView.value ? uiText('Staff mailboxes', 'بريد الموظفين') : uiText('My mailbox status', 'حالة بريدي'),
      value: isAdminView.value ? String(mailboxCount) : (auth.user?.mailbox_settings?.smtp_enabled ? uiText('Verified', 'موثّق') : uiText('Pending', 'قيد الانتظار')),
      tone: 'slate',
    },
  ]
})

onMounted(async () => {
  await loadMessages(1)
})

watch([onlyUnread, selectedStaffUserId], async () => {
  await loadMessages(1)
})

async function loadMessages(page = pagination.value.current_page) {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await listInboxMessages(isAdminView.value, {
      user_id: isAdminView.value ? selectedStaffUserId.value : null,
      only_unread: onlyUnread.value,
      search: search.value.trim() || undefined,
      page,
      per_page: pagination.value.per_page,
    })

    messages.value = response.messages ?? []
    staffUsers.value = response.staff_users ?? []
    const currentPage = response.pagination?.current_page ?? page
    const perPage = response.pagination?.per_page ?? pagination.value.per_page
    const total = response.pagination?.total ?? messages.value.length

    pagination.value = {
      current_page: currentPage,
      last_page: response.pagination?.last_page ?? 1,
      per_page: perPage,
      total,
      from: response.pagination?.from ?? (total > 0 ? (currentPage - 1) * perPage + 1 : null),
      to: response.pagination?.to ?? (total > 0 ? Math.min(currentPage * perPage, total) : null),
    }

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
    errorMessage.value = extractError(error, uiText('Failed to load inbox messages.', 'تعذر تحميل رسائل البريد الوارد.'))
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
    errorMessage.value = extractError(error, uiText('Failed to load the selected message.', 'تعذر تحميل الرسالة المحددة.'))
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

    successMessage.value = uiText('Inbox synchronization completed.', 'اكتملت مزامنة البريد الوارد.')
    await loadMessages(1)
  } catch (error) {
    errorMessage.value = extractError(error, uiText('Inbox synchronization failed.', 'فشلت مزامنة البريد الوارد.'))
  } finally {
    syncing.value = false
  }
}

function attachmentUrl(id: number) {
  return inboxAttachmentDownloadUrl(isAdminView.value, id)
}

function openAttachmentPreview(fileName: string | null | undefined, downloadUrl: string, mimeType?: string | null) {
  const targetUrl = String(downloadUrl || '').trim()
  if (!targetUrl) return
  filePreviewName.value = String(fileName || uiText('Attachment', 'مرفق'))
  filePreviewMime.value = String(mimeType || '')
  fileDownloadUrl.value = targetUrl
  filePreviewUrl.value = buildPreviewUrl(targetUrl)
  filePreviewOpen.value = true
}

function formatDate(value?: string | null) {
  return formatDateTime(value, locale, '-')
}

function applySearch() {
  loadMessages(1)
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
        <span class="admin-hero__eyebrow">{{ isAdminView ? uiText('Admin mailbox workspace', 'مساحة بريد الإدارة') : uiText('My mailbox workspace', 'مساحة بريدي') }}</span>
        <h2>{{ isAdminView ? uiText('Staff inbox review', 'مراجعة بريد الموظفين') : uiText('My inbox', 'بريدي الوارد') }}</h2>
        <p>
          {{ isAdminView
            ? uiText('Review synced inbox messages for staff mailboxes and inspect incoming attachments.', 'راجع الرسائل المُزامنة لبريد الموظفين وافحص المرفقات الواردة.')
            : uiText('Review the emails that were received in your verified mailbox.', 'راجع الرسائل المستلمة في بريدك الموثّق.') }}
        </p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="syncing" @click="syncNow">
          {{ syncing ? uiText('Syncing…', 'جارٍ المزامنة…') : uiText('Sync inbox now', 'مزامنة البريد الآن') }}
        </button>
      </div>
    </section>

    <div class="admin-question-stats-grid admin-question-stats-grid--balanced admin-reveal-up admin-reveal-delay-1">
      <article v-for="stat in stats" :key="stat.label" class="admin-question-stat" :class="`tone-${stat.tone}`">
        <strong>{{ stat.value }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </div>

    <div v-if="errorMessage" class="admin-alert admin-alert--error">{{ errorMessage }}</div>
    <div v-if="successMessage" class="admin-alert admin-alert--success">{{ successMessage }}</div>

    <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
      <div class="admin-form-grid">
        <label class="admin-field">
          <span>{{ uiText('Search', 'بحث') }}</span>
          <input v-model="search" type="text" class="admin-input" :placeholder="uiText('Subject, sender, or email body', 'الموضوع أو المرسل أو محتوى الرسالة')" @keyup.enter="applySearch" />
        </label>

        <label v-if="isAdminView" class="admin-field">
          <span>{{ uiText('Staff mailbox', 'بريد الموظف') }}</span>
          <select v-model="selectedStaffUserId" class="admin-select">
            <option :value="null">{{ uiText('All verified staff inboxes', 'كل صناديق بريد الموظفين الموثقة') }}</option>
            <option v-for="staff in staffUsers" :key="staff.id" :value="staff.id">
              {{ staff.name }} · {{ staff.email }}
            </option>
          </select>
        </label>

        <label class="admin-field">
          <span>{{ uiText('Unread filter', 'فلتر غير المقروء') }}</span>
          <select v-model="onlyUnread" class="admin-select">
            <option :value="false">{{ uiText('All messages', 'كل الرسائل') }}</option>
            <option :value="true">{{ uiText('Unread only', 'غير المقروء فقط') }}</option>
          </select>
        </label>

        <div class="admin-field">
          <span>&nbsp;</span>
          <button type="button" class="admin-secondary-btn" @click="applySearch">{{ uiText('Apply filters', 'تطبيق الفلاتر') }}</button>
        </div>
      </div>
    </section>

    <div class="admin-question-builder-grid">
      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ uiText('Messages', 'الرسائل') }}</span>
            <h2>{{ uiText('Inbox list', 'قائمة البريد الوارد') }}</h2>
          </div>
        </div>

        <div v-if="loading" class="empty-state">{{ uiText('Loading inbox messages…', 'جارٍ تحميل رسائل البريد الوارد…') }}</div>

        <div v-else-if="!messages.length" class="empty-state">
          {{ uiText('No inbox messages were found yet. Sync the mailbox first.', 'لا توجد رسائل واردة حتى الآن. قم بمزامنة البريد أولاً.') }}
        </div>

        <div v-else class="faq-list">
          <button
            v-for="item in messages"
            :key="item.id"
            type="button"
            class="faq-item admin-inbox-item"
            :class="{ 'faq-item--active': item.id === selectedMessageId }"
            @click="openMessage(item.id)"
          >
            <div class="faq-item__question admin-inbox-item__content">
              <div class="admin-inbox-item__head">
                <div>
                  <strong>{{ item.subject || uiText('(No subject)', '(بدون موضوع)') }}</strong>
                  <p class="admin-inbox-item__meta">
                    {{ item.from_name || item.from_email || uiText('Unknown sender', 'مرسل غير معروف') }}
                    <template v-if="isAdminView && item.user_name">
                      • {{ item.user_name }}
                    </template>
                  </p>
                </div>

                <div class="admin-inbox-item__badges">
                  <span class="client-badge" :class="item.is_read ? 'client-badge--green' : 'client-badge--amber'">
                    {{ item.is_read ? uiText('Read', 'مقروء') : uiText('Unread', 'غير مقروء') }}
                  </span>
                  <span v-if="item.has_attachments" class="client-badge client-badge--blue">
                    {{ item.attachment_count }} {{ uiText('attachment(s)', 'مرفق') }}
                  </span>
                </div>
              </div>

              <p class="admin-inbox-item__preview">
                {{ item.preview || uiText('No preview available.', 'لا توجد معاينة متاحة.') }}
              </p>

              <small class="admin-inbox-item__time">{{ formatDate(item.received_at) }}</small>
            </div>
          </button>
        </div>

        <AppPagination :pagination="pagination" :disabled="loading" @change="loadMessages" />
      </section>

      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ uiText('Message detail', 'تفاصيل الرسالة') }}</span>
            <h2>{{ uiText('Selected email', 'الرسالة المحددة') }}</h2>
          </div>
        </div>

        <div v-if="messageLoading" class="empty-state">{{ uiText('Loading selected message…', 'جارٍ تحميل الرسالة المحددة…') }}</div>
        <div v-else-if="!selectedMessage" class="empty-state">{{ uiText('Choose a message from the inbox list.', 'اختر رسالة من قائمة البريد الوارد.') }}</div>
        <div v-else class="faq-item faq-item--active">
          <div class="faq-item__question">
            <div class="summary-grid summary-grid--tight admin-inbox-summary-grid">
              <div><span>{{ uiText('Subject', 'الموضوع') }}</span><strong>{{ selectedMessage.subject || uiText('(No subject)', '(بدون موضوع)') }}</strong></div>
              <div><span>{{ uiText('Received', 'تاريخ الاستلام') }}</span><strong>{{ formatDate(selectedMessage.received_at) }}</strong></div>
              <div><span>{{ uiText('From', 'من') }}</span><strong>{{ selectedMessage.from_name || selectedMessage.from_email || uiText('Unknown sender', 'مرسل غير معروف') }}</strong></div>
              <div v-if="isAdminView"><span>{{ uiText('Mailbox', 'صندوق البريد') }}</span><strong>{{ selectedMessage.user_name || selectedMessage.user_email || '—' }}</strong></div>
            </div>

            <div class="admin-inbox-recipient-row">
              <strong>{{ uiText('To:', 'إلى:') }}</strong>
              <span v-if="selectedMessage.to_emails?.length">
                {{ selectedMessage.to_emails.map((row) => row.name ? `${row.name} <${row.email}>` : row.email).join(', ') }}
              </span>
              <span v-else>—</span>
            </div>

            <div v-if="selectedMessage.cc_emails?.length" class="admin-inbox-recipient-row">
              <strong>{{ uiText('CC:', 'نسخة:') }}</strong>
              {{ selectedMessage.cc_emails.map((row) => row.name ? `${row.name} <${row.email}>` : row.email).join(', ') }}
            </div>

            <div v-if="selectedMessage.attachments?.length">
              <strong>{{ uiText('Attachments', 'المرفقات') }}</strong>
              <div class="upload-list admin-inbox-attachments">
                <div v-for="attachment in selectedMessage.attachments" :key="attachment.id" class="upload-card">
                  <div>
                    <strong>{{ attachment.file_name }}</strong>
                    <p>{{ attachment.mime_type || uiText('Unknown type', 'نوع غير معروف') }}</p>
                  </div>
                  <div class="approve-actions">
                    <button
                      type="button"
                      class="admin-secondary-btn"
                      @click="openAttachmentPreview(attachment.file_name, attachmentUrl(attachment.id), attachment.mime_type || attachment.file_extension)"
                    >
                      {{ uiText('Preview', 'معاينة') }}
                    </button>
                    <a class="admin-secondary-btn" :href="attachmentUrl(attachment.id)" target="_blank" rel="noopener">
                      {{ uiText('Download', 'تنزيل') }}
                    </a>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="selectedMessage.body_html" class="admin-panel admin-inbox-body-html">
              <div v-html="selectedMessage.body_html"></div>
            </div>

            <pre v-else class="admin-inbox-body-text">{{ selectedMessage.body_text || uiText('No body content available.', 'لا يوجد محتوى نصي متاح.') }}</pre>
          </div>
        </div>
      </section>
    </div>

    <AppFilePreviewModal
      :model-value="filePreviewOpen"
      @update:model-value="(value) => { filePreviewOpen = value }"
      :title="uiText('Attachment preview', 'معاينة المرفق')"
      :file-name="filePreviewName"
      :mime-type="filePreviewMime"
      :preview-url="filePreviewUrl"
      :download-url="fileDownloadUrl"
    />
  </div>
</template>
