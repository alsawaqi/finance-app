<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import SignaturePad from 'signature_pad'
import { useI18n } from 'vue-i18n'
import {
  adminContractCommercialDownloadUrl,
  adminContractDownloadUrl,
  getAdminContract,
  saveAdminContract,
  uploadAdminCommercialContract,
} from '@/services/adminRequests'
import { countryNameFromCode } from '@/utils/countries'
import {
  intakeCompanyCrNumber,
  intakeCountryCode,
  intakeFinanceType,
  intakeFullName,
  intakeRequestedAmount,
  intakeUnifiedNumber,
} from '@/utils/requestIntake'
import { formatDateTime } from '@/utils/dateTime'

const route = useRoute()
const router = useRouter()
const requestId = computed(() => route.params.id as string)
const { t, locale } = useI18n()

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const financeRequest = ref<any | null>(null)
const contract = ref<any | null>(null)
const contractTemplate = ref<any | null>(null)
const draftContractHtml = ref('')
const initialDraftContractHtml = ref('')
const editorRef = ref<HTMLDivElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const uploadedContractFile = ref<File | null>(null)
const requiresCommercialRegistration = ref(false)
const adminCommercialFile = ref<File | null>(null)
const uploadingAdminCommercial = ref(false)
let signaturePad: SignaturePad | null = null

function buildSignaturePad() {
  const canvas = canvasRef.value
  if (!canvas) return

  if (signaturePad) {
    signaturePad.off()
    signaturePad = null
  }

  signaturePad = new SignaturePad(canvas, {
    backgroundColor: 'rgb(255,255,255)',
    penColor: 'rgb(15,23,42)',
    minWidth: 0.8,
    maxWidth: 2.2,
  })
}

function resizeCanvas(preserveDrawing = true) {
  const canvas = canvasRef.value
  if (!canvas) return

  const existingData = preserveDrawing && signaturePad && !signaturePad.isEmpty()
    ? signaturePad.toData()
    : null

  const ratio = Math.max(window.devicePixelRatio || 1, 1)
  const rect = canvas.getBoundingClientRect()
  if (!rect.width || !rect.height) return

  canvas.width = rect.width * ratio
  canvas.height = rect.height * ratio

  const ctx = canvas.getContext('2d')
  if (!ctx) return

  ctx.setTransform(1, 0, 0, 1, 0, 0)
  ctx.scale(ratio, ratio)

  buildSignaturePad()
  signaturePad?.clear()

  if (existingData && signaturePad) {
    signaturePad.fromData(existingData)
  }
}

async function initSignatureSurface() {
  await nextTick()
  requestAnimationFrame(() => {
    resizeCanvas(false)
  })
}

function syncEditorHtml() {
  if (editorRef.value && editorRef.value.innerHTML !== draftContractHtml.value) {
    editorRef.value.innerHTML = draftContractHtml.value
  }
}

function handleEditorInput() {
  draftContractHtml.value = editorRef.value?.innerHTML || ''
}

function hasVisibleContractContent(value: unknown) {
  const normalized = String(value ?? '')
    .replace(/<br\s*\/?>/gi, '')
    .replace(/&nbsp;/gi, '')
    .replace(/<[^>]*>/g, '')
    .trim()

  return normalized.length > 0
}

const bypassMode = computed(() => Boolean(uploadedContractFile.value))

const requiresAdminCommercialUpload = computed(() =>
  Boolean(
    contract.value?.requires_commercial_registration
    && contract.value?.client_commercial_contract_path
    && !contract.value?.admin_commercial_contract_path,
  ),
)

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

function readableDateTime(value: unknown) {
  return formatDateTime(value, locale, uiText('Pending', 'بانتظار الإجراء'))
}

function onUploadedContractChange(event: Event) {
  const input = event.target as HTMLInputElement | null
  uploadedContractFile.value = input?.files?.[0] ?? null
  if (uploadedContractFile.value) {
    requiresCommercialRegistration.value = false
  }
}

