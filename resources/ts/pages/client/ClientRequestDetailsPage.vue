<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { getRequestQuestions, type ClientQuestion, type FinanceRequestTypeOption } from '@/services/clientRequests'
import {
  clientContractDownloadUrl,
  getClientRequest,
  submitClientUpdateValue,
} from '@/services/clientPortal'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeRequestedAmount } from '@/utils/requestIntake'
import { getClientWorkflowStageMeta } from '@/utils/clientRequestStage'
import ClientQuestionField from './inc/ClientQuestionField.vue'

const route = useRoute()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const requestItem = ref<any | null>(null)
const { t, locale } = useI18n()

const updateDraftValues = ref<Record<number, unknown>>({})
const submittingUpdateItems = ref<Record<number, boolean>>({})
const financeRequestTypes = ref<FinanceRequestTypeOption[]>([])

function answerText(answer: any) {
  if (!answer) return '—'
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

function stageMeta(stage: string | null | undefined) {
  return getClientWorkflowStageMeta(stage)
}

const activeUpdateBatch = computed(() => requestItem.value?.active_update_batch ?? null)
const valueUpdateItems = computed(() => (activeUpdateBatch.value?.items ?? []).filter((item: any) => item.item_type !== 'attachment'))
const fileUpdateItems = computed(() => (activeUpdateBatch.value?.items ?? []).filter((item: any) => item.item_type === 'attachment'))

function localizedText(en?: string | null, ar?: string | null, fallback = '—') {
  if (locale.value === 'ar') return ar || en || fallback
  return en || ar || fallback
}

function isTextareaField(fieldKey: string | null | undefined) {
  return ['address', 'notes'].includes(String(fieldKey || ''))
}

function inputTypeForField(fieldKey: string | null | undefined) {
  const key = String(fieldKey || '')
  if (key === 'requested_amount') return 'number'
  if (key === 'email') return 'email'
  return 'text'
}

function extractInitialUpdateValue(item: any) {
  if (item?.new_value_json && Object.prototype.hasOwnProperty.call(item.new_value_json, 'value')) {
    return item.new_value_json.value
  }

  if (item?.item_type === 'request_answer') {
    const payload = item?.old_value_json || {}
    if (payload.answer_value_json !== null && payload.answer_value_json !== undefined) {
      return payload.answer_value_json
    }
    if (payload.answer_text !== null && payload.answer_text !== undefined) {
      return payload.answer_text
    }
    return item?.question?.question_type === 'checkbox' ? [] : ''
  }

  return item?.old_value_json?.value ?? ''
}

function seedUpdateDraftValues() {
  const next: Record<number, unknown> = {}
  valueUpdateItems.value.forEach((item: any) => {
    next[item.id] = updateDraftValues.value[item.id] ?? extractInitialUpdateValue(item)
  })
  updateDraftValues.value = next
}

async function loadFinanceRequestTypesIfNeeded() {
  const needsFinanceTypeList = valueUpdateItems.value.some((item: any) => item.field_key === 'finance_request_type_id')
  if (!needsFinanceTypeList || financeRequestTypes.value.length) return

  try {
    const response = await getRequestQuestions()
    financeRequestTypes.value = response.data.finance_request_types ?? []
  } catch {
    // keep the rest of the page usable even if the helper list fails
  }
}

function updateQuestionForItem(item: any): ClientQuestion {
  return {
    id: item.question?.id ?? item.id,
    code: item.question?.code ?? null,
    question_text: localizedText(item.label_en, item.label_ar, item.question?.question_text || 'Requested update'),
    question_type: item.question?.question_type || 'text',
    options_json: item.question?.options_json || [],
    placeholder: item.question?.placeholder || '',
    help_text: item.question?.help_text || localizedText(item.instruction_en, item.instruction_ar, ''),
    is_required: Boolean(item.is_required || item.question?.is_required),
    sort_order: 0,
  }
}

function updateStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'updated') return 'Submitted for review'
  if (key === 'approved') return 'Approved'
  if (key === 'rejected') return 'Needs another update'
  if (key === 'pending') return 'Waiting for your update'
  return key || 'Unknown'
}

function updateStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return 'client-badge client-badge--green'
  if (key === 'updated') return 'client-badge client-badge--blue'
  return 'client-badge client-badge--amber'
}

