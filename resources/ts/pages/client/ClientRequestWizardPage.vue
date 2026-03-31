<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import ClientQuestionField from './inc/ClientQuestionField.vue'
import { useAuthStore } from '../../stores/auth'
import { getRequestQuestions, submitClientRequest, type ClientQuestion } from '@/services/clientRequests'

const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const submitting = ref(false)
const currentStep = ref(1)
const totalSteps = 2

const questions = ref<ClientQuestion[]>([])
const answers = reactive<Record<number, unknown>>({})
const attachments = ref<File[]>([])
const nationalAddressAttachment = ref<File | null>(null)
const companyCr = ref<File | null>(null)
const shareholders = ref<Array<{ name: string; phone_country_code: string; phone_number: string; id_number: string; id_file: File | null }>>([])

const fieldErrors = reactive<Record<string, string>>({})
const generalError = ref('')
const successMessage = ref('')

const countryOptions = [
  { code: 'SA', label: 'Saudi Arabia' },

  { code: 'OM', label: 'Oman' },
  { code: 'AE', label: 'United Arab Emirates' },
  { code: 'KW', label: 'Kuwait' },
  { code: 'QA', label: 'Qatar' },
  { code: 'BH', label: 'Bahrain' },
  { code: 'EG', label: 'Egypt' },
  { code: 'JO', label: 'Jordan' },
  { code: 'LB', label: 'Lebanon' },
  { code: 'US', label: 'United States' },
  { code: 'GB', label: 'United Kingdom' },
  { code: 'IN', label: 'India' },
  { code: 'PK', label: 'Pakistan' },
  { code: 'BD', label: 'Bangladesh' },
  { code: 'TR', label: 'Turkey' },
  { code: 'DE', label: 'Germany' },
  { code: 'FR', label: 'France' },
  { code: 'CN', label: 'China' },
  { code: 'MY', label: 'Malaysia' },
  { code: 'SG', label: 'Singapore' },
]

const phoneCodeOptions = [
  { code: '+966', label: 'Saudi Arabia (+966)' },

  { code: '+968', label: 'Oman (+968)' },
  { code: '+971', label: 'UAE (+971)' },
  { code: '+965', label: 'Kuwait (+965)' },
  { code: '+974', label: 'Qatar (+974)' },
  { code: '+973', label: 'Bahrain (+973)' },
  { code: '+20', label: 'Egypt (+20)' },
  { code: '+962', label: 'Jordan (+962)' },
  { code: '+961', label: 'Lebanon (+961)' },
  { code: '+1', label: 'US / Canada (+1)' },
  { code: '+44', label: 'UK (+44)' },
  { code: '+91', label: 'India (+91)' },
  { code: '+92', label: 'Pakistan (+92)' },
  { code: '+880', label: 'Bangladesh (+880)' },
  { code: '+90', label: 'Turkey (+90)' },
]

const details = reactive({
  finance_type: 'individual' as 'individual' | 'company',
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

const steps = [
  { id: 1, title: 'Questions', text: 'Answer the request questionnaire.' },
  { id: 2, title: 'Details', text: 'Add applicant contact details, address, and company information when required.' },
]

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

function hydrateApplicantDefaults() {
  if (!details.email && auth.user?.email) {
    details.email = auth.user.email
  }

  if (!details.phone_number && auth.user?.phone) {
    details.phone_number = auth.user.phone
  }
}

function addShareholder() {
  shareholders.value.push({
    name: '',
    phone_country_code: '+966',
    phone_number: '',
    id_number: '',
    id_file: null,
  })
}

function removeShareholder(index: number) {
  shareholders.value.splice(index, 1)
}

function handleAttachments(event: Event) {
  const input = event.target as HTMLInputElement
  attachments.value = Array.from(input.files || [])
}

function handleNationalAddressAttachment(event: Event) {
  const input = event.target as HTMLInputElement
  nationalAddressAttachment.value = input.files?.[0] || null
}

function handleCompanyCr(event: Event) {
  const input = event.target as HTMLInputElement
  companyCr.value = input.files?.[0] || null
}

function handleShareholderFile(index: number, event: Event) {
  const input = event.target as HTMLInputElement
  shareholders.value[index].id_file = input.files?.[0] || null
}

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
      fieldErrors[`question-${question.id}`] = `${question.question_text} is required.`
      hasError = true
    }
  }

  return !hasError
}