function clearUploadedContract() {
  uploadedContractFile.value = null
}

function onAdminCommercialFileChange(event: Event) {
  const input = event.target as HTMLInputElement | null
  adminCommercialFile.value = input?.files?.[0] ?? null
}

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminContract(requestId.value)
    financeRequest.value = data.request ?? null
    contract.value = data.contract ?? null
    contractTemplate.value = data.contract_template ?? null
    const savedContractBody = data.contract?.contract_content ?? ''
    const generatedDraftBody = data.draft_contract_html ?? ''

    draftContractHtml.value = hasVisibleContractContent(savedContractBody)
      ? String(savedContractBody)
      : hasVisibleContractContent(generatedDraftBody)
        ? String(generatedDraftBody)
        : String(savedContractBody || generatedDraftBody || '')
    initialDraftContractHtml.value = hasVisibleContractContent(generatedDraftBody)
      ? String(generatedDraftBody)
      : draftContractHtml.value
    requiresCommercialRegistration.value = Boolean(data.contract?.requires_commercial_registration)
    uploadedContractFile.value = null
    adminCommercialFile.value = null
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminContractBuilderPage.errors.loadFailed')
  } finally {
    loading.value = false
  }

  if (!financeRequest.value) return

  await nextTick()
  syncEditorHtml()
  await initSignatureSurface()
}

function clearSignature() {
  signaturePad?.clear()
}

function resetToTemplate() {
  draftContractHtml.value = initialDraftContractHtml.value
  syncEditorHtml()
}

async function saveContract() {
  if (!financeRequest.value) return

  const selectedUpload = uploadedContractFile.value
  if (!selectedUpload && !contractTemplate.value) return

  if (!selectedUpload) {
    if (!signaturePad || signaturePad.isEmpty()) {
      errorMessage.value = t('adminContractBuilderPage.errors.signBeforeSaving')
      return
    }

    const contractBodyHtml = (editorRef.value?.innerHTML || '').trim()
    if (!contractBodyHtml) {
      errorMessage.value = t('adminContractBuilderPage.errors.contractBodyEmpty')
      return
    }
  }

  saving.value = true
  errorMessage.value = ''

  try {
    if (selectedUpload) {
      await saveAdminContract(financeRequest.value.id, {
        uploaded_contract_file: selectedUpload,
      })
    } else {
      await saveAdminContract(financeRequest.value.id, {
        contract_template_slug: contractTemplate.value.slug,
        contract_body_html: (editorRef.value?.innerHTML || '').trim(),
        signature_data_url: signaturePad!.toDataURL('image/png'),
        requires_commercial_registration: requiresCommercialRegistration.value,
      })
    }

    await router.push({ name: 'admin-request-details', params: { id: financeRequest.value.id } })
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminContractBuilderPage.errors.saveFailed')
  } finally {
    saving.value = false
  }
}

async function submitAdminCommercialContract() {
  if (!financeRequest.value || !adminCommercialFile.value || uploadingAdminCommercial.value) return

  uploadingAdminCommercial.value = true
  errorMessage.value = ''

  try {
    await uploadAdminCommercialContract(financeRequest.value.id, {
      file: adminCommercialFile.value,
    })

    await router.push({ name: 'admin-request-details', params: { id: financeRequest.value.id } })
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Failed to upload the admin commercial registration contract.', 'تعذر رفع عقد التوثيق الإداري.')
  } finally {
    uploadingAdminCommercial.value = false
  }
}

function handleWindowResize() {
  resizeCanvas(true)
}

onMounted(() => {
  load()
  window.addEventListener('resize', handleWindowResize, { passive: true })
})

