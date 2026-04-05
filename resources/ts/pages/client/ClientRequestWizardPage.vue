<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppFilePreviewModal from '@/components/AppFilePreviewModal.vue'
import ClientQuestionField from './inc/ClientQuestionField.vue'
import { useAuthStore } from '../../stores/auth'
import {
  getRequestQuestions,
  submitClientRequest,
  type ClientQuestion,
  type FinanceRequestTypeOption,
} from '@/services/clientRequests'
import {
  clearClientRequestDraft,
  hasMeaningfulClientRequestDraftData,
  loadClientRequestDraft,
  saveClientRequestDraft,
  type ClientRequestWizardDraftPayload,
} from '@/utils/clientRequestDraft'
import { allCountryOptions } from '@/utils/countries'

const router = useRouter()
const auth = useAuthStore()
const { t, locale } = useI18n()

const loading = ref(true)
const submitting = ref(false)
const currentStep = ref(1)
const totalSteps = 2

const questions = ref<ClientQuestion[]>([])
  const financeRequestTypes = ref<FinanceRequestTypeOption[]>([])
const answers = reactive<Record<number, unknown>>({})
const attachments = ref<File[]>([])
const nationalAddressAttachment = ref<File | null>(null)
const companyCr = ref<File | null>(null)
const shareholders = ref<Array<{ name: string; phone_country_code: string; phone_number: string; id_number: string; id_file: File | null }>>([])
const uploadPreviewOpen = ref(false)
const uploadPreviewTitle = ref('')
const uploadPreviewFile = ref<File | null>(null)

const fieldErrors = reactive<Record<string, string>>({})
const generalError = ref('')
const successMessage = ref('')
const draftRestored = ref(false)
const draftMessage = ref('')
const restoringDraft = ref(false)
const skipDraftPersistenceOnUnmount = ref(false)
let draftSaveTimeout: number | null = null
 

const countryOptions = computed(() => allCountryOptions(locale.value))

const phoneCodeOptions = computed(() => [
  { code: '+966', label: t('clientWizard.phoneCodes.sa') },
  { code: '+968', label: t('clientWizard.phoneCodes.om') },
  { code: '+971', label: t('clientWizard.phoneCodes.ae') },
  { code: '+965', label: t('clientWizard.phoneCodes.kw') },
  { code: '+974', label: t('clientWizard.phoneCodes.qa') },
  { code: '+973', label: t('clientWizard.phoneCodes.bh') },
  { code: '+20', label: t('clientWizard.phoneCodes.eg') },
  { code: '+962', label: t('clientWizard.phoneCodes.jo') },
  { code: '+961', label: t('clientWizard.phoneCodes.lb') },
  { code: '+1', label: t('clientWizard.phoneCodes.usCa') },
  { code: '+44', label: t('clientWizard.phoneCodes.gb') },
  { code: '+91', label: t('clientWizard.phoneCodes.in') },
  { code: '+92', label: t('clientWizard.phoneCodes.pk') },
  { code: '+880', label: t('clientWizard.phoneCodes.bd') },
  { code: '+90', label: t('clientWizard.phoneCodes.tr') },
])

const details = reactive({
  finance_type: 'individual' as 'individual' | 'company',
  finance_request_type_id: '',
  country: '',
  requested_amount: '',
  company_name: '',
  company_cr_number: '',
  email: '',
  phone_country_code: '+966',
  phone_number: '',
  unified_number: '',
  national_address_number: '',
  address: '',
  notes: '',
})


type ShareholderState = {
  name: string
  phone_country_code: string
  phone_number: string
  id_number: string
  id_file: File | null
}

function getDraftUserId() {
  return auth.user?.id ?? 'guest'
}

function createEmptyShareholder(): ShareholderState {
  return {
    name: '',
    phone_country_code: '+966',
    phone_number: '',
    id_number: '',
    id_file: null,
  }
}

const steps = computed(() => [
  { id: 1, title: t('clientWizard.steps.questionsTitle'), text: t('clientWizard.steps.questionsText') },
  { id: 2, title: t('clientWizard.steps.detailsTitle'), text: t('clientWizard.steps.detailsText') },
])

const answerPayload = computed(() =>
  questions.value.map((question) => ({
    question_id: question.id,
    value: answers[question.id] ?? (question.question_type === 'checkbox' ? [] : ''),
  })),
)

const applicantDisplayName = computed(() => {
  const firstName = String((auth.user as any)?.first_name ?? '').trim()
  const lastName = String((auth.user as any)?.last_name ?? '').trim()
  const joined = `${firstName} ${lastName}`.trim()

  return joined || auth.user?.name || ''
})

function clearErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  generalError.value = ''
}

function financeRequestTypeLabel(type: FinanceRequestTypeOption) {
  const slug = String((type as any)?.slug || '').trim().toLowerCase()
  const nameAr = String((type as any)?.name_ar || '').trim()
  const nameEn = String((type as any)?.name_en || '').trim()
  const descriptionAr = String((type as any)?.description_ar || '').trim()

  if (locale.value === 'ar') {
    return nameAr || descriptionAr || fallbackArabicLabelFromSlug(slug) || nameEn
  }

  return nameEn || nameAr || titleCaseSlug(slug)
}

