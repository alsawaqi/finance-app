<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ClientQuestionField from './inc/ClientQuestionField.vue'
import { getRequestQuestions, submitClientRequest, type ClientQuestion } from '@/services/clientRequests'

const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const submitting = ref(false)
const currentStep = ref(1)
const questions = ref<ClientQuestion[]>([])
const generalError = ref('')
const successMessage = ref('')
const fieldErrors = reactive<Record<string, string>>({})

const details = reactive({
  name: auth.user?.name ?? '',
  country: '',
  requested_amount: '',
  finance_type: 'individual' as 'individual' | 'company',
  notes: '',
})

const answers = reactive<Record<number, unknown>>({})
const attachments = ref<File[]>([])

const totalSteps = 2
const steps = [
  { id: 1, title: 'Questions', text: 'Answer the guided questions prepared by the admin.' },
  { id: 2, title: 'Your Details', text: 'Add your basic information and submit the request.' },
]

const answerPayload = computed(() => {
  return questions.value.map((question) => ({
    question_id: question.id,
    value: answers[question.id] ?? null,
  }))
})

const reviewRows = computed(() => [
  ['Full Name', details.name || '—'],
  ['Country', details.country || '—'],
  ['Requested Amount', details.requested_amount ? `OMR ${details.requested_amount}` : '—'],
  ['Finance Type', details.finance_type === 'company' ? 'Company' : 'Individual'],
  ['Initial Files', attachments.value.length ? `${attachments.value.length} file(s)` : 'No files attached'],
])

function clearErrors() {
  generalError.value = ''
  Object.keys(fieldErrors).forEach((key) => {
    delete fieldErrors[key]
  })
}

function hasValue(value: unknown) {
  if (Array.isArray(value)) return value.length > 0
  return value !== null && value !== undefined && String(value).trim() !== ''
}

function validateStepOne() {
  clearErrors()
  for (const question of questions.value) {
    if (question.is_required && !hasValue(answers[question.id])) {
      fieldErrors[`question-${question.id}`] = `${question.question_text} is required.`
    }
  }
  return Object.keys(fieldErrors).length === 0
}

function validateStepTwo() {
  clearErrors()
  if (!details.name.trim()) fieldErrors['details.name'] = 'Full name is required.'
  if (!details.country.trim()) fieldErrors['details.country'] = 'Country is required.'
  if (!String(details.requested_amount).trim()) fieldErrors['details.requested_amount'] = 'Requested amount is required.'
  else if (Number(details.requested_amount) < 0) fieldErrors['details.requested_amount'] = 'Requested amount must be valid.'
  if (!details.finance_type) fieldErrors['details.finance_type'] = 'Finance type is required.'
  return Object.keys(fieldErrors).length === 0
}

function goNext() {
  if (!validateStepOne()) return
  currentStep.value = 2
}

function goBack() {
  clearErrors()
  currentStep.value = 1
}

function onFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  attachments.value = Array.from(target.files || [])
}

async function loadQuestions() {
  loading.value = true
  generalError.value = ''
  try {
    const { data } = await getRequestQuestions()
    questions.value = data.questions || []
  } catch (error) {
    console.error(error)
    generalError.value = 'Unable to load the request questions right now.'
  } finally {
    loading.value = false
  }
}

