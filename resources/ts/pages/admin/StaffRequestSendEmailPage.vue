<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  getStaffRequest,
  getStaffRequestEmailOptions,
  sendStaffRequestEmail,
  type AgentOption,
  type AllowedEmailDocument,
  type BankOption,
} from '@/services/staffWorkspace'
import { adminContractDownloadUrl } from '@/services/adminRequests'
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
const hasEmailAssignments = ref(false)
const canEmailAssignedAgents = ref(false)

const selectedBankId = ref<number | null>(null)
const selectedAgentId = ref<number | null>(null)
const emailSubject = ref('')
const emailBody = ref('')
const selectedEmailDocumentKeys = ref<string[]>([])
const sendingEmail = ref(false)
const recipientPickerOpen = ref(false)
const attachmentPickerOpen = ref(false)
const emailEditorRef = ref<HTMLElement | null>(null)
const emailEditorFocused = ref(false)
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
const canComposeEmail = computed(() => Boolean(mailboxReady.value && canEmailAssignedAgents.value && selectedAgentId.value))
const selectedEmailAttachments = computed(() =>
  (allowedEmailDocuments.value ?? []).filter((document) => selectedEmailDocumentKeys.value.includes(document.key)),
)
const sentEmailsCount = computed(() => (Array.isArray(requestItem.value?.emails) ? requestItem.value.emails.length : 0))
const emailBodyTextLength = computed(() => stripHtml(emailBody.value).trim().length)
const canSendEmail = computed(() => Boolean(
  canComposeEmail.value
  && emailSubject.value.trim()
  && selectedEmailDocumentKeys.value.length > 0
  && emailBodyTextLength.value > 0,
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
  if (!normalized || normalized === '<br>' || normalized === '<div><br></div>') {
    return ''
  }

  return normalized
}

function syncEmailBodyFromEditor() {
  if (!emailEditorRef.value) return
  emailBody.value = normalizeEditorHtml(emailEditorRef.value.innerHTML)
}

function applyEmailEditorCommand(command: string, value?: string) {
  if (!canComposeEmail.value || !emailEditorRef.value) return
  emailEditorRef.value.focus()
  document.execCommand(command, false, value)
  syncEmailBodyFromEditor()
}

function clearEmailComposer() {
  emailSubject.value = ''
  emailBody.value = ''
  selectedEmailDocumentKeys.value = []

  if (emailEditorRef.value) {
    emailEditorRef.value.innerHTML = ''
  }
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
      subject: emailSubject.value.trim(),
      body: emailBody.value.trim() || null,
    })

    requestItem.value = data.request
    banks.value = data.banks ?? banks.value
    agents.value = data.agents ?? agents.value
    allowedEmailDocuments.value = data.allowed_documents ?? allowedEmailDocuments.value
    hasEmailAssignments.value = Boolean(data.has_assignments)
    canEmailAssignedAgents.value = Boolean(data.can_email)
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

async function loadEmailOptions() {
  const response = await getStaffRequestEmailOptions(requestId.value, {
    bank_id: selectedBankId.value,
    agent_id: selectedAgentId.value,
  })

  banks.value = response.banks ?? []
  agents.value = response.agents ?? []
  allowedEmailDocuments.value = response.allowed_documents ?? []
  hasEmailAssignments.value = Boolean(response.has_assignments)
  canEmailAssignedAgents.value = Boolean(response.can_email)
}

