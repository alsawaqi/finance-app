<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import Editor from 'primevue/editor'
import {
  getStaffRequest,
  getStaffRequestEmailOptions,
  sendStaffRequestEmail,
  type AgentOption,
  type AllowedEmailDocument,
  type BankOption,
  type RequestEmailOptionsPayload,
  type RequestEmailTemplateField,
  type RequestEmailTemplateOption,
} from '@/services/staffWorkspace'
import { intakeFullName } from '@/utils/requestIntake'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'
import { formatRequestStatus } from '@/utils/requestStatus'
import RequestSummaryStatGrid from './inc/RequestSummaryStatGrid.vue'
import AdminQuickViewModal from './inc/AdminQuickViewModal.vue'
import AppFilePreviewModal from '@/components/AppFilePreviewModal.vue'
import { buildPreviewUrl } from '@/utils/filePreview'

const route = useRoute()
const auth = useAuthStore()
const { t, locale } = useI18n()

const requestId = computed(() => String(route.params.id || ''))
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const requestItem = ref<any | null>(null)

const banks = ref<BankOption[]>([])
const agents = ref<AgentOption[]>([])
const allowedEmailDocuments = ref<AllowedEmailDocument[]>([])
const emailTemplates = ref<RequestEmailTemplateOption[]>([])
const hasEmailAssignments = ref(false)
const canEmailAssignedAgents = ref(false)
const loadingEmailOptions = ref(false)
const emailOptionsCache = new Map<string, RequestEmailOptionsPayload>()
let emailOptionsRequestToken = 0

const selectedBankId = ref<number | null>(null)
const selectedAgentId = ref<number | null>(null)
const selectedTemplateId = ref<number | null>(null)
const templateValues = ref<Record<string, string>>({})
const emailSubject = ref('')
const emailBody = ref('')
const selectedEmailDocumentKeys = ref<string[]>([])
const sendingEmail = ref(false)
const recipientPickerOpen = ref(false)
const attachmentPickerOpen = ref(false)
const filePreviewOpen = ref(false)
const filePreviewName = ref('')
const filePreviewMime = ref('')
const filePreviewUrl = ref('')
const fileDownloadUrl = ref('')

const mailboxReady = computed(() => Boolean(
  auth.user?.mailbox_settings?.smtp_enabled
  && auth.user?.mailbox_settings?.smtp_verified_at
  && auth.user?.mailbox_settings?.has_smtp_password,
))
const selectedAgentOption = computed(() => agents.value.find((agent) => agent.id === selectedAgentId.value) ?? null)
const selectedBankOption = computed(() => banks.value.find((bank) => bank.id === selectedBankId.value) ?? null)
const selectedTemplateOption = computed(() => emailTemplates.value.find((template) => template.id === selectedTemplateId.value) ?? null)
const selectedTemplateFields = computed(() => selectedTemplateOption.value?.fields_json ?? [])
const canComposeEmail = computed(() => Boolean(mailboxReady.value && canEmailAssignedAgents.value && selectedAgentId.value))
const selectedEmailAttachments = computed(() =>
  (allowedEmailDocuments.value ?? []).filter((document) => selectedEmailDocumentKeys.value.includes(document.key)),
)
const loadingAllowedEmailDocuments = computed(() => loadingEmailOptions.value && Boolean(selectedAgentId.value))
const sentEmailsCount = computed(() => (Array.isArray(requestItem.value?.emails) ? requestItem.value.emails.length : 0))
const emailBodyTextLength = computed(() => stripHtml(normalizeEditorHtml(emailBody.value)).trim().length)
const templateRequiredFieldsFilled = computed(() =>
  selectedTemplateFields.value.every((field) => !field.required || String(templateValues.value[field.key] || '').trim() !== ''),
)
const renderedTemplateSubject = computed(() =>
  selectedTemplateOption.value ? renderTemplateSubject(selectedTemplateOption.value.subject, templateValues.value) : '',
)
const renderedTemplateBody = computed(() =>
  selectedTemplateOption.value ? renderTemplateBody(selectedTemplateOption.value.body, templateValues.value) : '',
)
const canSendEmail = computed(() => Boolean(
  canComposeEmail.value
  && (
    selectedTemplateOption.value
      ? renderedTemplateSubject.value.trim() && stripHtml(renderedTemplateBody.value).trim() && templateRequiredFieldsFilled.value
      : emailSubject.value.trim() && emailBodyTextLength.value > 0
  ),
))

