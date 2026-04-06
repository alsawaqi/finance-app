<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  adminRequestEmailAttachmentDownloadUrl,
  getAdminRequestDetails,
} from '@/services/adminRequests'
import AppFilePreviewModal from '@/components/AppFilePreviewModal.vue'
import { buildPreviewUrl } from '@/utils/filePreview'
import { intakeFullName } from '@/utils/requestIntake'
import { formatDateTime } from '@/utils/dateTime'
import { formatEmailDeliveryStatus } from '@/utils/requestStatus'
import RequestSummaryStatGrid from './inc/RequestSummaryStatGrid.vue'

const route = useRoute()
const { t, locale } = useI18n()

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

const requestId = computed(() => String(route.params.id || ''))
const loading = ref(true)
const errorMessage = ref('')
const requestItem = ref<any | null>(null)
const currentPage = ref(1)
const pageSize = 12
const filePreviewOpen = ref(false)
const filePreviewName = ref('')
const filePreviewMime = ref('')
const filePreviewUrl = ref('')
const fileDownloadUrl = ref('')

const emails = computed(() => {
  const rows = Array.isArray(requestItem.value?.emails) ? [...requestItem.value.emails] : []
  return rows.sort((a: any, b: any) => {
    const aAt = new Date(a?.sent_at || a?.created_at || '').getTime()
    const bAt = new Date(b?.sent_at || b?.created_at || '').getTime()
    return (Number.isNaN(bAt) ? 0 : bAt) - (Number.isNaN(aAt) ? 0 : aAt)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(emails.value.length / pageSize)))
const pagedEmails = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return emails.value.slice(start, start + pageSize)
})

const summaryItems = computed(() => {
  const totalAttachments = emails.value.reduce((sum: number, row: any) => sum + (Array.isArray(row?.attachments) ? row.attachments.length : 0), 0)
  const delivered = emails.value.filter((row: any) => String(row?.delivery_status || '').toLowerCase() === 'delivered').length
  const queued = emails.value.filter((row: any) => String(row?.delivery_status || '').toLowerCase() === 'queued').length

  return [
    {
      label: t('adminRequestDetails.summary.client'),
      value: intakeFullName(requestItem.value?.intake_details_json, requestItem.value?.client?.name || t('adminRequestDetails.states.clientFallback')),
      hint: requestItem.value?.reference_number || t('adminRequestDetails.states.emptyValue'),
    },
    {
      label: t('adminRequestDetails.emailActivity.title'),
      value: String(emails.value.length),
      hint: `${t('adminRequestDetails.summary.emailLogs')} / ${t('adminRequestDetails.summary.timelineEvents')}`,
    },
    {
      label: t('adminRequestDetails.emailActivity.attachmentsCount', { count: totalAttachments }),
      value: String(totalAttachments),
      hint: `${t('adminRequestDetails.emailActivity.queued')}: ${queued}`,
    },
    {
      label: t('adminRequestDetails.summary.status'),
      value: String(delivered),
      hint: uiText('Delivered', 'تم التسليم'),
    },
  ]
})

watch(totalPages, (pages) => {
  if (currentPage.value > pages) {
    currentPage.value = pages
  }
})

watch(requestId, () => {
  currentPage.value = 1
  load()
})

function formatDate(value: unknown) {
  return formatDateTime(value, locale, '-')
}

