<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import SignaturePad from 'signature_pad'
import { clientContractDownloadUrl, getClientContract, signClientContract } from '@/services/clientPortal'
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

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getClientContract(requestId.value)
    financeRequest.value = data.request ?? null
    contract.value = data.contract ?? null

    await initSignatureSurface()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load the contract.'
  } finally {
    loading.value = false
  }
}

function clearSignature() {
  signaturePad?.clear()
}

async function submitSignature() {
  if (!financeRequest.value) return
  if (!signaturePad || signaturePad.isEmpty()) {
    errorMessage.value = 'Please sign before submitting.'
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
    errorMessage.value = error?.response?.data?.message || 'Failed to submit your signature.'
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
        <p class="eyebrow">Client signing</p>
        <h1>Review and Sign Contract</h1>
        <p>Download the PDF, review the final contract terms, then sign below to continue the process.</p>
      </div>
      <div class="action-stack">
        <RouterLink :to="{ name: 'client-request-details', params: { id: requestId } }" class="ghost-btn">Back to request</RouterLink>
        <a :href="clientContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="primary-btn">Download PDF</a>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading contract…</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <div v-else-if="financeRequest && contract" class="contract-grid">
      <article class="panel-card info-card">
        <div class="panel-head"><h2>Contract summary</h2></div>
        <div class="summary-grid">
          <div class="summary-row"><span>Request Ref</span><strong>{{ financeRequest.reference_number }}</strong></div>
          <div class="summary-row"><span>Approval Ref</span><strong>{{ financeRequest.approval_reference_number || 'Pending approval' }}</strong></div>
          <div class="summary-row"><span>Client</span><strong>{{ intakeFullName(financeRequest.intake_details_json, financeRequest.client?.name || 'Client') }}</strong></div>
          <div class="summary-row"><span>Country</span><strong>{{ countryNameFromCode(intakeCountryCode(financeRequest.intake_details_json)) }}</strong></div>
          <div class="summary-row"><span>Requested amount</span><strong>{{ intakeRequestedAmount(financeRequest.intake_details_json) }}</strong></div>
          <div class="summary-row"><span>Finance type</span><strong>{{ intakeFinanceType(financeRequest.intake_details_json) }}</strong></div>
        </div>
      </article>

      <article class="panel-card terms-card">
        <div class="panel-head"><h2>Terms overview</h2></div>
        <div class="terms-grid">
          <div class="term-box"><span>Commission</span><strong>{{ contract.terms_json?.commission || '—' }}</strong></div>
          <div class="term-box"><span>Interest</span><strong>{{ contract.terms_json?.interest || '—' }}</strong></div>
          <div class="term-box"><span>Payment period</span><strong>{{ contract.terms_json?.payment_period || '—' }}</strong></div>
        </div>

        <div class="term-list-box">
          <span class="term-list-title">General terms</span>
          <ol>
            <li v-for="(item, index) in contract.terms_json?.general_terms || []" :key="`${index}-${item}`">{{ item }}</li>
          </ol>
        </div>

        <div class="term-note" v-if="contract.terms_json?.special_terms">
          <span>Special terms</span>
          <p>{{ contract.terms_json?.special_terms }}</p>
        </div>
      </article>

      <article class="panel-card signature-card wide-card">
        <div class="panel-head"><h2>Client Signature</h2></div>
        <p class="subtext">By signing below, you confirm you reviewed the PDF contract and agree to proceed.</p>
        <div class="signature-pad-shell">
          <canvas ref="canvasRef" class="signature-canvas"></canvas>
        </div>
        <div class="signature-actions">
          <button class="ghost-btn" type="button" @click="clearSignature">Clear signature</button>
          <button class="primary-btn" type="button" :disabled="submitting" @click="submitSignature">
            {{ submitting ? 'Submitting…' : 'Sign and submit' }}
          </button>
        </div>
      </article>
    </div>
  </section>
</template>