const summaryItems = computed(() => [
  {
    label: t('staffRequestDetails.summary.client'),
    value: intakeFullName(requestItem.value?.intake_details_json, requestItem.value?.client?.name || t('staffRequestDetails.states.clientFallback')),
    hint: requestItem.value?.reference_number || t('staffRequestDetails.states.emptyValue'),
  },
  {
    label: t('staffRequestDetails.summary.workflow'),
    value: stageMeta(requestItem.value?.workflow_stage).label,
    hint: formatRequestStatus(requestItem.value?.status, locale, t('staffRequestDetails.states.emptyValue')),
  },
  {
    label: uiText('Selected attachments', 'المرفقات المحددة'),
    value: String(selectedEmailAttachments.value.length),
    hint: `${uiText('Allowed files', 'الملفات المسموح بها')}: ${allowedEmailDocuments.value.length}`,
  },
  {
    label: uiText('Sent emails', 'الرسائل المرسلة'),
    value: String(sentEmailsCount.value),
    hint: canComposeEmail.value ? uiText('Ready to send', 'جاهز للإرسال') : uiText('Select recipient first', 'اختر المستلم أولاً'),
  },
])

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

function stripHtml(value: string) {
  return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')
}

function normalizeEditorHtml(value: string) {
  const normalized = String(value || '').replace(/\u200B/g, '').trim()
  if (
    !normalized
    || normalized === '<br>'
    || normalized === '<div><br></div>'
    || normalized === '<p><br></p>'
  ) {
    return ''
  }

  return normalized
}

function clearEmailComposer() {
  emailSubject.value = ''
  emailBody.value = ''
  selectedTemplateId.value = null
  templateValues.value = {}
  selectedEmailDocumentKeys.value = []
}

function openRecipientPicker() {
  recipientPickerOpen.value = true
}

function openAttachmentPicker() {
  if (!canComposeEmail.value) return
  attachmentPickerOpen.value = true
}

function toggleEmailDocument(documentKey: string, checked: boolean) {
  const next = new Set(selectedEmailDocumentKeys.value)
  if (checked) next.add(documentKey)
  else next.delete(documentKey)
  selectedEmailDocumentKeys.value = Array.from(next)
}

function isEmailDocumentChecked(documentKey: string) {
  return selectedEmailDocumentKeys.value.includes(documentKey)
}

function emailOptionsCacheKey(bankId: number | null, agentId: number | null) {
  return `${bankId ?? 'all'}:${agentId ?? 'all'}`
}

function applyEmailOptions(response: RequestEmailOptionsPayload) {
  banks.value = response.banks ?? []
  agents.value = response.agents ?? []
  allowedEmailDocuments.value = response.allowed_documents ?? []
  emailTemplates.value = response.email_templates ?? []
  hasEmailAssignments.value = Boolean(response.has_assignments)
  canEmailAssignedAgents.value = Boolean(response.can_email)

  if (selectedTemplateId.value && !emailTemplates.value.some((template) => template.id === selectedTemplateId.value)) {
    selectedTemplateId.value = null
    templateValues.value = {}
  }
}

function resetTemplateValues(template: RequestEmailTemplateOption | null) {
  if (!template) {
    templateValues.value = {}
    return
  }

  const next: Record<string, string> = {}
  ;(template.fields_json ?? []).forEach((field) => {
    next[field.key] = templateValues.value[field.key] ?? ''
  })
  templateValues.value = next
}

function fieldInputType(field: RequestEmailTemplateField) {
  if (field.type === 'email') return 'email'
  if (field.type === 'number') return 'number'
  if (field.type === 'date') return 'date'
  if (field.type === 'phone') return 'tel'
  return 'text'
}

function renderTemplateSubject(subject: string, values: Record<string, string>) {
  return replaceTemplateTokens(subject, values, false).replace(/\s+/g, ' ').trim()
}

function renderTemplateBody(body: string, values: Record<string, string>) {
  return replaceTemplateTokens(body || '', values, true)
}

function replaceTemplateTokens(template: string, values: Record<string, string>, html: boolean) {
  return String(template || '').replace(/{{\s*([A-Za-z][A-Za-z0-9_]*)\s*}}/g, (_match, key: string) => {
    const value = values[key] ?? ''
    return html ? escapeHtml(value).replace(/\n/g, '<br>') : value.replace(/\s+/g, ' ')
  })
}

