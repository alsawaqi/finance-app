<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import SignaturePad from 'signature_pad'
import AppFilePreviewModal from '@/components/AppFilePreviewModal.vue'
import { clientContractDownloadUrl, getClientContract, signClientContract } from '@/services/clientPortal'
import { buildPreviewUrl } from '@/utils/filePreview'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'

const route = useRoute()
const router = useRouter()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const submitting = ref(false)
const errorMessage = ref('')
const financeRequest = ref<any | null>(null)
const contract = ref<any | null>(null)
const { t, locale } = useI18n()
const canvasRef = ref<HTMLCanvasElement | null>(null)
const filePreviewOpen = ref(false)
const filePreviewUrl = ref('')
const fileDownloadUrl = ref('')
let signaturePad: SignaturePad | null = null

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

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

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getClientContract(requestId.value)
    financeRequest.value = data.request ?? null
    contract.value = data.contract ?? null

    await initSignatureSurface()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('clientSign.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function clearSignature() {
  signaturePad?.clear()
}

function openContractPreview() {
  const downloadUrl = clientContractDownloadUrl(requestId.value)
  fileDownloadUrl.value = downloadUrl
  filePreviewUrl.value = buildPreviewUrl(downloadUrl)
  filePreviewOpen.value = true
}

async function submitSignature() {
  if (!financeRequest.value) return
  if (!signaturePad || signaturePad.isEmpty()) {
    errorMessage.value = t('clientSign.errors.signFirst')
    return
  }

  submitting.value = true
  errorMessage.value = ''

  try {
    await signClientContract(financeRequest.value.id, {
      signature_data_url: signaturePad.toDataURL('image/png'),
    })

    await router.push({ name: 'client-request-details', params: { id: financeRequest.value.id } })
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('clientSign.errors.submitFailed')
  } finally {
    submitting.value = false
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
  <section class="client-shell">
    <div class="hero-card">
      <div>
        <p class="eyebrow">{{ t('clientSign.hero.eyebrow') }}</p>
        <h1>{{ t('clientSign.hero.title') }}</h1>
        <p>{{ t('clientSign.hero.subtitle') }}</p>
      </div>
      <div class="action-stack">
        <RouterLink :to="{ name: 'client-request-details', params: { id: requestId } }" class="ghost-btn">{{ t('clientSign.hero.backToRequest') }}</RouterLink>
        <button type="button" class="ghost-btn" @click="openContractPreview">{{ uiText('Preview PDF', 'معاينة PDF') }}</button>
        <a :href="clientContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="primary-btn">{{ t('clientSign.hero.downloadPdf') }}</a>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('clientSign.states.loading') }}</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <div v-else-if="financeRequest && contract" class="contract-grid">
      <article class="panel-card info-card">
        <div class="panel-head"><h2>{{ t('clientSign.sections.contractSummary') }}</h2></div>
        <div class="summary-grid">
          <div class="summary-row"><span>{{ t('clientSign.summary.requestRef') }}</span><strong>{{ financeRequest.reference_number }}</strong></div>
          <div class="summary-row"><span>{{ t('clientSign.summary.approvalRef') }}</span><strong>{{ financeRequest.approval_reference_number || t('clientSign.states.pendingApproval') }}</strong></div>
          <div class="summary-row"><span>{{ t('clientSign.summary.client') }}</span><strong>{{ intakeFullName(financeRequest.intake_details_json, financeRequest.client?.name || t('clientSign.states.clientFallback')) }}</strong></div>
          <div class="summary-row"><span>{{ t('clientSign.summary.country') }}</span><strong>{{ countryNameFromCode(financeRequest.country_code || intakeCountryCode(financeRequest.intake_details_json), locale) }}</strong></div>
          <div class="summary-row"><span>{{ t('clientSign.summary.requestedAmount') }}</span><strong>{{ intakeRequestedAmount(financeRequest.intake_details_json) }}</strong></div>
          <div class="summary-row"><span>{{ t('clientSign.summary.financeType') }}</span><strong>{{ intakeFinanceType(financeRequest.intake_details_json, t('clientSign.states.emptyValue'), locale) }}</strong></div>
        </div>
      </article>

      <article class="panel-card terms-card">
        <div class="panel-head"><h2>{{ t('clientSign.sections.contractPreview') }}</h2></div>
        <p class="subtext">{{ t('clientSign.sections.contractPreviewHint') }}</p>
        <div class="contract-preview-shell">
          <div class="contract-preview-surface contract-doc" dir="rtl" v-html="contract.contract_content || `<p>${t('clientSign.states.contractPreviewUnavailable')}</p>`"></div>
        </div>
      </article>

      <article class="panel-card signature-card wide-card">
        <div class="panel-head"><h2>{{ t('clientSign.sections.clientSignature') }}</h2></div>
        <p class="subtext">{{ t('clientSign.sections.signatureDisclaimer') }}</p>
        <div class="signature-pad-shell">
          <canvas ref="canvasRef" class="signature-canvas"></canvas>
        </div>
        <div class="signature-actions">
          <button class="ghost-btn" type="button" @click="clearSignature">{{ t('clientSign.actions.clearSignature') }}</button>
          <button class="primary-btn" type="button" :disabled="submitting" @click="submitSignature">
            {{ submitting ? t('clientSign.actions.submitting') : t('clientSign.actions.signAndSubmit') }}
          </button>
        </div>
      </article>
    </div>

    <AppFilePreviewModal
      :model-value="filePreviewOpen"
      @update:model-value="(value) => { filePreviewOpen = value }"
      :title="uiText('Contract preview', 'معاينة العقد')"
      :file-name="`contract-${requestId}.pdf`"
      mime-type="application/pdf"
      :preview-url="filePreviewUrl"
      :download-url="fileDownloadUrl"
    />
  </section>
</template>