async function submitRequest() {
  if (!validateStepTwo()) return

  submitting.value = true
  clearErrors()
  successMessage.value = ''

  try {
    const { data } = await submitClientRequest({
      answers: answerPayload.value,
      details: {
        name: details.name,
        country: details.country,
        requested_amount: details.requested_amount,
        finance_type: details.finance_type,
        notes: details.notes,
      },
      attachments: attachments.value,
    })

    successMessage.value = data.message
    await router.replace({ name: 'client-request-details', params: { id: data.request.id } })
  } catch (error: any) {
    const responseErrors = error?.response?.data?.errors || {}
    const responseMessage = error?.response?.data?.message

    Object.entries(responseErrors).forEach(([key, messages]) => {
      if (Array.isArray(messages) && messages.length > 0) {
        if (key.startsWith('answers.')) {
          const id = key.split('.')[1]
          fieldErrors[`question-${id}`] = String(messages[0])
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

onMounted(loadQuestions)
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">Request Wizard</span>
      <h1 class="client-hero-title">Answer the guided questions, add your details, and submit the request in a simple flow.</h1>
      <p class="client-hero-text">
        This wizard follows the exact process you described: questions first, then your core details, then the request moves to admin review.
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
            <h3 v-else>Step 2 · Add your details and submit</h3>
            <p class="client-subtext">
              <template v-if="currentStep === 1">
                Choose the answers exactly as they were configured by the admin setup.
              </template>
              <template v-else>
                Add the core submission details and any initial files you want to include with the request.
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
              :error="fieldErrors[`question-${question.id}`] || fieldErrors[`answers.${question.id}`] || ''"
              @update:model-value="answers[question.id] = $event"
            />
          </div>

          <div class="client-inline-actions">
            <RouterLink :to="{ name: 'client-new-request' }" class="client-btn-secondary">Cancel</RouterLink>
            <button type="button" class="client-btn-primary" @click="goNext">Continue to Details</button>
          </div>
        </template>

        <template v-else>
          <div class="client-form-grid">
            <div class="client-form-group">
              <label class="client-form-label">Full Name <span class="client-required-mark">*</span></label>
              <input v-model="details.name" type="text" class="client-form-control" placeholder="Enter your full name" />
              <p v-if="fieldErrors['details.name']" class="client-form-error">{{ fieldErrors['details.name'] }}</p>
            </div>

            <div class="client-form-group">
              <label class="client-form-label">Country <span class="client-required-mark">*</span></label>
              

              <select name="country" id="country-select" v-model="details.country" class="client-form-control">
                      <option value="">-- Please choose a country --</option>
                      <option value="AF">Afghanistan</option>
                      <option value="AL">Albania</option>
                      <option value="DZ">Algeria</option>
                      <option value="AS">American Samoa</option>
                      <option value="AD">Andorra</option>
                      <option value="AO">Angola</option>
                      <option value="AI">Anguilla</option>
                      <option value="AQ">Antarctica</option>
                      <option value="AG">Antigua and Barbuda</option>
                      <option value="AR">Argentina</option>
                      <option value="AM">Armenia</option>
                      <option value="AW">Aruba</option>
                      <option value="AU">Australia</option>
                      <option value="AT">Austria</option>
                      <option value="AZ">Azerbaijan</option>
                      <option value="BS">Bahamas</option>
                      <option value="BH">Bahrain</option>
                      <option value="BD">Bangladesh</option>
                      <option value="BB">Barbados</option>
                      <option value="BY">Belarus</option>
                      <option value="BE">Belgium</option>
                      <option value="BZ">Belize</option>
                      <option value="BJ">Benin</option>
                      <option value="BM">Bermuda</option>
                      <option value="BT">Bhutan</option>
                      <option value="BO">Bolivia</option>
                      <option value="BA">Bosnia and Herzegovina</option>
                      <option value="BW">Botswana</option>
                      <option value="BR">Brazil</option>
                      <option value="IO">British Indian Ocean Territory</option>
                      <option value="BN">Brunei Darussalam</option>
                      <option value="BG">Bulgaria</option>
                      <option value="BF">Burkina Faso</option>
                      <option value="BI">Burundi</option>
                      <option value="CV">Cabo Verde</option>
                      <option value="KH">Cambodia</option>
                      <option value="CM">Cameroon</option>
                      <option value="CA">Canada</option>
                      <option value="KY">Cayman Islands</option>
                      <option value="CF">Central African Republic</option>
                      <option value="TD">Chad</option>
                      <option value="CL">Chile</option>
                      <option value="CN">China</option>
                      <option value="CX">Christmas Island</option>
                      <option value="CC">Cocos (Keeling) Islands</option>
                      <option value="CO">Colombia</option>
                      <option value="KM">Comoros</option>
                      <option value="CG">Congo</option>
                      <option value="CD">Congo, Democratic Republic of the</option>
                      <option value="CK">Cook Islands</option>
                      <option value="CR">Costa Rica</option>
                      <option value="HR">Croatia</option>
                      <option value="CU">Cuba</option>
                      <option value="CW">Curaçao</option>
                      <option value="CY">Cyprus</option>
                      <option value="CZ">Czechia</option>
                      <option value="CI">Côte d'Ivoire</option>
                      <option value="DK">Denmark</option>
                      <option value="DJ">Djibouti</option>
                      <option value="DM">Dominica</option>
                      <option value="DO">Dominican Republic</option>
                      <option value="EC">Ecuador</option>
                      <option value="EG">Egypt</option>
                      <option value="SV">El Salvador</option>
                      <option value="GQ">Equatorial Guinea</option>
                      <option value="ER">Eritrea</option>
                      <option value="EE">Estonia</option>
                      <option value="SZ">Eswatini</option>
                      <option value="ET">Ethiopia</option>
                      <option value="FK">Falkland Islands (Malvinas)</option>
                      <option value="FO">Faroe Islands</option>
                      <option value="FJ">Fiji</option>
                      <option value="FI">Finland</option>
                      <option value="FR">France</option>
                      <option value="GF">French Guiana</option>
                      <option value="PF">French Polynesia</option>
                      <option value="TF">French Southern Territories</option>
                      <option value="GA">Gabon</option>
                      <option value="GM">Gambia</option>
                      <option value="GE">Georgia</option>
                      <option value="DE">Germany</option>
                      <option value="GH">Ghana</option>
                      <option value="GI">Gibraltar</option>
                      <option value="GR">Greece</option>
                      <option value="GL">Greenland</option>
                      <option value="GD">Grenada</option>
                      <option value="GP">Guadeloupe</option>
                      <option value="GU">Guam</option>
                      <option value="GT">Guatemala</option>
                      <option value="GG">Guernsey</option>
                      <option value="GN">Guinea</option>
                      <option value="GW">Guinea-Bissau</option>
                      <option value="GY">Guyana</option>
                      <option value="HT">Haiti</option>
                      <option value="VA">Holy See</option>
                      <option value="HN">Honduras</option>
                      <option value="HK">Hong Kong</option>
                      <option value="HU">Hungary</option>
                      <option value="IS">Iceland</option>
                      <option value="IN">India</option>
                      <option value="ID">Indonesia</option>
                      <option value="IR">Iran</option>
                      <option value="IQ">Iraq</option>
                      <option value="IE">Ireland</option>
                      <option value="IM">Isle of Man</option>
                     
                      <option value="IT">Italy</option>
                      <option value="JM">Jamaica</option>
                      <option value="JP">Japan</option>
                      <option value="JE">Jersey</option>
                      <option value="JO">Jordan</option>
                      <option value="KZ">Kazakhstan</option>
                      <option value="KE">Kenya</option>
                      <option value="KI">Kiribati</option>
                      <option value="KP">Korea, Democratic People's Republic of</option>
                      <option value="KR">Korea, Republic of</option>
                      <option value="KW">Kuwait</option>
                      <option value="KG">Kyrgyzstan</option>
                      <option value="LA">Lao People's Democratic Republic</option>
                      <option value="LV">Latvia</option>
                      <option value="LB">Lebanon</option>
                      <option value="LS">Lesotho</option>
                      <option value="LR">Liberia</option>
                      <option value="LY">Libya</option>
                      <option value="LI">Liechtenstein</option>
                      <option value="LT">Lithuania</option>
                      <option value="LU">Luxembourg</option>
                      <option value="MO">Macao</option>
                      <option value="MG">Madagascar</option>
                      <option value="MW">Malawi</option>
                      <option value="MY">Malaysia</option>
                      <option value="MV">Maldives</option>
                      <option value="ML">Mali</option>
                      <option value="MT">Malta</option>
                      <option value="MH">Marshall Islands</option>
                      <option value="MQ">Martinique</option>
                      <option value="MR">Mauritania</option>
                      <option value="MU">Mauritius</option>
                      <option value="YT">Mayotte</option>
                      <option value="MX">Mexico</option>
                      <option value="FM">Micronesia</option>
                      <option value="MD">Moldova</option>
                      <option value="MC">Monaco</option>
                      <option value="MN">Mongolia</option>
                      <option value="ME">Montenegro</option>
                      <option value="MS">Montserrat</option>
                      <option value="MA">Morocco</option>
                      <option value="MZ">Mozambique</option>
                      <option value="MM">Myanmar</option>
                      <option value="NA">Namibia</option>
                      <option value="NR">Nauru</option>
                      <option value="NP">Nepal</option>
                      <option value="NL">Netherlands</option>
                      <option value="NC">New Caledonia</option>
                      <option value="NZ">New Zealand</option>
                      <option value="NI">Nicaragua</option>
                      <option value="NE">Niger</option>
                      <option value="NG">Nigeria</option>
                      <option value="NU">Niue</option>
                      <option value="NF">Norfolk Island</option>
                      <option value="MK">North Macedonia</option>
                      <option value="MP">Northern Mariana Islands</option>
                      <option value="NO">Norway</option>
                      <option value="OM">Oman</option>
                      <option value="PK">Pakistan</option>
                      <option value="PW">Palau</option>
                      <option value="PS">Palestine, State of</option>
                      <option value="PA">Panama</option>
                      <option value="PG">Papua New Guinea</option>
                      <option value="PY">Paraguay</option>
                      <option value="PE">Peru</option>
                      <option value="PH">Philippines</option>
                      <option value="PN">Pitcairn</option>
                      <option value="PL">Poland</option>
                      <option value="PT">Portugal</option>
                      <option value="PR">Puerto Rico</option>
                      <option value="QA">Qatar</option>
                      <option value="RO">Romania</option>
                      <option value="RU">Russian Federation</option>
                      <option value="RW">Rwanda</option>
                      <option value="RE">Réunion</option>
                      <option value="BL">Saint Barthélemy</option>
                      <option value="SH">Saint Helena, Ascension and Tristan da Cunha</option>
                      <option value="KN">Saint Kitts and Nevis</option>
                      <option value="LC">Saint Lucia</option>
                      <option value="MF">Saint Martin (French part)</option>
                      <option value="PM">Saint Pierre and Miquelon</option>
                      <option value="VC">Saint Vincent and the Grenadines</option>
                      <option value="WS">Samoa</option>
                      <option value="SM">San Marino</option>
                      <option value="ST">Sao Tome and Principe</option>
                      <option value="SA">Saudi Arabia</option>
                      <option value="SN">Senegal</option>
                      <option value="RS">Serbia</option>
                      <option value="SC">Seychelles</option>
                      <option value="SL">Sierra Leone</option>
                      <option value="SG">Singapore</option>
                      <option value="SX">Sint Maarten (Dutch part)</option>
                      <option value="SK">Slovakia</option>
                      <option value="SI">Slovenia</option>
                      <option value="SB">Solomon Islands</option>
                      <option value="SO">Somalia</option>
                      <option value="ZA">South Africa</option>
                      <option value="GS">South Georgia and the South Sandwich Islands</option>
                      <option value="SS">South Sudan</option>
                      <option value="ES">Spain</option>
                      <option value="LK">Sri Lanka</option>
                      <option value="SD">Sudan</option>
                      <option value="SR">Suriname</option>
                      <option value="SJ">Svalbard and Jan Mayen</option>
                      <option value="SE">Sweden</option>
                      <option value="CH">Switzerland</option>
                      <option value="SY">Syrian Arab Republic</option>
                      <option value="TW">Taiwan</option>
                      <option value="TJ">Tajikistan</option>
                      <option value="TZ">Tanzania</option>
                      <option value="TH">Thailand</option>
                      <option value="TL">Timor-Leste</option>
                      <option value="TG">Togo</option>
                      <option value="TK">Tokelau</option>
                      <option value="TO">Tonga</option>
                      <option value="TT">Trinidad and Tobago</option>
                      <option value="TN">Tunisia</option>
                      <option value="TR">Turkey</option>
                      <option value="TM">Turkmenistan</option>
                      <option value="TC">Turks and Caicos Islands</option>
                      <option value="TV">Tuvalu</option>
                      <option value="UG">Uganda</option>
                      <option value="UA">Ukraine</option>
                      <option value="AE">United Arab Emirates</option>
                      <option value="GB">United Kingdom</option>
                      <option value="US">United States</option>
                      <option value="UY">Uruguay</option>
                      <option value="UZ">Uzbekistan</option>
                      <option value="VU">Vanuatu</option>
                      <option value="VE">Venezuela</option>
                      <option value="VN">Viet Nam</option>
                      <option value="VG">Virgin Islands (British)</option>
                      <option value="VI">Virgin Islands (U.S.)</option>
                      <option value="WF">Wallis and Futuna</option>
                      <option value="EH">Western Sahara</option>
                      <option value="YE">Yemen</option>
                      <option value="ZM">Zambia</option>
                      <option value="ZW">Zimbabwe</option>
</select>

              <p v-if="fieldErrors['details.country']" class="client-form-error">{{ fieldErrors['details.country'] }}</p>
            </div>

            <div class="client-form-group">
              <label class="client-form-label">Requested Amount <span class="client-required-mark">*</span></label>
              <input v-model="details.requested_amount" type="number" min="0" step="0.01" class="client-form-control" placeholder="Enter the requested amount" />
              <p v-if="fieldErrors['details.requested_amount']" class="client-form-error">{{ fieldErrors['details.requested_amount'] }}</p>
            </div>

            <div class="client-form-group">
              <label class="client-form-label">Finance Type <span class="client-required-mark">*</span></label>
              <select v-model="details.finance_type" class="client-form-control">
                <option value="individual">Individual</option>
                <option value="company">Company</option>
              </select>
              <p v-if="fieldErrors['details.finance_type']" class="client-form-error">{{ fieldErrors['details.finance_type'] }}</p>
            </div>

            <div class="client-form-group client-form-group--full">
              <label class="client-form-label">Supporting Notes</label>
              <textarea v-model="details.notes" class="client-form-control client-form-control--textarea" placeholder="Add any short context or supporting notes for the admin review."></textarea>
            </div>

            <div class="client-form-group client-form-group--full">
              <label class="client-form-label">Initial Attachments</label>
              <label class="client-upload-box">
                <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="client-upload-box__input" @change="onFileChange" />
                <span class="client-upload-box__title">Upload initial supporting files</span>
                <span class="client-upload-box__text">PDF, JPG, PNG, DOC, and DOCX files are supported up to 10MB each.</span>
              </label>
              <ul v-if="attachments.length" class="client-file-list">
                <li v-for="file in attachments" :key="`${file.name}-${file.size}`">
                  <strong>{{ file.name }}</strong>
                  <span>{{ (file.size / 1024 / 1024).toFixed(2) }} MB</span>
                </li>
              </ul>
              <p v-if="fieldErrors['attachments']" class="client-form-error">{{ fieldErrors['attachments'] }}</p>
            </div>
          </div>

          <div class="client-review-card">
            <div class="client-card-head">
              <div>
                <h3>Submission Summary</h3>
                <p class="client-subtext">This is the preview of the main details that will be sent to the admin for review.</p>
              </div>
            </div>

            <div class="client-summary-list">
              <div v-for="row in reviewRows" :key="row[0]" class="client-summary-item">
                <span>{{ row[0] }}</span>
                <strong>{{ row[1] }}</strong>
              </div>
            </div>
          </div>

          <div class="client-inline-actions">
            <button type="button" class="client-btn-secondary" @click="goBack">Back to Questions</button>
            <button type="button" class="client-btn-primary" :disabled="submitting" @click="submitRequest">
              {{ submitting ? 'Submitting...' : 'Submit Request' }}
            </button>
          </div>
        </template>
      </article>
    </section>
  </div>
</template>