function escapeHtml(value: string) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

function openFilePreview(fileName: string | null | undefined, downloadUrl: string, mimeType?: string | null) {
  const targetUrl = String(downloadUrl || '').trim()
  if (!targetUrl) return
  filePreviewName.value = String(fileName || t('staffRequestDetails.states.emptyValue'))
  filePreviewMime.value = String(mimeType || '')
  fileDownloadUrl.value = targetUrl
  filePreviewUrl.value = buildPreviewUrl(targetUrl)
  filePreviewOpen.value = true
}

async function sendEmailToAssignedAgent() {
  if (!requestItem.value || !selectedAgentId.value || !canSendEmail.value) return

  sendingEmail.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await sendStaffRequestEmail(requestItem.value.id, {
      bank_id: selectedBankId.value,
      agent_id: selectedAgentId.value,
      document_keys: selectedEmailDocumentKeys.value,
      ...(selectedTemplateOption.value
        ? {
            email_template_id: selectedTemplateOption.value.id,
            template_values: templateValues.value,
          }
        : {
            subject: emailSubject.value.trim(),
            body: normalizeEditorHtml(emailBody.value) || null,
          }),
    })

    requestItem.value = data.request
    const responseOptions: RequestEmailOptionsPayload = {
      banks: data.banks ?? banks.value,
      agents: data.agents ?? agents.value,
      allowed_documents: data.allowed_documents ?? allowedEmailDocuments.value,
      email_templates: data.email_templates ?? emailTemplates.value,
      has_assignments: Boolean(data.has_assignments),
      can_email: Boolean(data.can_email),
    }
    emailOptionsCache.set(emailOptionsCacheKey(selectedBankId.value, selectedAgentId.value), responseOptions)
    applyEmailOptions(responseOptions)
    clearEmailComposer()
    attachmentPickerOpen.value = false
    successMessage.value = data.message || uiText('Email sent successfully.', 'تم إرسال البريد بنجاح.')
  } catch (error: any) {
    const validationErrors = error?.response?.data?.errors
    if (validationErrors && typeof validationErrors === 'object') {
      const firstField = Object.keys(validationErrors)[0]
      const firstMessage = Array.isArray(validationErrors[firstField]) ? validationErrors[firstField][0] : validationErrors[firstField]
      errorMessage.value = firstMessage || error?.response?.data?.message || uiText('Failed to send the email.', 'تعذر إرسال البريد.')
    } else {
      errorMessage.value = error?.response?.data?.message || uiText('Failed to send the email.', 'تعذر إرسال البريد.')
    }
  } finally {
    sendingEmail.value = false
  }
}

async function loadEmailOptions(force = false) {
  const cacheKey = emailOptionsCacheKey(selectedBankId.value, selectedAgentId.value)

  if (!force) {
    const cached = emailOptionsCache.get(cacheKey)
    if (cached) {
      applyEmailOptions(cached)
      return
    }
  }

  const requestToken = ++emailOptionsRequestToken
  loadingEmailOptions.value = true

  try {
    const response = await getStaffRequestEmailOptions(requestId.value, {
      bank_id: selectedBankId.value,
      agent_id: selectedAgentId.value,
    })

    emailOptionsCache.set(cacheKey, response)

    if (requestToken !== emailOptionsRequestToken) {
      return
    }

    applyEmailOptions(response)
  } finally {
    if (requestToken === emailOptionsRequestToken) {
      loadingEmailOptions.value = false
    }
  }
}

async function load() {
  if (!requestId.value) return

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const [requestResponse] = await Promise.all([getStaffRequest(requestId.value), loadEmailOptions(true)])
    requestItem.value = requestResponse.request ?? null
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequestDetails.errors.fetchFailed')
  } finally {
    loading.value = false
  }
}

watch(selectedBankId, async () => {
  selectedAgentId.value = null
  selectedEmailDocumentKeys.value = []
  await loadEmailOptions()
})

watch(selectedAgentId, async () => {
  selectedEmailDocumentKeys.value = []
  await loadEmailOptions()
})

watch(selectedTemplateId, () => {
  resetTemplateValues(selectedTemplateOption.value)
})