onUnmounted(() => {
  signaturePad?.off()
  window.removeEventListener('resize', handleWindowResize)
})
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminContractBuilderPage.hero.eyebrow') }}</p>
        <h4>{{ t('adminContractBuilderPage.hero.title') }}</h4>
        <p class="subtext">{{ t('adminContractBuilderPage.hero.subtitle') }}</p>
      </div>
      <div class="actions-row">
        <RouterLink :to="{ name: 'admin-request-details', params: { id: requestId } }" class="ghost-btn">{{ t('adminContractBuilderPage.actions.backToDetails') }}</RouterLink>
        <a v-if="financeRequest?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminContractBuilderPage.actions.downloadCurrentPdf') }}</a>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('adminContractBuilderPage.states.loading') }}</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <div v-else-if="financeRequest" class="contract-grid">
      <article class="panel-card info-card">
        <div class="panel-head"><h2>{{ t('adminContractBuilderPage.sections.requestSummary') }}</h2></div>
        <div class="summary-grid">
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.requestRef') }}</span><strong>{{ financeRequest.reference_number }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.approvalRef') }}</span><strong>{{ financeRequest.approval_reference_number || t('adminContractBuilderPage.states.pendingApproval') }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.client') }}</span><strong>{{ intakeFullName(financeRequest.intake_details_json, financeRequest.client?.name || t('adminContractBuilderPage.states.clientFallback')) }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.country') }}</span><strong>{{ countryNameFromCode(financeRequest.country_code || intakeCountryCode(financeRequest.intake_details_json), locale) }}</strong></div>
              <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.requestedAmount') }}</span><strong>{{ intakeRequestedAmount(financeRequest.intake_details_json, '-', true) }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.financeType') }}</span><strong>{{ intakeFinanceType(financeRequest.intake_details_json, t('adminContractBuilderPage.states.emptyValue'), locale) }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.unifiedNumber') }}</span><strong>{{ intakeUnifiedNumber(financeRequest.intake_details_json) }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.companyCrNumber') }}</span><strong>{{ intakeCompanyCrNumber(financeRequest.intake_details_json) }}</strong></div>
        </div>
      </article>

      <article class="panel-card contract-form-card">
        <div class="panel-head">
          <h2>{{ t('adminContractBuilderPage.sections.editableContractTemplate') }}</h2>
        </div>

        <div class="contract-mode-grid">
          <article class="mode-card">
            <h3>{{ uiText('Admin attachment (skip client signature)', 'مرفق إداري (تجاوز توقيع العميل)') }}</h3>
            <p class="muted-small">{{ uiText('Upload a signed contract file to bypass client signing and move directly to staff assignment.', 'ارفع عقدا موقعا لتجاوز توقيع العميل والانتقال مباشرة إلى تعيين الموظف.') }}</p>
            <input class="client-form-control" type="file" @change="onUploadedContractChange" />
            <div v-if="uploadedContractFile" class="selected-file-row">
              <span>{{ uploadedContractFile.name }}</span>
              <button type="button" class="ghost-btn small-btn" @click="clearUploadedContract">{{ uiText('Remove file', 'إزالة الملف') }}</button>
            </div>
          </article>

          <article class="mode-card">
            <h3>{{ uiText('Commercial registration authentication', 'توثيق الغرفة التجارية') }}</h3>
            <label class="toggle-row">
              <input
                v-model="requiresCommercialRegistration"
                type="checkbox"
                :disabled="bypassMode"
              />
              <span>{{ uiText('Require client + admin commercial registration uploads after signatures.', 'طلب رفع توثيق الغرفة التجارية من العميل ثم الإدارة بعد التوقيع.') }}</span>
            </label>
            <p v-if="bypassMode" class="muted-small mode-note">
              {{ uiText('Disabled while attachment bypass is active.', 'الخيار معطل أثناء تفعيل مسار المرفق الإداري.') }}
            </p>
          </article>
        </div>

        <div v-if="!bypassMode" class="contract-editor-toolbar">
          <div class="contract-editor-meta">
            <span class="editor-pill">{{ contractTemplate?.name || t('adminContractBuilderPage.states.defaultTemplate') }}</span>
            <span class="editor-pill">{{ t('adminContractBuilderPage.sections.applicantType') }}: {{ intakeFinanceType(financeRequest.intake_details_json, t('adminContractBuilderPage.states.emptyValue'), locale) }}</span>
          </div>
          <button type="button" class="ghost-btn small-btn" @click="resetToTemplate">{{ t('adminContractBuilderPage.actions.resetEditor') }}</button>
        </div>

        <p v-if="!bypassMode" class="muted-small">{{ t('adminContractBuilderPage.sections.editorHelp') }}</p>

        <div v-if="!bypassMode" class="contract-editor-shell">
          <div
            ref="editorRef"
            class="contract-editor-surface contract-doc"
            :dir="locale === 'ar' ? 'rtl' : 'ltr'"
            contenteditable="true"
            spellcheck="false"
            @input="handleEditorInput"
          ></div>
        </div>

        <div v-else class="notes-box">
          <span>{{ uiText('Attachment mode enabled', 'تم تفعيل مسار المرفق') }}</span>
          <p>{{ uiText('The uploaded contract will be used as the final downloadable contract for this request.', 'سيتم استخدام العقد المرفوع كنسخة العقد النهائية القابلة للتنزيل لهذا الطلب.') }}</p>
        </div>
      </article>

      <article class="panel-card signature-card wide-card">
        <div class="panel-head">
          <h2>{{ bypassMode ? uiText('Finalize attachment workflow', 'إكمال مسار المرفق') : t('adminContractBuilderPage.sections.adminSignature') }}</h2>
        </div>
        <p class="subtext">
          {{ bypassMode ? uiText('Saving now will bypass client signature and move the request directly to staff assignment.', 'عند الحفظ الآن سيتم تجاوز توقيع العميل ونقل الطلب مباشرة إلى تعيين الموظف.') : t('adminContractBuilderPage.sections.signatureHelp') }}
        </p>
        <div v-if="!bypassMode" class="signature-pad-shell">
          <canvas ref="canvasRef" class="signature-canvas"></canvas>
        </div>
        <div class="signature-actions">
          <button v-if="!bypassMode" class="ghost-btn" type="button" @click="clearSignature">{{ t('adminContractBuilderPage.actions.clearSignature') }}</button>
          <button class="primary-btn" type="button" :disabled="saving" @click="saveContract">
            {{
              saving
                ? t('adminContractBuilderPage.actions.saving')
                : (
                  bypassMode
                    ? uiText('Upload contract and continue', 'رفع العقد والمتابعة')
                    : t('adminContractBuilderPage.actions.saveSignSend')
                )
            }}
          </button>
        </div>
      </article>

      <article v-if="contract?.requires_commercial_registration" class="panel-card wide-card commercial-card">
        <div class="panel-head">
          <h2>{{ uiText('Commercial registration status', 'حالة توثيق الغرفة التجارية') }}</h2>
        </div>
        <div class="summary-grid summary-grid--tight">
          <div>
            <span>{{ uiText('Client upload', 'رفع العميل') }}</span>
            <strong>{{ readableDateTime(contract?.client_commercial_uploaded_at) }}</strong>
          </div>
          <div>
            <span>{{ uiText('Admin upload', 'رفع الإدارة') }}</span>
            <strong>{{ readableDateTime(contract?.admin_commercial_uploaded_at) }}</strong>
          </div>
        </div>
        <div class="actions-row">
          <a
            v-if="contract?.client_commercial_contract_path"
            :href="adminContractCommercialDownloadUrl(requestId, 'client')"
            target="_blank"
            rel="noopener"
            class="ghost-btn"
          >
            {{ uiText('Preview client upload', 'معاينة رفع العميل') }}
          </a>
          <a
            v-if="contract?.admin_commercial_contract_path"
            :href="adminContractCommercialDownloadUrl(requestId, 'admin')"
            target="_blank"
            rel="noopener"
            class="ghost-btn"
          >
            {{ uiText('Preview admin upload', 'معاينة رفع الإدارة') }}
          </a>
        </div>

        <div v-if="requiresAdminCommercialUpload" class="commercial-upload-box">
          <p class="subtext">{{ uiText('Upload the admin-authenticated Chamber of Commerce contract to continue to staff assignment.', 'ارفع العقد الموثق من الغرفة التجارية من جهة الإدارة للانتقال إلى تعيين الموظف.') }}</p>
          <input class="client-form-control" type="file" @change="onAdminCommercialFileChange" />
          <button
            type="button"
            class="primary-btn"
            :disabled="uploadingAdminCommercial || !adminCommercialFile"
            @click="submitAdminCommercialContract"
          >
            {{ uploadingAdminCommercial ? uiText('Uploading...', 'جاري الرفع...') : uiText('Upload admin authenticated contract', 'رفع العقد الموثق من الإدارة') }}
          </button>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.contract-grid {
  display: grid;
  grid-template-columns: minmax(280px, 0.95fr) minmax(0, 1.7fr);
  gap: 1.4rem;
  align-items: start;
}