function validateStepTwo() {
  clearErrors()
  let hasError = false

  if (!details.finance_type) {
    fieldErrors['details.finance_type'] = 'Applicant type is required.'
    hasError = true
  }

  if (!details.country.trim()) {
    fieldErrors['details.country'] = 'Country is required.'
    hasError = true
  }

  if (!String(details.requested_amount).trim()) {
    fieldErrors['details.requested_amount'] = 'Requested amount is required.'
    hasError = true
  }

  if (!details.email.trim()) {
    fieldErrors['details.email'] = 'Email is required.'
    hasError = true
  }

 if (!details.phone_number.trim()) {
  fieldErrors['details.phone_number'] = 'Phone number is required.'
  hasError = true
}

if (!details.unified_number.trim()) {
  fieldErrors['details.unified_number'] = 'Unified number is required.'
  hasError = true
}

if (!details.national_address_number.trim()) {
  fieldErrors['details.national_address_number'] = 'National address number is required.'
  hasError = true
}

if (!details.address.trim()) {
  fieldErrors['details.address'] = 'Address is required.'
  hasError = true
}

if (!nationalAddressAttachment.value) {
  fieldErrors.national_address_attachment = 'National address attachment is required.'
  hasError = true
}

  if (details.finance_type === 'company') {
    if (!details.company_name.trim()) {
      fieldErrors['details.company_name'] = 'Company name is required.'
      hasError = true
    }

    if (!details.company_cr_number.trim()) {
      fieldErrors['details.company_cr_number'] = 'Company CR number is required.'
      hasError = true
    }

    if (!companyCr.value) {
      fieldErrors.company_cr = 'Company CR is required.'
      hasError = true
    }

    if (shareholders.value.length === 0) {
      fieldErrors.shareholders = 'At least one shareholder is required.'
      hasError = true
    }

    shareholders.value.forEach((shareholder, index) => {
      if (!shareholder.name.trim()) {
        fieldErrors[`shareholders.${index}.name`] = 'Shareholder name is required.'
        hasError = true
      }

      if (!shareholder.phone_country_code.trim()) {
        fieldErrors[`shareholders.${index}.phone_country_code`] = 'Shareholder country code is required.'
        hasError = true
      }

      if (!shareholder.phone_number.trim()) {
        fieldErrors[`shareholders.${index}.phone_number`] = 'Shareholder phone number is required.'
        hasError = true
      }

      if (!shareholder.id_number.trim()) {
        fieldErrors[`shareholders.${index}.id_number`] = 'Shareholder ID number is required.'
        hasError = true
      }

      if (!shareholder.id_file) {
        fieldErrors[`shareholders.${index}.id_file`] = 'Shareholder ID file is required.'
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

    questions.value.forEach((question) => {
      if (!(question.id in answers)) {
        answers[question.id] = question.question_type === 'checkbox' ? [] : ''
      }
    })
  } catch (error: any) {
    generalError.value = error?.response?.data?.message || 'Unable to load the request questions.'
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

    generalError.value = responseMessage || 'Unable to submit your request right now.'
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  hydrateApplicantDefaults()
  loadQuestions()
})
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">Request Wizard</span>
      <h1 class="client-hero-title">Answer the guided questions, add your request details, and submit everything in one flow.</h1>
      <p class="client-hero-text">
        Capture the applicant details first, then upload the required company and address files in a cleaner layout.
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
        <h3>Loading request wizard</h3>
        <p class="client-muted">We are preparing the latest questions configured by the admin.</p>
      </div>
    </section>

    <section v-else class="client-card-grid client-reveal-left">
      <article class="client-content-card client-content-card--full">
        <div class="client-card-head">
          <div>
            <h3 v-if="currentStep === 1">Step 1 · Answer the questions</h3>
            <h3 v-else>Step 2 · Add your request details</h3>
            <p class="client-subtext">
              <template v-if="currentStep === 1">
                Complete the configured questionnaire first.
              </template>
              <template v-else>
                Add the applicant details, attachments, and company information when required.
              </template>
            </p>
          </div>
          <span class="client-badge client-badge--purple">Step {{ currentStep }} of {{ totalSteps }}</span>
        </div>

        <div v-if="generalError" class="client-alert client-alert--error">{{ generalError }}</div>
        <div v-if="successMessage" class="client-alert client-alert--success">{{ successMessage }}</div>

        <template v-if="currentStep === 1">
          <div v-if="questions.length === 0" class="client-empty-state client-empty-state--inner">
            <h3>No active questions yet</h3>
            <p class="client-muted">The admin has not published request questions yet. You can still continue once they are added.</p>
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
            <RouterLink :to="{ name: 'client-new-request' }" class="client-btn-secondary">Cancel</RouterLink>
            <button type="button" class="client-btn-primary" @click="goNext">Continue to Details</button>
          </div>
        </template>

        <template v-else>
          <div class="client-request-stack">
            <div class="client-request-section">
              <div class="client-request-section__head">
                <div>
                  <h4>Applicant details</h4>
                  <p class="client-subtext">Start with the applicant type, country, amount, and contact information.</p>
                </div>
              </div>

              <div class="client-form-grid">
                <div class="client-form-group">
                  <label class="client-form-label">Applicant Type <span class="client-required-mark">*</span></label>
                  <select v-model="details.finance_type" class="client-form-control">
                    <option value="individual">Individual</option>
                    <option value="company">Company</option>
                  </select>
                  <p v-if="fieldErrors['details.finance_type']" class="client-form-error">{{ fieldErrors['details.finance_type'] }}</p>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">Country <span class="client-required-mark">*</span></label>
                  <select v-model="details.country" class="client-form-control">
                    <option value="">-- Select country --</option>
                    <option v-for="option in countryOptions" :key="option.code" :value="option.label">
                      {{ option.label }}
                    </option>
                  </select>
                  <p v-if="fieldErrors['details.country']" class="client-form-error">{{ fieldErrors['details.country'] }}</p>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">Requested Amount <span class="client-required-mark">*</span></label>
                  <input
                    v-model="details.requested_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="client-form-control"
                    placeholder="Enter requested amount"
                  />
                  <p v-if="fieldErrors['details.requested_amount']" class="client-form-error">{{ fieldErrors['details.requested_amount'] }}</p>
                </div>

                <div v-if="details.finance_type === 'company'" class="client-form-group">
                  <label class="client-form-label">Company Name <span class="client-required-mark">*</span></label>
                  <input
                    v-model="details.company_name"
                    type="text"
                    class="client-form-control"
                    placeholder="Enter company name"
                  />
                  <p v-if="fieldErrors['details.company_name']" class="client-form-error">{{ fieldErrors['details.company_name'] }}</p>
                </div>

                <div v-else class="client-form-group">
                  <label class="client-form-label">Applicant Name</label>
                  <input :value="applicantDisplayName" type="text" class="client-form-control" readonly />
                  <p class="client-form-help">This name is pulled automatically from the user profile.</p>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">Email <span class="client-required-mark">*</span></label>
                  <input
                    v-model="details.email"
                    type="email"
                    class="client-form-control"
                    placeholder="Enter contact email"
                  />
                  <p v-if="fieldErrors['details.email']" class="client-form-error">{{ fieldErrors['details.email'] }}</p>
                </div>

                <div class="client-form-group">
                  <label class="client-form-label">Phone Number <span class="client-required-mark">*</span></label>
                  <div class="client-form-split">
                    <select v-model="details.phone_country_code" class="client-form-control client-form-control--code">
                      <option v-for="option in phoneCodeOptions" :key="option.code" :value="option.code">
                        {{ option.label }}
                      </option>
                    </select>
                    <input
                      v-model="details.phone_number"
                      type="text"
                      class="client-form-control"
                      placeholder="Enter phone number"
                    />
                  </div>
                  <p v-if="fieldErrors['details.phone_country_code']" class="client-form-error">{{ fieldErrors['details.phone_country_code'] }}</p>
                  <p v-if="fieldErrors['details.phone_number']" class="client-form-error">{{ fieldErrors['details.phone_number'] }}</p>
                </div>

               <div class="client-form-group">
  <label class="client-form-label">Unified Number <span class="client-required-mark">*</span></label>
  <input
    v-model="details.unified_number"
    type="text"
    class="client-form-control"
    placeholder="Enter unified number"
  />
  <p v-if="fieldErrors['details.unified_number']" class="client-form-error">{{ fieldErrors['details.unified_number'] }}</p>
</div>

<div class="client-form-group">
  <label class="client-form-label">National Address Number <span class="client-required-mark">*</span></label>
  <input
    v-model="details.national_address_number"
    type="text"
    class="client-form-control"
    placeholder="Enter national address number"
  />
  <p v-if="fieldErrors['details.national_address_number']" class="client-form-error">{{ fieldErrors['details.national_address_number'] }}</p>
</div>

<div class="client-form-group">
  <label class="client-form-label">National Address Attachment <span class="client-required-mark">*</span></label>
  <input type="file" class="client-form-control" @change="handleNationalAddressAttachment" />
  <p v-if="fieldErrors.national_address_attachment" class="client-form-error">{{ fieldErrors.national_address_attachment }}</p>
</div>

<div class="client-form-group client-form-group--full">
  <label class="client-form-label">Address <span class="client-required-mark">*</span></label>
  <textarea
    v-model="details.address"
    rows="4"
    class="client-form-control client-form-control--textarea"
    placeholder="Enter full address"
  ></textarea>
  <p v-if="fieldErrors['details.address']" class="client-form-error">{{ fieldErrors['details.address'] }}</p>
</div>

<div class="client-form-group client-form-group--full">
  <label class="client-form-label">Notes</label>
  <textarea
    v-model="details.notes"
    rows="4"
    class="client-form-control client-form-control--textarea"
    placeholder="Any notes for the admin review"
  ></textarea>
</div>
              </div>
            </div>

            <div class="client-request-section">
              <div class="client-request-section__head">
                <div>
                  <h4>Supporting files</h4>
                  <p class="client-subtext">Keep optional files and required company files in their own section.</p>
                </div>
              </div>

              <div class="client-form-grid">
                <div class="client-form-group client-form-group--full">
                  <label class="client-form-label">Initial Attachments</label>
                  <input type="file" class="client-form-control" multiple @change="handleAttachments" />
                  <p class="client-form-help">Optional initial files for the request.</p>
                </div>

                <div v-if="details.finance_type === 'company'" class="client-form-group">
                  <label class="client-form-label">Company CR Number <span class="client-required-mark">*</span></label>
                  <input
                    v-model="details.company_cr_number"
                    type="text"
                    class="client-form-control"
                    placeholder="Enter company CR number"
                  />
                  <p v-if="fieldErrors['details.company_cr_number']" class="client-form-error">{{ fieldErrors['details.company_cr_number'] }}</p>
                </div>

                <div v-if="details.finance_type === 'company'" class="client-form-group client-form-group--full">
                  <label class="client-form-label">Company CR <span class="client-required-mark">*</span></label>
                  <input type="file" class="client-form-control" @change="handleCompanyCr" />
                  <p v-if="fieldErrors.company_cr" class="client-form-error">{{ fieldErrors.company_cr }}</p>
                </div>
              </div>
            </div>

            <div v-if="details.finance_type === 'company'" class="client-request-section">
              <div class="client-request-section__head">
                <div>
                  <h4>Shareholders</h4>
                  <p class="client-subtext">Each shareholder keeps their own card so the top form no longer compresses.</p>
                </div>

                <button type="button" class="client-btn-secondary" @click="addShareholder">Add Shareholder</button>
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
                      <h3>Shareholder {{ index + 1 }}</h3>
                      <p class="client-subtext">Add the shareholder contact details, ID number, and ID attachment.</p>
                    </div>

                    <button type="button" class="client-btn-secondary" @click="removeShareholder(index)">Remove</button>
                  </div>

                  <div class="client-form-grid">
                    <div class="client-form-group">
                      <label class="client-form-label">Shareholder Name <span class="client-required-mark">*</span></label>
                      <input
                        v-model="shareholder.name"
                        type="text"
                        class="client-form-control"
                        placeholder="Enter shareholder name"
                      />
                      <p v-if="fieldErrors[`shareholders.${index}.name`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.name`] }}</p>
                    </div>

                    <div class="client-form-group">
                      <label class="client-form-label">Phone Number <span class="client-required-mark">*</span></label>
                      <div class="client-form-split">
                        <select v-model="shareholder.phone_country_code" class="client-form-control client-form-control--code">
                          <option v-for="option in phoneCodeOptions" :key="option.code" :value="option.code">
                            {{ option.label }}
                          </option>
                        </select>
                        <input
                          v-model="shareholder.phone_number"
                          type="text"
                          class="client-form-control"
                          placeholder="Enter shareholder phone number"
                        />
                      </div>
                      <p v-if="fieldErrors[`shareholders.${index}.phone_country_code`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.phone_country_code`] }}</p>
                      <p v-if="fieldErrors[`shareholders.${index}.phone_number`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.phone_number`] }}</p>
                    </div>

                    <div class="client-form-group">
                      <label class="client-form-label">Shareholder ID Number <span class="client-required-mark">*</span></label>
                      <input
                        v-model="shareholder.id_number"
                        type="text"
                        class="client-form-control"
                        placeholder="Enter shareholder ID number"
                      />
                      <p v-if="fieldErrors[`shareholders.${index}.id_number`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.id_number`] }}</p>
                    </div>

                    <div class="client-form-group">
                      <label class="client-form-label">Shareholder ID Upload <span class="client-required-mark">*</span></label>
                      <input type="file" class="client-form-control" @change="handleShareholderFile(index, $event)" />
                      <p v-if="fieldErrors[`shareholders.${index}.id_file`]" class="client-form-error">{{ fieldErrors[`shareholders.${index}.id_file`] }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="client-inline-actions">
            <button type="button" class="client-btn-secondary" @click="goBack">Back</button>
            <button type="button" class="client-btn-primary" :disabled="submitting" @click="submitRequest">
              {{ submitting ? 'Submitting…' : 'Submit Request' }}
            </button>
          </div>
        </template>
      </article>
    </section>
  </div>
</template>