watch(requestId, () => {
  clearEmailComposer()
  emailOptionsCache.clear()
  emailOptionsRequestToken += 1
  selectedBankId.value = null
  selectedAgentId.value = null
  load()
})

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('staffRequestDetails.sections.emailComposerTitle') }}</p>
        <h4>{{ uiText('Send request email', 'إرسال بريد الطلب') }}</h4>
        <p class="subtext">{{ t('staffRequestDetails.sections.emailComposerSubtitle') }}</p>
      </div>
      <div class="actions-row">
        <RouterLink class="ghost-btn" :to="{ name: 'staff-request-details', params: { id: requestId } }">{{ t('staffRequestDetails.hero.backToAssignedRequests') }}</RouterLink>
        <RouterLink class="ghost-btn" :to="{ name: 'staff-request-emails', params: { id: requestId } }">{{ uiText('Sent email history', 'سجل الرسائل المرسلة') }}</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('staffRequestDetails.states.loading') }}</p>
    <p v-else-if="errorMessage && !requestItem" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>
    <p v-if="errorMessage && requestItem" class="error-state">{{ errorMessage }}</p>

    <template v-if="!loading && requestItem">
      <RequestSummaryStatGrid :items="summaryItems" />

      <article class="panel-card staff-email-page">
        <div class="panel-head staff-email-composer-head">
          <div>
            <h2>{{ t('staffRequestDetails.sections.emailBody') }}</h2>
            <p class="subtext">{{ uiText('Set recipients first, then compose a full email with approved request files.', 'حدّد المستلمين أولاً ثم اكتب بريدًا كاملاً مع ملفات الطلب المعتمدة.') }}</p>
          </div>
          <div class="staff-email-composer-actions">
            <button type="button" class="ghost-btn" @click="openRecipientPicker">
              <i class="fas fa-user-check"></i>
              {{ t('staffRequestDetails.sections.recipients') }}
            </button>
            <button type="button" class="ghost-btn" :disabled="!canComposeEmail || loadingAllowedEmailDocuments" @click="openAttachmentPicker">
              <i class="fas fa-paperclip"></i>
              {{ uiText('Attachments', 'المرفقات') }} ({{ selectedEmailAttachments.length }})
            </button>
          </div>
        </div>

        <div v-if="!mailboxReady" class="notes-box">
          <span>{{ t('staffRequestDetails.mailboxSetup.title') }}</span>
          <p>{{ uiText('The admin still needs to save and verify your mailbox before you can send request emails from this workspace.', 'لا تزال الإدارة بحاجة إلى حفظ بريدك والتحقق منه قبل أن تتمكن من إرسال رسائل الطلب من مساحة العمل هذه.') }}</p>
        </div>

        <div v-if="!hasEmailAssignments" class="notes-box">
          <span>{{ uiText('Waiting for admin setup', 'بانتظار إعداد الإدارة') }}</span>
          <p>{{ uiText('The admin still needs to approve the bank-agent assignment phase before you can prepare a controlled email for this request.', 'لا تزال الإدارة بحاجة لاعتماد مرحلة تعيين البنك والوكيل قبل أن تتمكن من إعداد بريد مُتحكم به لهذا الطلب.') }}</p>
        </div>

        <div class="staff-email-recipient-summary" :class="{ 'is-ready': canComposeEmail }">
          <p v-if="canComposeEmail">
            {{ uiText('Sending to', 'الإرسال إلى') }}:
            <strong>{{ selectedAgentOption?.name || uiText('Selected agent', 'الوكيل المحدد') }}</strong>
            <template v-if="selectedBankOption?.name"> - {{ selectedBankOption.name }}</template>
          </p>
          <p v-else>{{ uiText('Select a recipient before entering your email content.', 'اختر مستلمًا قبل إدخال محتوى البريد.') }}</p>
        </div>

        <p v-if="loadingAllowedEmailDocuments" class="staff-loading-note">
          <span class="staff-inline-spinner" aria-hidden="true"></span>
          {{ uiText('Loading the approved attachments for this agent...', 'جارٍ تحميل المرفقات المعتمدة لهذا الوكيل...') }}
        </p>

        <div class="staff-email-input-stack">
          <label class="client-form-group">
            <span class="client-form-label">{{ uiText('Email template', 'قالب البريد') }}</span>
            <select v-model="selectedTemplateId" class="admin-select" :disabled="!canComposeEmail">
              <option :value="null">{{ uiText('Free compose', 'كتابة حرة') }}</option>
              <option v-for="template in emailTemplates" :key="template.id" :value="template.id">
                {{ template.name }}
              </option>
            </select>
          </label>

          <div v-if="selectedTemplateOption" class="staff-template-composer">
            <div class="staff-template-composer__head">
              <div>
                <span class="client-form-label">{{ selectedTemplateOption.name }}</span>
                <p class="client-subtext">{{ uiText('Fill the editable slots approved by admin.', 'املأ الحقول التي سمحت بها الإدارة.') }}</p>
              </div>
              <span class="count-pill">{{ selectedTemplateFields.length }} {{ uiText('slot(s)', 'حقل') }}</span>
            </div>

            <div v-if="selectedTemplateFields.length" class="staff-template-fields">
              <label v-for="field in selectedTemplateFields" :key="field.key" class="client-form-group">
                <span class="client-form-label">
                  {{ field.label }}
                  <template v-if="field.required">*</template>
                </span>
                <textarea
                  v-if="field.type === 'textarea'"
                  v-model="templateValues[field.key]"
                  rows="4"
                  class="admin-textarea"
                  :placeholder="field.placeholder || field.label"
                  :disabled="!canComposeEmail"
                ></textarea>
                <input
                  v-else
                  v-model="templateValues[field.key]"
                  :type="fieldInputType(field)"
                  class="admin-input"
                  :placeholder="field.placeholder || field.label"
                  :disabled="!canComposeEmail"
                />
                <small v-if="field.help_text" class="client-subtext">{{ field.help_text }}</small>
              </label>
            </div>

            <div class="staff-template-preview">
              <span class="client-form-label">{{ t('staffRequestDetails.placeholders.emailSubject') }}</span>
              <strong>{{ renderedTemplateSubject || uiText('Subject preview', 'معاينة العنوان') }}</strong>
              <span class="client-form-label">{{ t('staffRequestDetails.sections.emailBody') }}</span>
              <div class="staff-template-preview__body" v-html="renderedTemplateBody || '<p></p>'"></div>
            </div>
          </div>

          <template v-else>
            <label class="client-form-group">
              <span class="client-form-label">{{ t('staffRequestDetails.placeholders.emailSubject') }}</span>
              <input
                v-model="emailSubject"
                type="text"
                class="admin-input"
                :placeholder="t('staffRequestDetails.placeholders.emailSubject')"
                :disabled="!canComposeEmail"
              />
            </label>

            <Editor
              v-model="emailBody"
              class="staff-prime-editor"
              :readonly="!canComposeEmail"
              :placeholder="t('staffRequestDetails.placeholders.emailBody')"
              editor-style="height: 320px;"
            />
          </template>

          <div class="staff-email-attachments-meta">
            <span class="count-pill">{{ selectedEmailAttachments.length }} {{ uiText('selected', 'محدد') }}</span>
            <p v-if="loadingAllowedEmailDocuments" class="client-subtext">
              {{ uiText('Fetching approved attachments. Please wait before selecting files.', 'Ø¬Ø§Ø±Ù Ø¬Ù„Ø¨ Ø§Ù„Ù…Ø±ÙÙ‚Ø§Øª Ø§Ù„Ù…Ø¹ØªÙ…Ø¯Ø©. ÙŠØ±Ø¬Ù‰ Ø§Ù„Ø§Ù†ØªØ¸Ø§Ø± Ù‚Ø¨Ù„ Ø§Ø®ØªÙŠØ§Ø± Ø§Ù„Ù…Ù„ÙØ§Øª.') }}
            </p>
            <p v-else-if="selectedEmailAttachments.length" class="client-subtext">
              {{ selectedEmailAttachments.map((document) => document.label).join(', ') }}
            </p>
            <p v-else class="client-subtext">{{ uiText('No attachments selected yet.', 'لم يتم اختيار أي مرفقات بعد.') }}</p>
          </div>
        </div>

        <div class="approve-actions staff-email-send-row">
          <button class="primary-btn" type="button" :disabled="sendingEmail || !canSendEmail" @click="sendEmailToAssignedAgent">
            {{ sendingEmail ? uiText('Sending...', 'جارٍ الإرسال...') : uiText('Send email now', 'إرسال البريد الآن') }}
          </button>
          <RouterLink class="ghost-btn" :to="{ name: 'staff-request-emails', params: { id: requestId } }">{{ uiText('View sent email history', 'عرض سجل الرسائل المرسلة') }}</RouterLink>
        </div>
      </article>

      <AdminQuickViewModal
        :model-value="recipientPickerOpen"
        @update:model-value="(value) => { recipientPickerOpen = value }"
        :title="t('staffRequestDetails.sections.recipients')"
        :subtitle="uiText('Pick bank and agent before composing.', 'اختر البنك والوكيل قبل بدء كتابة البريد.')"
        wide
      >
        <div class="staff-picker-grid">
          <label class="client-form-group">
            <span class="client-form-label">{{ t('staffRequestDetails.form.bank') }}</span>
            <select v-model="selectedBankId" class="admin-select">
              <option :value="null">{{ t('staffRequestDetails.form.allBanks') }}</option>
              <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
            </select>
          </label>

          <label class="client-form-group">
            <span class="client-form-label">{{ t('staffRequestDetails.form.agents') }}</span>
            <select v-model="selectedAgentId" class="admin-select">
              <option :value="null">{{ uiText('Select an assigned agent', 'اختر وكيلاً مُسندًا') }}</option>
              <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                {{ agent.name }}<template v-if="agent.bank_name"> - {{ agent.bank_name }}</template>
              </option>
            </select>
          </label>

          <p class="client-subtext">{{ uiText('Only admin-approved agents appear in this list.', 'تظهر في هذه القائمة فقط الوكلاء المعتمدون من الإدارة.') }}</p>
          <p v-if="loadingEmailOptions" class="client-subtext staff-loading-note">
            <span class="staff-inline-spinner" aria-hidden="true"></span>
            {{
              selectedAgentId
                ? uiText('Loading approved attachments for the selected agent...', 'جارٍ تحميل المرفقات المعتمدة للوكيل المحدد...')
                : uiText('Loading available agents...', 'جارٍ تحميل الوكلاء المتاحين...')
            }}
          </p>
          <div class="approve-actions">
            <button type="button" class="primary-btn" :disabled="!selectedAgentId" @click="recipientPickerOpen = false">{{ uiText('Use this recipient', 'استخدام هذا المستلم') }}</button>
          </div>
        </div>
      </AdminQuickViewModal>

      <AdminQuickViewModal
        :model-value="attachmentPickerOpen"
        @update:model-value="(value) => { attachmentPickerOpen = value }"
        :title="uiText('Choose attachments', 'اختيار المرفقات')"
        :subtitle="uiText('Select approved files to include with this email.', 'اختر الملفات المعتمدة لإرفاقها مع هذا البريد.')"
        wide
      >
        <div v-if="loadingAllowedEmailDocuments" class="staff-loading-state">
          <span class="staff-inline-spinner staff-inline-spinner--large" aria-hidden="true"></span>
          <p>{{ uiText('Loading approved attachments. Please wait...', 'جارٍ تحميل المرفقات المعتمدة. يرجى الانتظار...') }}</p>
        </div>
        <div v-else-if="allowedEmailDocuments.length" class="timeline-list compact-list">
          <label v-for="document in allowedEmailDocuments" :key="document.key" class="timeline-item staff-picker-item">
            <input
              type="checkbox"
              :checked="isEmailDocumentChecked(document.key)"
              :disabled="!canComposeEmail"
              @change="toggleEmailDocument(document.key, ($event.target as HTMLInputElement).checked)"
            >
            <div>
              <strong>{{ document.label }}</strong>
              <p>{{ document.group_label || uiText('Request file', 'ملف الطلب') }}</p>
              <span>{{ document.file_name }}</span>
              <div v-if="document.download_url" class="approve-actions staff-picker-preview">
                <button
                  type="button"
                  class="ghost-btn"
                  @click="openFilePreview(document.file_name, document.download_url)"
                >
                  {{ uiText('Preview file', 'معاينة الملف') }}
                </button>
                <a :href="document.download_url" target="_blank" rel="noopener" class="ghost-btn">{{ uiText('Download', 'تنزيل') }}</a>
              </div>
            </div>
          </label>
        </div>
        <p v-else class="empty-state">
          {{
            selectedAgentId
              ? uiText('No files are currently assigned to this agent.', 'لا توجد ملفات مُسندة لهذا الوكيل حالياً.')
              : uiText('Select an assigned agent first to load the approved attachments.', 'اختر الوكيل المُسند أولاً لتحميل المرفقات المعتمدة.')
          }}
        </p>
        <div class="approve-actions" style="margin-top: 0.85rem;">
          <button type="button" class="primary-btn" @click="attachmentPickerOpen = false">{{ uiText('Done', 'تم') }}</button>
        </div>
      </AdminQuickViewModal>

      <AppFilePreviewModal
        :model-value="filePreviewOpen"
        @update:model-value="(value) => { filePreviewOpen = value }"
        :title="uiText('File preview', 'معاينة الملف')"
        :file-name="filePreviewName"
        :mime-type="filePreviewMime"
        :preview-url="filePreviewUrl"
        :download-url="fileDownloadUrl"
      />
    </template>
  </section>