function titleCaseSlug(slug: string) {
  if (!slug) return ''
  return slug
    .split(/[_-\s]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function fallbackArabicLabelFromSlug(slug: string) {
  if (!slug) return ''

  const directMap: Record<string, string> = {
    home_finance: 'تمويل سكني',
    personal_finance: 'تمويل شخصي',
    business_finance: 'تمويل أعمال',
    company_finance: 'تمويل شركات',
    auto_finance: 'تمويل سيارة',
    car_finance: 'تمويل سيارة',
    mortgage: 'رهن عقاري',
    real_estate_finance: 'تمويل عقاري',
  }

  if (directMap[slug]) return directMap[slug]

  const tokenMap: Record<string, string> = {
    home: 'سكني',
    housing: 'سكني',
    personal: 'شخصي',
    business: 'أعمال',
    company: 'شركات',
    auto: 'سيارة',
    car: 'سيارة',
    vehicle: 'مركبة',
    finance: 'تمويل',
    loan: 'قرض',
    mortgage: 'رهن',
    real: 'عقار',
    estate: 'عقاري',
  }

  const localizedParts = slug
    .split(/[_-\s]+/)
    .filter(Boolean)
    .map((part) => tokenMap[part] || '')
    .filter(Boolean)

  if (!localizedParts.length) return ''

  const hasFinanceWord = localizedParts.includes('تمويل')
  const ordered = hasFinanceWord
    ? localizedParts
    : ['تمويل', ...localizedParts]

  return ordered.join(' ').trim()
}

function digitsOnlyPhone(value: string) {
  return value.replace(/\D/g, '')
}

function sanitizeWizardPhones() {
  details.phone_number = digitsOnlyPhone(details.phone_number)
  shareholders.value.forEach((s) => {
    s.phone_number = digitsOnlyPhone(s.phone_number)
  })
}

const FILE_INPUT_IDS = {
  nationalAddress: 'wizard-file-national-address',
  attachments: 'wizard-file-attachments',
  companyCr: 'wizard-file-company-cr',
} as const

function resetFileInput(id: string) {
  const el = document.getElementById(id) as HTMLInputElement | null
  if (el) el.value = ''
}

function shareholderFileInputId(index: number) {
  return `wizard-file-shareholder-${index}`
}

function triggerFilePick(id: string) {
  document.getElementById(id)?.click()
}

function hydrateApplicantDefaults() {
  if (!details.email && auth.user?.email) {
    details.email = auth.user.email
  }

  if (!details.phone_number && auth.user?.phone) {
    details.phone_number = digitsOnlyPhone(String(auth.user.phone))
  }

  details.phone_number = digitsOnlyPhone(details.phone_number)
}

function addShareholder() {
  shareholders.value.push(createEmptyShareholder())
}

function removeShareholder(index: number) {
  shareholders.value.splice(index, 1)
}

function resetWizardState() {
  currentStep.value = 1

  details.finance_type = 'individual'
  details.finance_request_type_id = ''
  
  details.country = ''
  details.requested_amount = ''
  details.company_name = ''
  details.company_cr_number = ''
  details.email = ''
  details.phone_country_code = '+966'
  details.phone_number = ''
  details.unified_number = ''
  details.national_address_number = ''
  details.address = ''
  details.notes = ''

  Object.keys(answers).forEach((key) => {
    delete answers[Number(key)]
  })

  questions.value.forEach((question) => {
    answers[question.id] = question.question_type === 'checkbox' ? [] : ''
  })

  attachments.value = []
  nationalAddressAttachment.value = null
  companyCr.value = null
  shareholders.value = []
  uploadPreviewOpen.value = false
  uploadPreviewTitle.value = ''
  uploadPreviewFile.value = null

  clearErrors()
  successMessage.value = ''
  generalError.value = ''
  hydrateApplicantDefaults()
}

function mergeAttachmentFiles(newFiles: File[]) {
  const merged = [...attachments.value]
  for (const f of newFiles) {
    if (!merged.some((x) => x.name === f.name && x.size === f.size)) merged.push(f)
  }
  attachments.value = merged
}

function handleAttachments(event: Event) {
  const input = event.target as HTMLInputElement
  mergeAttachmentFiles(Array.from(input.files || []))
  input.value = ''
}

function handleNationalAddressAttachment(event: Event) {
  const input = event.target as HTMLInputElement
  nationalAddressAttachment.value = input.files?.[0] || null
  input.value = ''
}

function handleCompanyCr(event: Event) {
  const input = event.target as HTMLInputElement
  companyCr.value = input.files?.[0] || null
  input.value = ''
}

function handleShareholderFile(index: number, event: Event) {
  const input = event.target as HTMLInputElement
  shareholders.value[index].id_file = input.files?.[0] || null
  input.value = ''
}

function onDropAttachments(event: DragEvent) {
  event.preventDefault()
  const list = event.dataTransfer?.files
  if (!list?.length) return
  mergeAttachmentFiles(Array.from(list))
}

function dropNationalAddressAttachment(event: DragEvent) {
  event.preventDefault()
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  nationalAddressAttachment.value = file
  resetFileInput(FILE_INPUT_IDS.nationalAddress)
}

function dropCompanyCrFile(event: DragEvent) {
  event.preventDefault()
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  companyCr.value = file
  resetFileInput(FILE_INPUT_IDS.companyCr)
}

function dropShareholderIdFile(index: number, event: DragEvent) {
  event.preventDefault()
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  shareholders.value[index].id_file = file
  resetFileInput(shareholderFileInputId(index))
}

function clearNationalAddressAttachment() {
  nationalAddressAttachment.value = null
  resetFileInput(FILE_INPUT_IDS.nationalAddress)
}

function clearCompanyCr() {
  companyCr.value = null
  resetFileInput(FILE_INPUT_IDS.companyCr)
}

function removeAttachment(index: number) {
  attachments.value.splice(index, 1)
}

function clearShareholderIdFile(index: number) {
  shareholders.value[index].id_file = null
  resetFileInput(shareholderFileInputId(index))
}

function openUploadPreview(file: File | null, title: string) {
  if (!file) return
  uploadPreviewTitle.value = title
  uploadPreviewFile.value = file
  uploadPreviewOpen.value = true
}

function syncAnswersFromQuestions() {
  const activeQuestionIds = new Set<number>()

  questions.value.forEach((question) => {
    activeQuestionIds.add(question.id)

    if (!(question.id in answers)) {
      answers[question.id] = question.question_type === 'checkbox' ? [] : ''
    }
  })

  Object.keys(answers).forEach((key) => {
    const numericKey = Number(key)

    if (Number.isInteger(numericKey) && !activeQuestionIds.has(numericKey)) {
      delete answers[numericKey]
    }
  })
}

function applyDraftToState(draft: ClientRequestWizardDraftPayload) {
  restoringDraft.value = true

  currentStep.value = draft.currentStep
  Object.assign(details, draft.details)

  Object.keys(answers).forEach((key) => {
    delete answers[Number(key)]
  })

  questions.value.forEach((question) => {
    if (question.id in draft.answers) {
      answers[question.id] = draft.answers[question.id]
      return
    }

    answers[question.id] = question.question_type === 'checkbox' ? [] : ''
  })

  shareholders.value = draft.shareholders.map((shareholder) => ({
    ...shareholder,
    id_file: null,
  }))

  hydrateApplicantDefaults()
  sanitizeWizardPhones()
  draftRestored.value = true
  draftMessage.value = t('clientWizard.draft.restoredWithFilesNotice')
  restoringDraft.value = false
}

function restoreDraftIfAvailable() {
  const draft = loadClientRequestDraft(getDraftUserId())

  if (!draft) {
    syncAnswersFromQuestions()
    hydrateApplicantDefaults()
    return
  }

  applyDraftToState(draft)
}

function persistDraftNow() {
  if (restoringDraft.value || skipDraftPersistenceOnUnmount.value) return

  const draftInput = {
    currentStep: currentStep.value,
    details,
    answers,
    shareholders: shareholders.value,
  }

  if (!hasMeaningfulClientRequestDraftData(draftInput)) {
    clearClientRequestDraft(getDraftUserId())
    return
  }

  saveClientRequestDraft(getDraftUserId(), draftInput)
}

function queueDraftSave() {
  if (restoringDraft.value) return

  if (draftSaveTimeout) {
    window.clearTimeout(draftSaveTimeout)
  }

  draftSaveTimeout = window.setTimeout(() => {
    persistDraftNow()
    draftSaveTimeout = null
  }, 250)
}

function discardDraft() {
  clearClientRequestDraft(getDraftUserId())
  draftRestored.value = false
  draftMessage.value = t('clientWizard.draft.discarded')
  resetWizardState()
}

watch(
  () => ({
    currentStep: currentStep.value,
    details: { ...details },
    answers: JSON.parse(JSON.stringify(answers)),
    shareholders: shareholders.value.map((shareholder) => ({
      name: shareholder.name,
      phone_country_code: shareholder.phone_country_code,
      phone_number: shareholder.phone_number,
      id_number: shareholder.id_number,
    })),
  }),
  () => {
    queueDraftSave()
  },
  { deep: true },
)

function validateStepOne() {
  clearErrors()
  let hasError = false

  for (const question of questions.value) {
    if (!question.is_required) continue

    const value = answers[question.id]
    const isEmpty = Array.isArray(value)
      ? value.length === 0
      : value === null || value === undefined || String(value).trim() === ''

    if (isEmpty) {
      fieldErrors[`question-${question.id}`] = t('clientWizard.errors.requiredField', { field: question.question_text })
      hasError = true
    }
  }

  return !hasError
}

function validateStepTwo() {
  clearErrors()
  let hasError = false

  if (!details.finance_type) {
    fieldErrors['details.finance_type'] = t('clientWizard.errors.applicantTypeRequired')
    hasError = true
  }

  if (!String(details.finance_request_type_id).trim()) {
  fieldErrors['details.finance_request_type_id'] = t('clientWizard.errors.requestTypeRequired')
  hasError = true
}

  if (!details.country.trim()) {
    fieldErrors['details.country'] = t('clientWizard.errors.countryCodeRequired')
    hasError = true
  }

  if (!String(details.requested_amount).trim()) {
    fieldErrors['details.requested_amount'] = t('clientWizard.errors.requestedAmountRequired')
    hasError = true
  }

  if (!details.email.trim()) {
    fieldErrors['details.email'] = t('clientWizard.errors.emailRequired')
    hasError = true
  }

  if (!details.phone_number.trim()) {
    fieldErrors['details.phone_number'] = t('clientWizard.errors.phoneRequired')
    hasError = true
  }

  if (!details.unified_number.trim()) {
    fieldErrors['details.unified_number'] = t('clientWizard.errors.unifiedNumberRequired')
    hasError = true
  }

  if (!details.national_address_number.trim()) {
    fieldErrors['details.national_address_number'] = t('clientWizard.errors.nationalAddressNumberRequired')
    hasError = true
  }

  if (!details.address.trim()) {
    fieldErrors['details.address'] = t('clientWizard.errors.addressRequired')
    hasError = true
  }

  if (!nationalAddressAttachment.value) {
    fieldErrors.national_address_attachment = t('clientWizard.errors.nationalAddressAttachmentRequired')
    hasError = true
  }

  if (details.finance_type === 'company') {
    if (!details.company_name.trim()) {
      fieldErrors['details.company_name'] = t('clientWizard.errors.companyNameRequired')
      hasError = true
    }

    if (!details.company_cr_number.trim()) {
      fieldErrors['details.company_cr_number'] = t('clientWizard.errors.companyCrNumberRequired')
      hasError = true
    }

    if (!companyCr.value) {
      fieldErrors.company_cr = t('clientWizard.errors.companyCrRequired')
      hasError = true
    }

    if (shareholders.value.length === 0) {
      fieldErrors.shareholders = t('clientWizard.errors.shareholdersRequired')
      hasError = true
    }

    shareholders.value.forEach((shareholder, index) => {
      if (!shareholder.name.trim()) {
        fieldErrors[`shareholders.${index}.name`] = t('clientWizard.errors.shareholderNameRequired')
        hasError = true
      }

      if (!shareholder.phone_country_code.trim()) {
        fieldErrors[`shareholders.${index}.phone_country_code`] = t('clientWizard.errors.shareholderPhoneCountryCodeRequired')
        hasError = true
      }

      if (!shareholder.phone_number.trim()) {
        fieldErrors[`shareholders.${index}.phone_number`] = t('clientWizard.errors.shareholderPhoneNumberRequired')
        hasError = true
      }

      if (!shareholder.id_number.trim()) {
        fieldErrors[`shareholders.${index}.id_number`] = t('clientWizard.errors.shareholderIdNumberRequired')
        hasError = true
      }

      if (!shareholder.id_file) {
        fieldErrors[`shareholders.${index}.id_file`] = t('clientWizard.errors.shareholderIdRequired')
        hasError = true
      }
    })
  }

  return !hasError
}

function goNext() {
  if (currentStep.value === 1 && validateStepOne()) {
    currentStep.value = 2
  }
}

function goBack() {
  clearErrors()
  currentStep.value = 1
}

async function loadQuestions() {
  loading.value = true
  clearErrors()

  try {
    const { data } = await getRequestQuestions()
questions.value = data.questions ?? []
financeRequestTypes.value = data.finance_request_types ?? []
restoreDraftIfAvailable()
  } catch (error: any) {
    generalError.value = error?.response?.data?.message || t('clientWizard.errors.loadQuestionsFailed')
  } finally {
    loading.value = false
  }
}

async function submitRequest() {
  if (!validateStepTwo() || !nationalAddressAttachment.value) return

  submitting.value = true
  clearErrors()
  successMessage.value = ''

  try {
    const { data } = await submitClientRequest({
      answers: answerPayload.value,
      details: {
        finance_request_type_id: Number(details.finance_request_type_id),
        country: details.country,
        requested_amount: details.requested_amount,
        finance_type: details.finance_type,
        company_name: details.company_name,
        company_cr_number: details.company_cr_number,
        email: details.email,
        phone_country_code: details.phone_country_code,
        phone_number: details.phone_number,
        unified_number: details.unified_number,
        national_address_number: details.national_address_number,
        address: details.address,
        notes: details.notes,
      },
      attachments: attachments.value,
      national_address_attachment: nationalAddressAttachment.value,
      company_cr: companyCr.value,
      shareholders: shareholders.value,
    })

    successMessage.value = data.message
skipDraftPersistenceOnUnmount.value = true
clearClientRequestDraft(getDraftUserId())

if (draftSaveTimeout) {
  window.clearTimeout(draftSaveTimeout)
  draftSaveTimeout = null
}

await router.replace({ name: 'client-request-details', params: { id: data.request.id } })



  } catch (error: any) {
    const responseErrors = error?.response?.data?.errors || {}
    const responseMessage = error?.response?.data?.message

    Object.entries(responseErrors).forEach(([key, messages]) => {
      if (Array.isArray(messages) && messages.length > 0) {
        if (key.startsWith('answers.')) {
          const rawId = key.split('.')[1]
          fieldErrors[`question-${rawId}`] = String(messages[0])
        } else {
          fieldErrors[key] = String(messages[0])
        }
      }
    })

    generalError.value = responseMessage || t('clientWizard.errors.submitFailed')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadQuestions()
})

onBeforeUnmount(() => {
  if (draftSaveTimeout) {
    window.clearTimeout(draftSaveTimeout)
    draftSaveTimeout = null
  }

  if (!skipDraftPersistenceOnUnmount.value) {
    persistDraftNow()
  }
})
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">{{ t('clientWizard.hero.eyebrow') }}</span>
      <h1 class="client-hero-title">{{ t('clientWizard.hero.title') }}</h1>
      <p class="client-hero-text">
        {{ t('clientWizard.hero.subtitle') }}
      </p>

      <div class="client-wizard-steps">
        <article
          v-for="step in steps"
          :key="step.id"
          class="client-wizard-step"
          :class="{ 'is-active': currentStep === step.id, 'is-complete': currentStep > step.id }"
        >
          <span class="client-wizard-step__index">{{ step.id }}</span>
          <div>
            <strong>{{ step.title }}</strong>
            <p>{{ step.text }}</p>
          </div>
        </article>
      </div>
    </section>

    <section v-if="loading" class="client-content-card client-content-card--full client-reveal-up">
      <div class="client-empty-state">
        <h3>{{ t('clientWizard.states.loadingTitle') }}</h3>
        <p class="client-muted">{{ t('clientWizard.states.loadingText') }}</p>
      </div>
    </section>

    <section v-else class="client-card-grid client-reveal-left">
      <article class="client-content-card client-content-card--full">
        <div class="client-card-head">
          <div>
            <h3 v-if="currentStep === 1">{{ t('clientWizard.steps.step1Title') }}</h3>
            <h3 v-else>{{ t('clientWizard.steps.step2Title') }}</h3>
            <p class="client-subtext">
              <template v-if="currentStep === 1">
                {{ t('clientWizard.steps.step1Text') }}
              </template>
              <template v-else>
                {{ t('clientWizard.steps.step2Text') }}
              </template>
            </p>
          </div>
          <span class="client-badge client-badge--purple">{{ t('clientWizard.steps.progress', { current: currentStep, total: totalSteps }) }}</span>
        </div>

        <div v-if="generalError" class="client-alert client-alert--error">{{ generalError }}</div>
<div v-if="successMessage" class="client-alert client-alert--success">{{ successMessage }}</div>
<div v-if="draftMessage" class="client-alert client-alert--success">{{ draftMessage }}</div>

        <template v-if="currentStep === 1">
          <div v-if="questions.length === 0" class="client-empty-state client-empty-state--inner">
            <h3>{{ t('clientWizard.states.noQuestionsTitle') }}</h3>
            <p class="client-muted">{{ t('clientWizard.states.noQuestionsText') }}</p>
          </div>

          <div v-else class="client-form-stack">
            <ClientQuestionField
              v-for="question in questions"
              :key="question.id"
              :question="question"
              :model-value="answers[question.id]"
              :error="fieldErrors[`question-${question.id}`] || ''"
              @update:model-value="answers[question.id] = $event"
            />
          </div>

          <div class="client-inline-actions">
  <button v-if="draftRestored" type="button" class="client-btn-secondary" @click="discardDraft">
    {{ t('clientWizard.actions.discardDraft') }}
  </button>
  <RouterLink :to="{ name: 'client-new-request' }" class="client-btn-secondary">
    {{ t('clientWizard.actions.cancel') }}
  </RouterLink>
  <button type="button" class="client-btn-primary" @click="goNext">
    {{ t('clientWizard.actions.continueToDetails') }}
  </button>
</div>
        </template>

        <template v-else>
          <div class="client-request-stack">
            <div class="client-request-section">
              <div class="client-request-section__head">
                <div>
                  <h4>{{ t('clientWizard.sections.applicantDetails') }}</h4>
                  <p class="client-subtext">{{ t('clientWizard.sections.applicantDetailsText') }}</p>
                </div>
              </div>

              <div class="client-form-grid">
                 
                <div class="client-form-group">
                  <label class="client-form-label">{{ t('clientWizard.fields.requestType') }} <span class="client-required-mark">*</span></label>
                  <select v-model="details.finance_request_type_id" class="client-form-control">
                    <option value="">{{ t('clientWizard.placeholders.selectRequestType') }}</option>
                    <option v-for="type in financeRequestTypes" :key="type.id" :value="String(type.id)">
                      {{ financeRequestTypeLabel(type) }}
                    </option>
                  </select>
                  <p v-if="fieldErrors['details.finance_request_type_id']" class="client-form-error">
                    {{ fieldErrors['details.finance_request_type_id'] }}
                  </p>
                </div>



                <div class="client-form-group">
                  <label class="client-form-label">{{ t('clientWizard.fields.applicantType') }} <span class="client-required-mark">*</span></label>
                  <select v-model="details.finance_type" class="client-form-control">
                    <option value="individual">{{ t('clientWizard.options.individual') }}</option>
                    <option value="company">{{ t('clientWizard.options.company') }}</option>
                  </select>
                  <p v-if="fieldErrors['details.finance_type']" class="client-form-error">{{ fieldErrors['details.finance_type'] }}</p>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">{{ t('clientWizard.fields.country') }} <span class="client-required-mark">*</span></label>
                  <select v-model="details.country" class="client-form-control">
                    <option value="">{{ t('clientWizard.placeholders.selectCountry') }}</option>
                    <option v-for="option in countryOptions" :key="option.code" :value="option.code">
                      {{ `${option.label} (${option.code})` }}
                    </option>
                  </select>
                  <p v-if="fieldErrors['details.country']" class="client-form-error">{{ fieldErrors['details.country'] }}</p>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">{{ t('clientWizard.fields.requestedAmount') }} <span class="client-required-mark">*</span></label>
                  <input
                    v-model="details.requested_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="client-form-control"
                    :placeholder="t('clientWizard.placeholders.requestedAmount')"
                  />
                  <p v-if="fieldErrors['details.requested_amount']" class="client-form-error">{{ fieldErrors['details.requested_amount'] }}</p>
                </div>

                <div v-if="details.finance_type === 'company'" class="client-form-group">
                  <label class="client-form-label">{{ t('clientWizard.fields.companyName') }} <span class="client-required-mark">*</span></label>
                  <input
                    v-model="details.company_name"
                    type="text"
                    class="client-form-control"
                    :placeholder="t('clientWizard.placeholders.companyName')"
                  />
                  <p v-if="fieldErrors['details.company_name']" class="client-form-error">{{ fieldErrors['details.company_name'] }}</p>
                </div>

                <div v-else class="client-form-group">
                  <label class="client-form-label">{{ t('clientWizard.fields.applicantName') }}</label>
                  <input :value="applicantDisplayName" type="text" class="client-form-control" readonly />
                  <p class="client-form-help">{{ t('clientWizard.help.applicantNameAuto') }}</p>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">{{ t('clientWizard.fields.email') }} <span class="client-required-mark">*</span></label>
                  <input
                    v-model="details.email"
                    type="email"
                    class="client-form-control"
                    :placeholder="t('clientWizard.placeholders.contactEmail')"
                  />
                  <p v-if="fieldErrors['details.email']" class="client-form-error">{{ fieldErrors['details.email'] }}</p>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">{{ t('clientWizard.fields.phoneNumber') }} <span class="client-required-mark">*</span></label>
                  <div class="client-form-split">
                    <select v-model="details.phone_country_code" class="client-form-control client-form-control--code">
                      <option v-for="option in phoneCodeOptions" :key="option.code" :value="option.code">
                        {{ option.label }}
                      </option>
                    </select>
                    <input
                      :value="details.phone_number"
                      type="text"
                      inputmode="numeric"
                      autocomplete="tel-national"
                      pattern="[0-9]*"
                      class="client-form-control"
                      :placeholder="t('clientWizard.placeholders.phoneNumber')"
                      @input="details.phone_number = digitsOnlyPhone(($event.target as HTMLInputElement).value)"
                    />
                  </div>
                  <p v-if="fieldErrors['details.phone_country_code']" class="client-form-error">{{ fieldErrors['details.phone_country_code'] }}</p>
                  <p v-if="fieldErrors['details.phone_number']" class="client-form-error">{{ fieldErrors['details.phone_number'] }}</p>
                </div>

               <div class="client-form-group">
  <label class="client-form-label">{{ t('clientWizard.fields.unifiedNumber') }} <span class="client-required-mark">*</span></label>
  <input
    v-model="details.unified_number"
    type="text"
    class="client-form-control"
    :placeholder="t('clientWizard.placeholders.unifiedNumber')"
  />
  <p v-if="fieldErrors['details.unified_number']" class="client-form-error">{{ fieldErrors['details.unified_number'] }}</p>
</div>

<div class="client-form-group">
  <label class="client-form-label">{{ t('clientWizard.fields.nationalAddressNumber') }} <span class="client-required-mark">*</span></label>
  <input
    v-model="details.national_address_number"
    type="text"
    class="client-form-control"
    :placeholder="t('clientWizard.placeholders.nationalAddressNumber')"
  />
  <p v-if="fieldErrors['details.national_address_number']" class="client-form-error">{{ fieldErrors['details.national_address_number'] }}</p>
</div>

<div class="client-form-group">
  <label class="client-form-label">{{ t('clientWizard.fields.nationalAddressAttachment') }} <span class="client-required-mark">*</span></label>
  <div
    class="client-upload-drop"
    @dragover.prevent
    @dragenter.prevent
    @drop="dropNationalAddressAttachment"
  >
    <input
      :id="FILE_INPUT_IDS.nationalAddress"
      type="file"
      class="client-file-input-hidden"
      @change="handleNationalAddressAttachment"
    />
    <label :for="FILE_INPUT_IDS.nationalAddress" class="client-upload-drop__surface">
      <span class="client-upload-drop__icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="17 8 12 3 7 8" />
          <line x1="12" y1="3" x2="12" y2="15" />
        </svg>
      </span>
      <span class="client-upload-drop__title">{{ t('clientWizard.upload.browse') }}</span>
      <span class="client-upload-drop__hint">{{ t('clientWizard.upload.dragHint') }}</span>
      <span class="client-upload-drop__formats">{{ t('clientWizard.upload.formatsHint') }}</span>
    </label>
  </div>
  <div v-if="nationalAddressAttachment" class="client-upload-file-row">
    <span class="client-upload-file-row__name" :title="nationalAddressAttachment.name">{{ nationalAddressAttachment.name }}</span>
    <div class="client-upload-file-row__actions">
      <button type="button" class="ghost-btn" @click="openUploadPreview(nationalAddressAttachment, t('clientWizard.fields.nationalAddressAttachment'))">
        {{ t('clientWizard.actions.preview') }}
      </button>
      <button type="button" class="ghost-btn" @click="triggerFilePick(FILE_INPUT_IDS.nationalAddress)">
        {{ t('clientWizard.upload.replace') }}
      </button>
      <button type="button" class="ghost-btn client-upload-file-row__remove" @click="clearNationalAddressAttachment">
        {{ t('clientWizard.upload.remove') }}
      </button>
    </div>
  </div>
  <p v-if="fieldErrors.national_address_attachment" class="client-form-error">{{ fieldErrors.national_address_attachment }}</p>
</div>

<div class="client-form-group client-form-group--full">
  <label class="client-form-label">{{ t('clientWizard.fields.address') }} <span class="client-required-mark">*</span></label>
  <textarea
    v-model="details.address"
    rows="4"
    class="client-form-control client-form-control--textarea"
    :placeholder="t('clientWizard.placeholders.fullAddress')"
  ></textarea>
  <p v-if="fieldErrors['details.address']" class="client-form-error">{{ fieldErrors['details.address'] }}</p>
</div>

<div class="client-form-group client-form-group--full">
  <label class="client-form-label">{{ t('clientWizard.fields.notes') }}</label>
  <textarea
    v-model="details.notes"
    rows="4"
    class="client-form-control client-form-control--textarea"
    :placeholder="t('clientWizard.placeholders.notes')"
  ></textarea>
</div>
              </div>
            </div>

            <div class="client-request-section">
              <div class="client-request-section__head">
                <div>
                  <h4>{{ t('clientWizard.sections.supportingFiles') }}</h4>
                  <p class="client-subtext">{{ t('clientWizard.sections.supportingFilesText') }}</p>
                </div>
              </div>

              <div class="client-form-grid">
                <div class="client-form-group client-form-group--full">
                  <label class="client-form-label">{{ t('clientWizard.fields.initialAttachments') }}</label>
                  <div class="client-upload-drop" @dragover.prevent @dragenter.prevent @drop="onDropAttachments">
                    <input
                      :id="FILE_INPUT_IDS.attachments"
                      type="file"
                      class="client-file-input-hidden"
                      multiple
                      @change="handleAttachments"
                    />
                    <label :for="FILE_INPUT_IDS.attachments" class="client-upload-drop__surface">
                      <span class="client-upload-drop__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                          <polyline points="17 8 12 3 7 8" />
                          <line x1="12" y1="3" x2="12" y2="15" />
                        </svg>
                      </span>
                      <span class="client-upload-drop__title">{{ t('clientWizard.upload.browse') }}</span>
                      <span class="client-upload-drop__hint">{{ t('clientWizard.upload.dragHint') }}</span>
                      <span class="client-upload-drop__formats">{{ t('clientWizard.upload.multiHint') }}</span>
                    </label>
                  </div>
                  <div v-if="attachments.length" class="client-request-stack client-upload-file-list">
                    <div v-for="(file, index) in attachments" :key="`${file.name}-${index}`" class="client-upload-file-row">
                      <span class="client-upload-file-row__name" :title="file.name">{{ file.name }}</span>
                      <div class="client-upload-file-row__actions">
                        <button type="button" class="ghost-btn" @click="openUploadPreview(file, t('clientWizard.fields.initialAttachments'))">
                          {{ t('clientWizard.actions.preview') }}
                        </button>
                        <button type="button" class="ghost-btn client-upload-file-row__remove" @click="removeAttachment(index)">
                          {{ t('clientWizard.upload.remove') }}
                        </button>
                      </div>
                    </div>
                  </div>
                  <p class="client-form-help">{{ t('clientWizard.help.initialAttachmentsOptional') }}</p>
                </div>

                <div v-if="details.finance_type === 'company'" class="client-form-group">
                  <label class="client-form-label">{{ t('clientWizard.fields.companyCrNumber') }} <span class="client-required-mark">*</span></label>
                  <input
                    v-model="details.company_cr_number"
                    type="text"
                    class="client-form-control"
                    :placeholder="t('clientWizard.placeholders.companyCrNumber')"
                  />
                  <p v-if="fieldErrors['details.company_cr_number']" class="client-form-error">{{ fieldErrors['details.company_cr_number'] }}</p>
                </div>

                <div v-if="details.finance_type === 'company'" class="client-form-group client-form-group--full">
                  <label class="client-form-label">{{ t('clientWizard.fields.companyCr') }} <span class="client-required-mark">*</span></label>
                  <div
                    class="client-upload-drop"
                    @dragover.prevent
                    @dragenter.prevent
                    @drop="dropCompanyCrFile"
                  >
                    <input
                      :id="FILE_INPUT_IDS.companyCr"
                      type="file"
                      class="client-file-input-hidden"
                      @change="handleCompanyCr"
                    />
                    <label :for="FILE_INPUT_IDS.companyCr" class="client-upload-drop__surface">
                      <span class="client-upload-drop__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                          <polyline points="17 8 12 3 7 8" />
                          <line x1="12" y1="3" x2="12" y2="15" />
                        </svg>
                      </span>
                      <span class="client-upload-drop__title">{{ t('clientWizard.upload.browse') }}</span>
                      <span class="client-upload-drop__hint">{{ t('clientWizard.upload.dragHint') }}</span>
                      <span class="client-upload-drop__formats">{{ t('clientWizard.upload.formatsHint') }}</span>
                    </label>
                  </div>
                  <div v-if="companyCr" class="client-upload-file-row">
                    <span class="client-upload-file-row__name" :title="companyCr.name">{{ companyCr.name }}</span>
                    <div class="client-upload-file-row__actions">
                      <button type="button" class="ghost-btn" @click="openUploadPreview(companyCr, t('clientWizard.fields.companyCr'))">
                        {{ t('clientWizard.actions.preview') }}
                      </button>
                      <button type="button" class="ghost-btn" @click="triggerFilePick(FILE_INPUT_IDS.companyCr)">
                        {{ t('clientWizard.upload.replace') }}
                      </button>
                      <button type="button" class="ghost-btn client-upload-file-row__remove" @click="clearCompanyCr">
                        {{ t('clientWizard.upload.remove') }}
                      </button>
                    </div>
                  </div>
                  <p v-if="fieldErrors.company_cr" class="client-form-error">{{ fieldErrors.company_cr }}</p>
                </div>
              </div>
            </div>

            <div v-if="details.finance_type === 'company'" class="client-request-section">
              <div class="client-request-section__head">
                <div>
                  <h4>{{ t('clientWizard.sections.shareholders') }}</h4>
                  <p class="client-subtext">{{ t('clientWizard.sections.shareholdersText') }}</p>
                </div>

                <button type="button" class="client-btn-secondary" @click="addShareholder">{{ t('clientWizard.actions.addShareholder') }}</button>
              </div>

              <p v-if="fieldErrors.shareholders" class="client-form-error">{{ fieldErrors.shareholders }}</p>

              <div class="client-request-stack">
                <div
                  v-for="(shareholder, index) in shareholders"
                  :key="index"
                  class="client-shareholder-card"
                >
                  <div class="client-card-head">
                    <div>
                      <h3>{{ t('clientWizard.sections.shareholderCardTitle', { index: index + 1 }) }}</h3>
                      <p class="client-subtext">{{ t('clientWizard.sections.shareholderCardText') }}</p>
                    </div>

                    <button type="button" class="client-btn-secondary" @click="removeShareholder(index)">{{ t('clientWizard.actions.remove') }}</button>
                  </div>

                  <div class="client-form-grid">
                    <div class="client-form-group">
                      <label class="client-form-label">{{ t('clientWizard.fields.shareholderName') }} <span class="client-required-mark">*</span></label>
                      <input
                        v-model="shareholder.name"
                        type="text"
                        class="client-form-control"
                        :placeholder="t('clientWizard.placeholders.shareholderName')"
                      />
                      <p v-if="fieldErrors[`shareholders.${index}.name`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.name`] }}</p>
                    </div>

                    <div class="client-form-group">
                      <label class="client-form-label">{{ t('clientWizard.fields.phoneNumber') }} <span class="client-required-mark">*</span></label>
                      <div class="client-form-split">
                        <select v-model="shareholder.phone_country_code" class="client-form-control client-form-control--code">
                          <option v-for="option in phoneCodeOptions" :key="option.code" :value="option.code">
                            {{ option.label }}
                          </option>
                        </select>
                        <input
                          :value="shareholder.phone_number"
                          type="text"
                          inputmode="numeric"
                          autocomplete="tel-national"
                          pattern="[0-9]*"
                          class="client-form-control"
                          :placeholder="t('clientWizard.placeholders.shareholderPhoneNumber')"
                          @input="shareholder.phone_number = digitsOnlyPhone(($event.target as HTMLInputElement).value)"
                        />
                      </div>
                      <p v-if="fieldErrors[`shareholders.${index}.phone_country_code`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.phone_country_code`] }}</p>
                      <p v-if="fieldErrors[`shareholders.${index}.phone_number`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.phone_number`] }}</p>
                    </div>

                    <div class="client-form-group">
                      <label class="client-form-label">{{ t('clientWizard.fields.shareholderIdNumber') }} <span class="client-required-mark">*</span></label>
                      <input
                        v-model="shareholder.id_number"
                        type="text"
                        class="client-form-control"
                        :placeholder="t('clientWizard.placeholders.shareholderIdNumber')"
                      />
                      <p v-if="fieldErrors[`shareholders.${index}.id_number`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.id_number`] }}</p>
                    </div>

                    <div class="client-form-group">
                      <label class="client-form-label">{{ t('clientWizard.fields.shareholderIdUpload') }} <span class="client-required-mark">*</span></label>
                      <div
                        class="client-upload-drop"
                        @dragover.prevent
                        @dragenter.prevent
                        @drop="dropShareholderIdFile(index, $event)"
                      >
                        <input
                          :id="shareholderFileInputId(index)"
                          type="file"
                          class="client-file-input-hidden"
                          @change="handleShareholderFile(index, $event)"
                        />
                        <label :for="shareholderFileInputId(index)" class="client-upload-drop__surface">
                          <span class="client-upload-drop__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                              <polyline points="17 8 12 3 7 8" />
                              <line x1="12" y1="3" x2="12" y2="15" />
                            </svg>
                          </span>
                          <span class="client-upload-drop__title">{{ t('clientWizard.upload.browse') }}</span>
                          <span class="client-upload-drop__hint">{{ t('clientWizard.upload.dragHint') }}</span>
                          <span class="client-upload-drop__formats">{{ t('clientWizard.upload.formatsHint') }}</span>
                        </label>
                      </div>
                      <div v-if="shareholder.id_file" class="client-upload-file-row">
                        <span class="client-upload-file-row__name" :title="shareholder.id_file.name">{{ shareholder.id_file.name }}</span>
                        <div class="client-upload-file-row__actions">
                          <button
                            type="button"
                            class="ghost-btn"
                            @click="openUploadPreview(shareholder.id_file, t('clientWizard.fields.shareholderIdUpload'))"
                          >
                            {{ t('clientWizard.actions.preview') }}
                          </button>
                          <button type="button" class="ghost-btn" @click="triggerFilePick(shareholderFileInputId(index))">
                            {{ t('clientWizard.upload.replace') }}
                          </button>
                          <button type="button" class="ghost-btn client-upload-file-row__remove" @click="clearShareholderIdFile(index)">
                            {{ t('clientWizard.upload.remove') }}
                          </button>
                        </div>
                      </div>
                      <p v-if="fieldErrors[`shareholders.${index}.id_file`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.id_file`] }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="client-inline-actions">
  <button v-if="draftRestored" type="button" class="client-btn-secondary" @click="discardDraft">
    {{ t('clientWizard.actions.discardDraft') }}
  </button>
  <button type="button" class="client-btn-secondary" @click="goBack">
    {{ t('clientWizard.actions.back') }}
  </button>
  <button type="button" class="client-btn-primary" :disabled="submitting" @click="submitRequest">
    {{ submitting ? t('clientWizard.actions.submitting') : t('clientWizard.actions.submitRequest') }}
  </button>
</div>
        </template>
      </article>
    </section>

    <AppFilePreviewModal
      :model-value="uploadPreviewOpen"
      @update:model-value="(value) => { uploadPreviewOpen = value }"
      :title="uploadPreviewTitle || t('clientWizard.sections.supportingFiles')"
      :file-name="uploadPreviewFile?.name || ''"
      :mime-type="uploadPreviewFile?.type || ''"
      :local-file="uploadPreviewFile"
    />
  </div>
</template>