.contract-grid > .signature-card {
  grid-column: 1 / -1;
}

.contract-form-card {
  display: grid;
  gap: 0.95rem;
}

.contract-mode-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem;
}

.mode-card {
  display: grid;
  gap: 0.6rem;
  padding: 0.95rem;
  border-radius: 16px;
  border: 1px solid rgba(148, 163, 184, 0.18);
  background: rgba(248, 250, 252, 0.72);
}

.mode-card > h3 {
  margin: 0;
  font-size: 0.9rem;
}

.toggle-row {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  color: var(--admin-text);
  font-weight: 600;
}

.mode-note {
  color: var(--admin-text-muted);
}

.selected-file-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.55rem 0.6rem;
  border-radius: 12px;
  border: 1px solid rgba(148, 163, 184, 0.18);
  background: #fff;
  font-size: 0.82rem;
}

.contract-editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.75rem;
}

.contract-editor-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.editor-pill {
  display: inline-flex;
  align-items: center;
  padding: 0.4rem 0.7rem;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.24);
  background: rgba(248, 250, 252, 0.86);
  color: var(--admin-text-muted);
  font-size: 0.78rem;
  font-weight: 700;
}

.contract-editor-shell {
  min-height: 520px;
  border-radius: 18px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  background: #ffffff;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
  overflow: auto;
}