function formatValuePreview(item: any, mode: 'old' | 'new') {
  const payload = mode === 'old' ? item?.old_value_json : item?.new_value_json
  if (!payload) return '—'

  if (Object.prototype.hasOwnProperty.call(payload, 'value')) {
    const value = payload.value
    if (Array.isArray(value)) return value.join(', ')
    return value === null || value === undefined || value === '' ? '—' : String(value)
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'answer_value_json') && payload.answer_value_json !== null && payload.answer_value_json !== undefined) {
    return Array.isArray(payload.answer_value_json)
      ? payload.answer_value_json.join(', ')
      : String(payload.answer_value_json)
  }

  if (payload.answer_text) return String(payload.answer_text)
  if (payload.file_name) return String(payload.file_name)
  return '—'
}

function applyRequestFromResponse(data: any) {
  if (data?.request) {
    requestItem.value = data.request
    seedUpdateDraftValues()
    loadFinanceRequestTypesIfNeeded()
  }
}

async function submitValueItem(item: any) {
  submittingUpdateItems.value = {
    ...submittingUpdateItems.value,
    [item.id]: true,
  }
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await submitClientUpdateValue(requestId.value, item.id, {
      value: updateDraftValues.value[item.id],
    })
    applyRequestFromResponse(data)
    successMessage.value = data.message || 'Your requested update was submitted successfully.'
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Unable to submit the requested update.'
  } finally {
    submittingUpdateItems.value = {
      ...submittingUpdateItems.value,
      [item.id]: false,
    }
  }
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    const data = await getClientRequest(requestId.value)
    requestItem.value = data.request ?? null
    seedUpdateDraftValues()
    await loadFinanceRequestTypesIfNeeded()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('clientRequestDetails.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="client-shell client-request-detail-shell">
    <div class="hero-card">
      <div>
        <p class="eyebrow">{{ t('clientRequestDetails.hero.eyebrow') }}</p>
        <h1>{{ t('clientRequestDetails.hero.title') }}</h1>
        <p>{{ t('clientRequestDetails.hero.subtitle') }}</p>
      </div>
      <div class="action-stack">
        <RouterLink :to="{ name: 'client-requests' }" class="ghost-btn">{{ t('clientRequestDetails.hero.backToRequests') }}</RouterLink>
        <RouterLink v-if="requestItem?.can_upload_documents || fileUpdateItems.length" :to="{ name: 'client-request-documents', params: { id: requestId } }" class="ghost-btn">{{ t('clientRequestDetails.hero.openDocuments') }}</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="clientContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">{{ t('clientRequestDetails.hero.downloadContractPdf') }}</a>
        <RouterLink v-if="requestItem?.can_sign" :to="{ name: 'client-request-sign', params: { id: requestId } }" class="primary-btn">{{ t('clientRequestDetails.hero.reviewAndSign') }}</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('clientRequestDetails.states.loading') }}</p>
    <p v-else-if="errorMessage && !requestItem" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>
    <p v-if="errorMessage && requestItem" class="error-state">{{ errorMessage }}</p>

    <template v-else-if="requestItem">
      <div class="client-status-chip-grid client-status-chip-grid--summary">
        <div class="client-status-chip-card">
          <strong>{{ requestItem.reference_number }}</strong>
          <span>{{ t('clientRequestDetails.summary.requestReference') }}</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ requestItem.status }}</strong>
          <span>{{ t('clientRequestDetails.summary.status') }}</span>
        </div>
        <div class="client-status-chip-card">
          <strong>
            <span class="client-stage-badge" :class="stageMeta(requestItem.workflow_stage).className">{{ stageMeta(requestItem.workflow_stage).label }}</span>
          </strong>
          <span>{{ t('clientRequestDetails.summary.workflowStage') }}</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ requestItem.current_contract?.status || t('clientRequestDetails.states.noContractYet') }}</strong>
          <span>{{ t('clientRequestDetails.summary.contractState') }}</span>
        </div>
      </div>

      <div class="client-accordion-stack">
        <details v-if="activeUpdateBatch" class="client-accordion-card" open>
          <summary>
            <div>
              <h2>Requested updates</h2>
              <p>The team asked you to revise specific fields, answers, or files for this request.</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div class="summary-grid summary-grid--compact">
              <div>
                <span>Batch status</span>
                <strong>{{ activeUpdateBatch.status }}</strong>
              </div>
              <div>
                <span>Opened at</span>
                <strong>{{ activeUpdateBatch.opened_at ? new Date(activeUpdateBatch.opened_at).toLocaleString() : '—' }}</strong>
              </div>
              <div>
                <span>Requested items</span>
                <strong>{{ activeUpdateBatch.items?.length || 0 }}</strong>
              </div>
              <div>
                <span>File updates</span>
                <strong>{{ fileUpdateItems.length }}</strong>
              </div>
            </div>

            <div class="notes-box" v-if="localizedText(activeUpdateBatch.reason_en, activeUpdateBatch.reason_ar, '') !== ''">
              <span>Reason from the team</span>
              <p>{{ localizedText(activeUpdateBatch.reason_en, activeUpdateBatch.reason_ar, '—') }}</p>
            </div>

            <div v-if="valueUpdateItems.length" class="client-doc-grid">
              <article v-for="item in valueUpdateItems" :key="item.id" class="client-doc-card">
                <div class="client-card-head">
                  <div>
                    <h3>{{ localizedText(item.label_en, item.label_ar, 'Requested update') }}</h3>
                    <p class="client-subtext">{{ localizedText(item.instruction_en, item.instruction_ar, 'Please review and submit the requested update.') }}</p>
                  </div>
                  <span :class="updateStatusClass(item.status)">{{ updateStatusLabel(item.status) }}</span>
                </div>

                <div class="summary-grid summary-grid--compact">
                  <div>
                    <span>Current value</span>
                    <strong>{{ formatValuePreview(item, 'old') }}</strong>
                  </div>
                  <div>
                    <span>Last submitted</span>
                    <strong>{{ formatValuePreview(item, 'new') }}</strong>
                  </div>
                </div>

                <div class="panel-card slim-card" style="margin-top: 1rem;">
                  <template v-if="item.item_type === 'request_answer' && item.question">
                    <ClientQuestionField
                      :question="updateQuestionForItem(item)"
                      v-model="updateDraftValues[item.id]"
                    />
                  </template>

                  <template v-else>
                    <div class="client-form-group">
                      <label class="client-form-label">{{ localizedText(item.label_en, item.label_ar, 'Requested update') }}</label>

                      <select
                        v-if="item.field_key === 'finance_request_type_id'"
                        v-model="updateDraftValues[item.id]"
                        class="client-form-control"
                      >
                        <option value="">Select a finance request type</option>
                        <option v-for="type in financeRequestTypes" :key="type.id" :value="type.id">
                          {{ locale === 'ar' ? (type.name_ar || type.name_en) : (type.name_en || type.name_ar) }}
                        </option>
                      </select>

                      <textarea
                        v-else-if="isTextareaField(item.field_key)"
                        v-model="updateDraftValues[item.id]"
                        rows="4"
                        class="client-form-control client-form-control--textarea"
                      />

                      <input
                        v-else
                        v-model="updateDraftValues[item.id]"
                        :type="inputTypeForField(item.field_key)"
                        class="client-form-control"
                      />
                    </div>
                  </template>
                </div>

                <div class="client-inline-actions client-inline-actions--stackable" style="margin-top: 1rem;">
                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="submittingUpdateItems[item.id] || item.status === 'approved' || !requestItem.can_submit_client_updates"
                    @click="submitValueItem(item)"
                  >
                    {{
                      submittingUpdateItems[item.id]
                        ? 'Submitting...'
                        : item.status === 'updated'
                          ? 'Submit revised value'
                          : item.status === 'rejected'
                            ? 'Resubmit update'
                            : 'Submit update'
                    }}
                  </button>
                  <RouterLink v-if="fileUpdateItems.length" :to="{ name: 'client-request-documents', params: { id: requestId } }" class="ghost-btn">
                    Open requested file updates
                  </RouterLink>
                </div>
              </article>
            </div>

            <div v-else class="client-empty-state client-empty-state--inner">
              <h3>No value fields are waiting here.</h3>
              <p class="client-muted">Any requested file replacements can be uploaded from the documents page.</p>
            </div>
          </div>
        </details>

        <details class="client-accordion-card" open>
          <summary>
            <div>
              <h2>{{ t('clientRequestDetails.sections.submissionSummaryTitle') }}</h2>
              <p>{{ t('clientRequestDetails.sections.submissionSummarySubtitle') }}</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div class="summary-grid summary-grid--compact">
              <div><span>{{ t('clientRequestDetails.summary.approvalReference') }}</span><strong>{{ requestItem.approval_reference_number || t('clientRequestDetails.states.pendingApproval') }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.applicantType') }}</span><strong>{{ requestItem.applicant_type || t('clientRequestDetails.states.individual') }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.companyName') }}</span><strong>{{ requestItem.company_name || t('clientRequestDetails.states.emptyValue') }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.country') }}</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json), locale) }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.requestedAmount') }}</span><strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.submitted') }}</span><strong>{{ requestItem.submitted_at ? new Date(requestItem.submitted_at).toLocaleString() : t('clientRequestDetails.states.emptyValue') }}</strong></div>
            </div>

            <div class="client-two-col-grid">
              <article class="panel-card slim-card">
                <div class="panel-head"><h3>{{ t('clientRequestDetails.sections.yourAnswers') }}</h3></div>
                <div class="qa-list compact-list" v-if="requestItem.answers?.length">
                  <div class="qa-item" v-for="answer in requestItem.answers" :key="answer.id">
                    <strong>{{ answer.question_text || answer.question?.question_text || t('clientRequestDetails.states.questionFallback') }}</strong>
                    <p>{{ answerText(answer) }}</p>
                  </div>
                </div>
                <p v-else class="empty-state">{{ t('clientRequestDetails.states.noAnswers') }}</p>
              </article>

              <article class="panel-card slim-card">
                <div class="panel-head"><h3>{{ t('clientRequestDetails.sections.submittedFiles') }}</h3></div>
                <div v-if="requestItem.attachments?.length" class="file-list compact-list">
                  <div v-for="file in requestItem.attachments" :key="file.id" class="file-item">
                    <strong>{{ file.file_name }}</strong>
                    <span>{{ file.category }}</span>
                  </div>
                </div>
                <p v-else class="empty-state">{{ t('clientRequestDetails.states.noUploadedFiles') }}</p>
              </article>
            </div>

            <article v-if="requestItem.shareholders?.length" class="panel-card slim-card">
              <div class="panel-head"><h3>{{ t('clientRequestDetails.sections.shareholders') }}</h3></div>
              <div class="qa-list compact-list">
                <div class="qa-item" v-for="shareholder in requestItem.shareholders" :key="shareholder.id">
                  <strong>{{ shareholder.shareholder_name }}</strong>
                  <p>{{ shareholder.id_file_name }}</p>
                </div>
              </div>
            </article>
          </div>
        </details>

        <details class="client-accordion-card">
          <summary>
            <div>
              <h2>{{ t('clientRequestDetails.sections.contractUploadStatusTitle') }}</h2>
              <p>{{ t('clientRequestDetails.sections.contractUploadStatusSubtitle') }}</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div class="summary-grid summary-grid--compact">
              <div><span>{{ t('clientRequestDetails.summary.contractVersion') }}</span><strong>{{ requestItem.current_contract?.version_no || t('clientRequestDetails.states.emptyValue') }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.contractStatus') }}</span><strong>{{ requestItem.current_contract?.status || t('clientRequestDetails.states.emptyValue') }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.adminSigned') }}</span><strong>{{ requestItem.current_contract?.admin_signed_at ? new Date(requestItem.current_contract.admin_signed_at).toLocaleString() : t('clientRequestDetails.states.pending') }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.clientSigned') }}</span><strong>{{ requestItem.current_contract?.client_signed_at ? new Date(requestItem.current_contract.client_signed_at).toLocaleString() : t('clientRequestDetails.states.pending') }}</strong></div>
            </div>

            <div class="client-two-col-grid">
              <article class="panel-card slim-card">
                <div class="panel-head"><h3>{{ t('clientRequestDetails.sections.requiredDocuments') }}</h3></div>
                <div v-if="requestItem.required_documents?.length" class="qa-list compact-list">
                  <div v-for="item in requestItem.required_documents" :key="item.document_upload_step_id" class="qa-item">
                    <strong>{{ item.name }}</strong>
                    <p>{{ item.is_uploaded ? t('clientRequestDetails.states.uploaded') : t('clientRequestDetails.states.pending') }}</p>
                  </div>
                </div>
                <p v-else class="empty-state">{{ t('clientRequestDetails.states.noRequiredDocuments') }}</p>
              </article>

              <article class="panel-card slim-card">
                <div class="panel-head"><h3>{{ t('clientRequestDetails.sections.additionalRequestedDocuments') }}</h3></div>
                <div v-if="requestItem.additional_document_requests?.length" class="qa-list compact-list">
                  <div v-for="item in requestItem.additional_document_requests" :key="item.id" class="qa-item">
                    <strong>{{ item.title }}</strong>
                    <p>{{ item.status }}</p>
                  </div>
                </div>
                <p v-else class="empty-state">{{ t('clientRequestDetails.states.noAdditionalRequestedDocuments') }}</p>
              </article>
            </div>
          </div>
        </details>
      </div>
    </template>
  </section>
</template>