</template>

<style scoped>
.staff-email-page {
  display: grid;
  gap: 1rem;
}

.staff-email-composer-head {
  align-items: flex-start;
}

.staff-email-composer-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.staff-email-recipient-summary {
  padding: 0.85rem 1rem;
  border-radius: 14px;
  border: 1px dashed rgba(148, 163, 184, 0.35);
  background: rgba(248, 250, 252, 0.72);
}

.staff-email-recipient-summary.is-ready {
  border-style: solid;
  border-color: rgba(16, 185, 129, 0.28);
  background: rgba(236, 253, 245, 0.8);
}

.staff-email-recipient-summary p {
  margin: 0;
}

.staff-loading-note {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.staff-email-input-stack {
  display: grid;
  gap: 0.85rem;
}

.staff-template-composer {
  display: grid;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 14px;
  background: rgba(248, 250, 252, 0.7);
}

.staff-template-composer__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.8rem;
  flex-wrap: wrap;
}

.staff-template-composer__head p {
  margin: 0.25rem 0 0;
}

.staff-template-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem;
}

.staff-template-preview {
  display: grid;
  gap: 0.55rem;
}

.staff-template-preview > strong {
  display: block;
  padding: 0.8rem 0.9rem;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid rgba(148, 163, 184, 0.22);
  color: var(--admin-text);
}