function formatFileSize(value: number | null | undefined) {
  if (!value || value <= 0) return '0 B'
  if (value < 1024) return `${value} B`
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`
  return `${(value / (1024 * 1024)).toFixed(2)} MB`
}

function emailRecipients(email: any) {
  const rows = Array.isArray(email?.agents) ? email.agents : []
  if (!rows.length) return t('adminRequestDetails.states.emptyValue')
  return rows.map((agent: any) => agent?.name || agent?.email || t('adminRequestDetails.states.emptyValue')).join(', ')
}

function emailStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'delivered') return 'client-badge client-badge--green'
  if (key === 'failed') return 'client-badge client-badge--rose'
  if (key === 'sent') return 'client-badge client-badge--blue'
  return 'client-badge client-badge--amber'
}

function attachmentDownloadUrl(emailId: number | string, attachmentId: number | string) {
  return adminRequestEmailAttachmentDownloadUrl(requestId.value, emailId, attachmentId)
}

function openFilePreview(fileName: string | null | undefined, downloadUrl: string, mimeType?: string | null) {
  const targetUrl = String(downloadUrl || '').trim()
  if (!targetUrl) return
  filePreviewName.value = String(fileName || t('adminRequestDetails.states.emptyValue'))
  filePreviewMime.value = String(mimeType || '')
  fileDownloadUrl.value = targetUrl
  filePreviewUrl.value = buildPreviewUrl(targetUrl)
  filePreviewOpen.value = true
}

async function load() {
  if (!requestId.value) return

  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminRequestDetails(requestId.value)
    requestItem.value = data.request
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.fetchFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminRequestDetails.emailActivity.title') }}</p>
        <h4>{{ t('adminRequestDetails.emailActivity.title') }}</h4>
        <p class="subtext">{{ t('adminRequestDetails.hero.subtitle') }}</p>
      </div>
      <div class="actions-row">
        <RouterLink class="ghost-btn" :to="{ name: 'admin-request-details', params: { id: requestId } }">{{ t('adminRequestDetails.hero.backToQueue') }}</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('adminRequestDetails.states.loading') }}</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <template v-else>
      <RequestSummaryStatGrid :items="summaryItems" />

      <article class="panel-card">
        <div class="panel-head">
          <div>
            <h2>{{ t('adminRequestDetails.emailActivity.title') }}</h2>
            <p class="subtext">{{ t('adminRequestDetails.modal.subtitle') }}</p>
          </div>
            <div class="actions-row">
            <button type="button" class="ghost-btn" :disabled="currentPage <= 1" @click="currentPage -= 1">{{ uiText('Previous', 'السابق') }}</button>
            <span class="client-subtext">{{ uiText('Page', 'صفحة') }} {{ currentPage }} / {{ totalPages }}</span>
            <button type="button" class="ghost-btn" :disabled="currentPage >= totalPages" @click="currentPage += 1">{{ uiText('Next', 'التالي') }}</button>
          </div>
        </div>

        <div v-if="pagedEmails.length" class="timeline-list">
          <article v-for="email in pagedEmails" :key="email.id" class="timeline-item">
            <div class="request-modal-meta">
              <div>
                <strong>{{ email.subject || t('adminRequestDetails.states.emptyValue') }}</strong>
                <p>{{ t('adminRequestDetails.emailActivity.fromLabel') }} {{ email.sender?.name || t('adminRequestDetails.states.system') }} · {{ email.from_email || email.sender?.email || t('adminRequestDetails.states.emptyValue') }}</p>
                <p>{{ t('adminRequestDetails.emailActivity.toLabel') }} {{ emailRecipients(email) }}</p>
                <p v-if="email.body" class="request-prewrap-text">{{ email.body }}</p>
                <span>{{ formatDate(email.sent_at || email.created_at) }} · {{ t('adminRequestDetails.emailActivity.attachmentsCount', { count: email.attachments?.length || 0 }) }}</span>
              </div>
              <span :class="emailStatusClass(email.delivery_status)">{{ formatEmailDeliveryStatus(email.delivery_status, locale, t('adminRequestDetails.emailActivity.queued')) }}</span>
            </div>

            <div v-if="email.attachments?.length" class="file-list request-inline-stack">
              <div v-for="attachment in email.attachments" :key="attachment.id" class="file-item">
                <div>
                  <strong>{{ attachment.file_name }}</strong>
                  <span>{{ attachment.mime_type || attachment.file_extension || t('adminRequestDetails.emailActivity.storedRequestFile') }} · {{ formatFileSize(attachment.file_size) }}</span>
                </div>
                <div class="approve-actions">
                  <button
                    type="button"
                    class="ghost-btn"
                    @click="openFilePreview(attachment.file_name, attachmentDownloadUrl(email.id, attachment.id), attachment.mime_type || attachment.file_extension)"
                  >
                    {{ t('adminRequestDetails.agentAssignments.previewFile') }}
                  </button>
                  <a class="ghost-btn" :href="attachmentDownloadUrl(email.id, attachment.id)" target="_blank" rel="noopener">{{ t('adminRequestDetails.actions.download') }}</a>
                </div>
              </div>
            </div>
          </article>
        </div>
        <p v-else class="empty-state">{{ t('adminRequestDetails.emailActivity.empty') }}</p>
      </article>
    </template>

    <AppFilePreviewModal
      :model-value="filePreviewOpen"
      @update:model-value="(value) => { filePreviewOpen = value }"
      :title="uiText('File preview', 'معاينة الملف')"
      :file-name="filePreviewName"
      :mime-type="filePreviewMime"
      :preview-url="filePreviewUrl"
      :download-url="fileDownloadUrl"
    />
  </section>
</template>