.contract-editor-surface {
  min-height: 500px;
  padding: 1.25rem;
  outline: none;
  line-height: 1.85;
  color: var(--admin-text);
}

.signature-card {
  display: grid;
  gap: 0.85rem;
}

.commercial-card {
  display: grid;
  gap: 0.85rem;
}

.commercial-upload-box {
  display: grid;
  gap: 0.7rem;
  padding: 0.95rem;
  border-radius: 14px;
  border: 1px solid rgba(59, 130, 246, 0.24);
  background: rgba(239, 246, 255, 0.8);
}

.signature-pad-shell {
  width: 100%;
  min-height: 300px;
  padding: 8px;
  border-radius: 20px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  background: #ffffff;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.82);
}

.signature-canvas {
  width: 100%;
  height: 280px;
  display: block;
  border-radius: 14px;
  background: #ffffff;
}

.signature-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.75rem;
}

@media (max-width: 1200px) {
  .contract-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .contract-mode-grid {
    grid-template-columns: 1fr;
  }

  .contract-editor-shell {
    min-height: 430px;
  }

  .contract-editor-surface {
    min-height: 410px;
    padding: 1rem;
  }

  .signature-pad-shell {
    min-height: 252px;
  }

  .signature-canvas {
    height: 232px;
  }

  .signature-actions .ghost-btn,
  .signature-actions .primary-btn {
    width: 100%;
  }
}
</style>