async function load() {
  if (!requestId.value) return

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const [requestResponse] = await Promise.all([getStaffRequest(requestId.value), loadEmailOptions()])
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

watch(requestId, () => {
  clearEmailComposer()
  selectedBankId.value = null
  selectedAgentId.value = null
  load()
})

watch(canComposeEmail, async () => {
  await nextTick()
  if (emailEditorRef.value && emailEditorRef.value.innerHTML !== emailBody.value) {
    emailEditorRef.value.innerHTML = emailBody.value
  }
})

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('staffRequestDetails.sections.emailComposerTitle') }}</p>
        <h1>{{ uiText('Send request email', 'إرسال بريد الطلب') }}</h1>
        <p class="subtext">{{ t('staffRequestDetails.sections.emailComposerSubtitle') }}</p>
      </div>
      <div class="actions-row">
        <RouterLink class="ghost-btn" :to="{ name: 'staff-request-details', params: { id: requestId } }">{{ t('staffRequestDetails.hero.backToAssignedRequests') }}</RouterLink>
        <RouterLink class="ghost-btn" :to="{ name: 'staff-request-emails', params: { id: requestId } }">{{ uiText('Sent email history', 'سجل الرسائل المرسلة') }}</RouterLink>
        <button
          v-if="requestItem?.current_contract?.contract_pdf_path"
          type="button"
          class="ghost-btn"
          @click="openFilePreview(`contract-${requestId}.pdf`, adminContractDownloadUrl(requestId), 'application/pdf')"
        >
          {{ uiText('Preview contract', 'معاينة العقد') }}
        </button>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.hero.downloadContractPdf') }}</a>
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
            <button type="button" class="ghost-btn" :disabled="!canComposeEmail" @click="openAttachmentPicker">
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

        <div class="staff-email-input-stack">
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

          <div class="staff-email-editor-shell" :class="{ 'is-disabled': !canComposeEmail, 'is-focused': emailEditorFocused }">
            <div class="staff-email-editor-toolbar">
              <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('bold')"><strong>B</strong></button>
              <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('italic')"><em>I</em></button>
              <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('insertUnorderedList')"><i class="fas fa-list-ul"></i></button>
              <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('insertOrderedList')"><i class="fas fa-list-ol"></i></button>
              <button type="button" class="small-btn ghost-btn" :disabled="!canComposeEmail" @click="applyEmailEditorCommand('removeFormat')"><i class="fas fa-eraser"></i></button>
            </div>

            <div
              ref="emailEditorRef"
              class="staff-email-editor-surface"
              :contenteditable="canComposeEmail ? 'true' : 'false'"
              :data-placeholder="t('staffRequestDetails.placeholders.emailBody')"
              @focus="emailEditorFocused = true"
              @blur="emailEditorFocused = false; syncEmailBodyFromEditor()"
              @input="syncEmailBodyFromEditor"
            ></div>
          </div>

          <div class="staff-email-attachments-meta">
            <span class="count-pill">{{ selectedEmailAttachments.length }} {{ uiText('selected', 'محدد') }}</span>
            <p v-if="selectedEmailAttachments.length" class="client-subtext">
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
        <div v-if="allowedEmailDocuments.length" class="timeline-list compact-list">
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
        <p v-else class="empty-state">{{ uiText('No files are currently assigned to this agent.', 'لا توجد ملفات مُسندة لهذا الوكيل حالياً.') }}</p>
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

.staff-email-input-stack {
  display: grid;
  gap: 0.85rem;
}

.staff-email-editor-shell {
  border: 1px solid rgba(148, 163, 184, 0.3);
  border-radius: 16px;
  overflow: hidden;
  background: #ffffff;
}

.staff-email-editor-shell.is-focused {
  border-color: rgba(79, 70, 229, 0.45);
  box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
}

.staff-email-editor-shell.is-disabled {
  opacity: 0.78;
  background: rgba(248, 250, 252, 0.95);
}

.staff-email-editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding: 0.6rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.2);
  background: rgba(248, 250, 252, 0.9);
}

.staff-email-editor-surface {
  min-height: 300px;
  padding: 1rem;
  outline: none;
  line-height: 1.6;
  color: var(--admin-text);
}

.staff-email-editor-surface:empty::before {
  content: attr(data-placeholder);
  color: var(--admin-text-light);
}

.staff-email-attachments-meta {
  display: grid;
  gap: 0.45rem;
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

@media (max-width: 768px) {
  .staff-email-editor-surface {
    min-height: 220px;
  }

  .staff-email-composer-actions {
    width: 100%;
  }

  .staff-email-composer-actions .ghost-btn {
    width: 100%;
  }
}
</style>