.staff-template-preview__body {
  min-height: 180px;
  padding: 1rem;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid rgba(148, 163, 184, 0.22);
  color: var(--admin-text);
  line-height: 1.65;
}

.staff-prime-editor {
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, 0.3);
}

.staff-prime-editor:deep(.p-editor-toolbar) {
  border: none;
  border-bottom: 1px solid rgba(148, 163, 184, 0.24);
  background: rgba(248, 250, 252, 0.9);
}

.staff-prime-editor:deep(.p-editor-content) {
  border: none;
  background: #ffffff;
}

.staff-prime-editor:deep(.ql-editor) {
  color: var(--admin-text);
  line-height: 1.65;
  font-size: 0.96rem;
}

.staff-prime-editor:deep(.ql-container.ql-disabled) {
  background: rgba(248, 250, 252, 0.95);
}

.staff-email-attachments-meta {
  display: grid;
  gap: 0.45rem;
}

.staff-loading-state {
  min-height: 180px;
  display: grid;
  place-items: center;
  gap: 0.75rem;
  text-align: center;
  color: var(--admin-text-light);
}

.staff-inline-spinner {
  width: 1rem;
  height: 1rem;
  border-radius: 999px;
  border: 2px solid rgba(148, 163, 184, 0.35);
  border-top-color: var(--admin-primary);
  animation: staff-spin 0.8s linear infinite;
  flex: 0 0 auto;
}

.staff-inline-spinner--large {
  width: 1.8rem;
  height: 1.8rem;
  border-width: 3px;
}

.staff-email-send-row {
  margin-top: 0.4rem;
  gap: 0.7rem;
  flex-wrap: wrap;
}

.staff-picker-grid {
  display: grid;
  gap: 0.9rem;
}

.staff-picker-item {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: start;
  gap: 0.8rem;
  cursor: pointer;
}

.staff-picker-item > input {
  margin-top: 0.2rem;
  width: 18px;
  height: 18px;
  accent-color: var(--admin-primary);
}

.staff-picker-preview {
  margin-top: 0.45rem;
}

@keyframes staff-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 768px) {
  .staff-email-composer-actions {
    width: 100%;
  }

  .staff-email-composer-actions .ghost-btn {
    width: 100%;
  }

  .staff-template-fields {
    grid-template-columns: 1fr;
  }
}
</style>
