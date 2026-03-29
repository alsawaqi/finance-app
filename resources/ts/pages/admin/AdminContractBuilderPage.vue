<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import SignaturePad from 'signature_pad'
import { adminContractDownloadUrl, getAdminContract, saveAdminContract } from '@/services/adminRequests'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'

const route = useRoute()
const router = useRouter()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const financeRequest = ref<any | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
let signaturePad: SignaturePad | null = null

const form = ref({
  commission: '',
  interest: '',
  payment_period: '',
  general_terms: [
    'Commission is due according to the approved structure.',
    'Payments must be made on or before the agreed due date.',
    'Any major changes require written confirmation from both parties.',
  ],
  special_terms: '',
})

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
    const data = await getAdminContract(requestId.value)
    financeRequest.value = data.request ?? null

    const current = data.contract?.terms_json
    if (current) {
      form.value.commission = current.commission || ''
      form.value.interest = current.interest || ''
      form.value.payment_period = current.payment_period || ''
      form.value.general_terms = Array.isArray(current.general_terms) && current.general_terms.length
        ? current.general_terms
        : form.value.general_terms
      form.value.special_terms = current.special_terms || ''
    }

    await initSignatureSurface()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load contract builder.'
  } finally {
    loading.value = false
  }
}

function clearSignature() {
  signaturePad?.clear()
}

function addTerm() {
  form.value.general_terms.push('')
}

function removeTerm(index: number) {
  form.value.general_terms.splice(index, 1)
}

async function saveContract() {
  if (!financeRequest.value) return
  if (!signaturePad || signaturePad.isEmpty()) {
    errorMessage.value = 'Please sign the contract before saving.'
    return
  }

  saving.value = true
  errorMessage.value = ''

  try {
    await saveAdminContract(financeRequest.value.id, {
      commission: form.value.commission,
      interest: form.value.interest,
      payment_period: form.value.payment_period,
      general_terms: form.value.general_terms.filter((item) => item.trim().length > 0),
      special_terms: form.value.special_terms,
      signature_data_url: signaturePad.toDataURL('image/png'),
    })

    await router.push({ name: 'admin-request-details', params: { id: financeRequest.value.id } })
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to save the contract.'
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
        <p class="eyebrow">Contract drafting</p>
        <h1>Create Contract</h1>
        <p class="subtext">Prepare the contract terms, sign as admin, and send the final review package to the client.</p>
      </div>
      <div class="actions-row">
        <RouterLink :to="{ name: 'admin-request-details', params: { id: requestId } }" class="ghost-btn">Back to details</RouterLink>
        <a v-if="financeRequest?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">Download current PDF</a>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading contract builder…</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <div v-else-if="financeRequest" class="contract-grid">
      <article class="panel-card info-card">
        <div class="panel-head"><h2>Request summary</h2></div>
        <div class="summary-grid">
          <div class="summary-row"><span>Request Ref</span><strong>{{ financeRequest.reference_number }}</strong></div>
          <div class="summary-row"><span>Approval Ref</span><strong>{{ financeRequest.approval_reference_number || 'Pending approval' }}</strong></div>
          <div class="summary-row"><span>Client</span><strong>{{ intakeFullName(financeRequest.intake_details_json, financeRequest.client?.name || 'Client') }}</strong></div>
          <div class="summary-row"><span>Country</span><strong>{{ countryNameFromCode(intakeCountryCode(financeRequest.intake_details_json)) }}</strong></div>
          <div class="summary-row"><span>Requested Amount</span><strong>{{ intakeRequestedAmount(financeRequest.intake_details_json) }}</strong></div>
          <div class="summary-row"><span>Finance Type</span><strong>{{ intakeFinanceType(financeRequest.intake_details_json) }}</strong></div>
        </div>
      </article>

      <article class="panel-card contract-form-card">
        <div class="panel-head"><h2>Agreement Terms</h2></div>
        <div class="form-grid">
          <label class="field-block">
            <span>Commission</span>
            <input v-model="form.commission" type="text" class="admin-input" placeholder="e.g. 2% of approved amount" />
          </label>

          <label class="field-block">
            <span>Interest</span>
            <input v-model="form.interest" type="text" class="admin-input" placeholder="e.g. 5.5% yearly" />
          </label>

          <label class="field-block">
            <span>Payment Period</span>
            <input v-model="form.payment_period" type="text" class="admin-input" placeholder="e.g. 36 monthly installments" />
          </label>
        </div>

        <div class="terms-block">
          <div class="panel-head compact-head">
            <h3>General Terms</h3>
            <button type="button" class="ghost-btn small-btn" @click="addTerm">Add term</button>
          </div>
          <div class="term-list">
            <div v-for="(term, index) in form.general_terms" :key="index" class="term-row">
              <textarea v-model="form.general_terms[index]" rows="2" class="admin-textarea" :placeholder="`Term ${index + 1}`"></textarea>
              <button type="button" class="ghost-btn small-btn" @click="removeTerm(index)">Remove</button>
            </div>
          </div>
        </div>

        <label class="field-block">
          <span>Special Terms</span>
          <textarea v-model="form.special_terms" rows="4" class="admin-textarea" placeholder="Optional custom terms or remarks"></textarea>
        </label>
      </article>

      <article class="panel-card signature-card wide-card">
        <div class="panel-head"><h2>Admin Signature</h2></div>
        <p class="subtext">Sign here to confirm the contract is ready for client review.</p>
        <div class="signature-pad-shell">
          <canvas ref="canvasRef" class="signature-canvas"></canvas>
        </div>
        <div class="signature-actions">
          <button class="ghost-btn" type="button" @click="clearSignature">Clear signature</button>
          <button class="primary-btn" type="button" :disabled="saving" @click="saveContract">
            {{ saving ? 'Saving…' : 'Save, sign, and send to client' }}
          </button>
        </div>
      </article>
    </div>
  </section>
</template>
