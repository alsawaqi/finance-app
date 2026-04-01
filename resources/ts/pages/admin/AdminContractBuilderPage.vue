<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import SignaturePad from 'signature_pad'
import { useI18n } from 'vue-i18n'
import { adminContractDownloadUrl, getAdminContract, saveAdminContract } from '@/services/adminRequests'
import { countryNameFromCode } from '@/utils/countries'
import {
  intakeCompanyCrNumber,
  intakeCountryCode,
  intakeFinanceType,
  intakeFullName,
  intakeRequestedAmount,
  intakeUnifiedNumber,
} from '@/utils/requestIntake'

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

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminContract(requestId.value)
    financeRequest.value = data.request ?? null
    contract.value = data.contract ?? null
    contractTemplate.value = data.contract_template ?? null
    draftContractHtml.value = data.contract?.contract_content || data.draft_contract_html || ''
    initialDraftContractHtml.value = data.draft_contract_html || draftContractHtml.value

    await nextTick()
    syncEditorHtml()
    await initSignatureSurface()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminContractBuilderPage.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function clearSignature() {
  signaturePad?.clear()
}

function resetToTemplate() {
  draftContractHtml.value = initialDraftContractHtml.value
  syncEditorHtml()
}

async function saveContract() {
  if (!financeRequest.value || !contractTemplate.value) return
  if (!signaturePad || signaturePad.isEmpty()) {
    errorMessage.value = t('adminContractBuilderPage.errors.signBeforeSaving')
    return
  }

  const contractBodyHtml = (editorRef.value?.innerHTML || '').trim()
  if (!contractBodyHtml) {
    errorMessage.value = t('adminContractBuilderPage.errors.contractBodyEmpty')
    return
  }

  saving.value = true
  errorMessage.value = ''

  try {
    await saveAdminContract(financeRequest.value.id, {
      contract_template_slug: contractTemplate.value.slug,
      contract_body_html: contractBodyHtml,
      signature_data_url: signaturePad.toDataURL('image/png'),
    })

    await router.push({ name: 'admin-request-details', params: { id: financeRequest.value.id } })
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminContractBuilderPage.errors.saveFailed')
  } finally {
    saving.value = false
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
        <h1>{{ t('adminContractBuilderPage.hero.title') }}</h1>
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
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.country') }}</span><strong>{{ countryNameFromCode(intakeCountryCode(financeRequest.intake_details_json), locale) }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.requestedAmount') }}</span><strong>{{ intakeRequestedAmount(financeRequest.intake_details_json) }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.financeType') }}</span><strong>{{ intakeFinanceType(financeRequest.intake_details_json) }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.unifiedNumber') }}</span><strong>{{ intakeUnifiedNumber(financeRequest.intake_details_json) }}</strong></div>
          <div class="summary-row"><span>{{ t('adminContractBuilderPage.summary.companyCrNumber') }}</span><strong>{{ intakeCompanyCrNumber(financeRequest.intake_details_json) }}</strong></div>
        </div>
      </article>

      <article class="panel-card contract-form-card">
        <div class="panel-head">
          <h2>{{ t('adminContractBuilderPage.sections.editableContractTemplate') }}</h2>
        </div>

        <div class="contract-editor-toolbar">
          <div class="contract-editor-meta">
            <span class="editor-pill">{{ contractTemplate?.name || t('adminContractBuilderPage.states.defaultTemplate') }}</span>
            <span class="editor-pill">{{ t('adminContractBuilderPage.sections.applicantType') }}: {{ intakeFinanceType(financeRequest.intake_details_json) }}</span>
          </div>
          <button type="button" class="ghost-btn small-btn" @click="resetToTemplate">{{ t('adminContractBuilderPage.actions.resetEditor') }}</button>
        </div>

        <p class="muted-small">{{ t('adminContractBuilderPage.sections.editorHelp') }}</p>

        <div class="contract-editor-shell">
          <div
            ref="editorRef"
            class="contract-editor-surface contract-doc"
            dir="rtl"
            contenteditable="true"
            spellcheck="false"
                        @input="handleEditorInput"
          ></div>
        </div>
      </article>

      <article class="panel-card signature-card wide-card">
        <div class="panel-head"><h2>{{ t('adminContractBuilderPage.sections.adminSignature') }}</h2></div>
        <p class="subtext">{{ t('adminContractBuilderPage.sections.signatureHelp') }}</p>
        <div class="signature-pad-shell">
          <canvas ref="canvasRef" class="signature-canvas"></canvas>
        </div>
        <div class="signature-actions">
          <button class="ghost-btn" type="button" @click="clearSignature">{{ t('adminContractBuilderPage.actions.clearSignature') }}</button>
          <button class="primary-btn" type="button" :disabled="saving" @click="saveContract">
            {{ saving ? t('adminContractBuilderPage.actions.saving') : t('adminContractBuilderPage.actions.saveSignSend') }}
          </button>
        </div>
      </article>
    </div>
  </section>
</template>
