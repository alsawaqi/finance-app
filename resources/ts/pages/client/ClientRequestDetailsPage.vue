<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppFilePreviewModal from '@/components/AppFilePreviewModal.vue'
import { getRequestQuestions, type ClientQuestion, type FinanceRequestTypeOption } from '@/services/clientRequests'
import {
  clientContractDownloadUrl,
  getClientRequest,
  submitClientUpdateValue,
  uploadClientCommercialContract,
} from '@/services/clientPortal'
import { buildPreviewUrl } from '@/utils/filePreview'
import { countryNameFromCode } from '@/utils/countries'
import { applicantTypeLabel, intakeCountryCode, intakeRequestedAmount } from '@/utils/requestIntake'
import { getClientWorkflowStageMeta } from '@/utils/clientRequestStage'
import { formatDateTime } from '@/utils/dateTime'
import {
  formatAdditionalDocumentStatus,
  formatContractStatus,
  formatRequestStatus,
  formatUpdateBatchStatus,
} from '@/utils/requestStatus'
import ClientQuestionField from './inc/ClientQuestionField.vue'

/** Shape stored for ClientQuestionField + native inputs (v-model rejects `unknown`). */
type UpdateDraftValue = string | number | string[] | null | undefined

const route = useRoute()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const requestItem = ref<any | null>(null)
const { t, locale } = useI18n()
const filePreviewOpen = ref(false)
const filePreviewName = ref('')
const filePreviewMime = ref('')
const filePreviewUrl = ref('')
const fileDownloadUrl = ref('')

const updateDraftValues = ref<Record<number, UpdateDraftValue>>({})
const submittingUpdateItems = ref<Record<number, boolean>>({})
const financeRequestTypes = ref<FinanceRequestTypeOption[]>([])
const commercialContractFile = ref<File | null>(null)
const uploadingCommercialContract = ref(false)
const isArabic = computed(() => locale.value === 'ar')
const emptyValueLabel = computed(() => t('clientRequestDetails.states.emptyValue'))

function uiText(en: string, ar: string) {
  return isArabic.value ? ar : en
}

function answerText(answer: any) {
  if (!answer) return emptyValueLabel.value
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return emptyValueLabel.value
  return String(value)
}

function stageMeta(stage: string | null | undefined) {
  return getClientWorkflowStageMeta(stage)
}

function dateTimeText(value: unknown, fallback?: string) {
  return formatDateTime(value, locale, fallback ?? emptyValueLabel.value)
}

function requestStatusLabel(value: unknown) {
  return formatRequestStatus(value, locale, emptyValueLabel.value)
}

function contractStatusLabel(value: unknown) {
  return formatContractStatus(value, locale, t('clientRequestDetails.states.noContractYet'))
}

function updateBatchStatusLabel(value: unknown) {
  return formatUpdateBatchStatus(value, locale, emptyValueLabel.value)
}

function additionalDocumentStatusLabel(value: unknown) {
  return formatAdditionalDocumentStatus(value, locale, emptyValueLabel.value)
}

const activeUpdateBatch = computed(() => requestItem.value?.active_update_batch ?? null)
const valueUpdateItems = computed(() => (activeUpdateBatch.value?.items ?? []).filter((item: any) => item.item_type !== 'attachment'))
const fileUpdateItems = computed(() => (activeUpdateBatch.value?.items ?? []).filter((item: any) => item.item_type === 'attachment'))
const canUploadClientCommercialContract = computed(() => Boolean(requestItem.value?.can_upload_client_commercial_contract))

function localizedText(en?: string | null, ar?: string | null, fallback?: string) {
  const resolvedFallback = fallback ?? emptyValueLabel.value
  if (isArabic.value) return ar || en || resolvedFallback
  return en || ar || resolvedFallback
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
  const next: Record<number, UpdateDraftValue> = {}
  valueUpdateItems.value.forEach((item: any) => {
    next[item.id] = (updateDraftValues.value[item.id] ?? extractInitialUpdateValue(item)) as UpdateDraftValue
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
    question_text: localizedText(item.label_en, item.label_ar, item.question?.question_text || uiText('Requested update', 'تحديث مطلوب')),
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
  if (key === 'updated') return uiText('Submitted for review', 'تم الإرسال للمراجعة')
  if (key === 'approved') return uiText('Approved', 'معتمد')
  if (key === 'rejected') return uiText('Needs another update', 'يتطلب تحديثا جديدا')
  if (key === 'pending') return uiText('Waiting for your update', 'بانتظار تحديثك')
  return key || uiText('Unknown', 'غير معروف')
}

function updateStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return 'client-badge client-badge--green'
  if (key === 'updated') return 'client-badge client-badge--blue'
  if (key === 'rejected') return 'client-badge client-badge--red'
  return 'client-badge client-badge--amber'
}

function formatValuePreview(item: any, mode: 'old' | 'new') {
  const payload = mode === 'old' ? item?.old_value_json : item?.new_value_json
  if (!payload) return emptyValueLabel.value

  if (Object.prototype.hasOwnProperty.call(payload, 'value')) {
    const value = payload.value
    if (Array.isArray(value)) return value.join(', ')
    return value === null || value === undefined || value === '' ? emptyValueLabel.value : String(value)
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'answer_value_json') && payload.answer_value_json !== null && payload.answer_value_json !== undefined) {
    return Array.isArray(payload.answer_value_json)
      ? payload.answer_value_json.join(', ')
      : String(payload.answer_value_json)
  }

  if (payload.answer_text) return String(payload.answer_text)
  if (payload.file_name) return String(payload.file_name)
  return emptyValueLabel.value
}

function updateSubmitButtonLabel(item: any) {
  if (submittingUpdateItems.value[item.id]) return uiText('Submitting...', 'جارٍ الإرسال...')
  if (item.status === 'updated') return uiText('Submit revised value', 'إرسال القيمة المعدلة')
  if (item.status === 'rejected') return uiText('Resubmit update', 'إعادة إرسال التحديث')
  return uiText('Submit update', 'إرسال التحديث')
}

function financeTypePlaceholder() {
  return uiText('Select a finance request type', 'اختر نوع طلب التمويل')
}

function openContractPreview() {
  const downloadUrl = clientContractDownloadUrl(requestId.value)
  filePreviewName.value = `contract-${requestId.value}.pdf`
  filePreviewMime.value = 'application/pdf'
  fileDownloadUrl.value = downloadUrl
  filePreviewUrl.value = buildPreviewUrl(downloadUrl)
  filePreviewOpen.value = true
}

function onCommercialContractFileChange(event: Event) {
  const input = event.target as HTMLInputElement | null
  commercialContractFile.value = input?.files?.[0] ?? null
}

async function submitCommercialContractUpload() {
  if (!commercialContractFile.value || uploadingCommercialContract.value) return

  uploadingCommercialContract.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await uploadClientCommercialContract(requestId.value, {
      file: commercialContractFile.value,
    })
    await load()
    commercialContractFile.value = null
    successMessage.value = data.message || uiText('Commercial registration contract uploaded successfully.', 'تم رفع عقد توثيق الغرفة التجارية بنجاح.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Unable to upload the commercial registration contract.', 'تعذر رفع عقد توثيق الغرفة التجارية.')
  } finally {
    uploadingCommercialContract.value = false
  }
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
    successMessage.value = data.message || uiText('Your requested update was submitted successfully.', 'تم إرسال التحديث المطلوب بنجاح.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Unable to submit the requested update.', 'تعذر إرسال التحديث المطلوب.')
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
  commercialContractFile.value = null
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
    <div class="hero-card client-reveal-up">
      <div>
        <p class="eyebrow">{{ t('clientRequestDetails.hero.eyebrow') }}</p>
        <h1>{{ t('clientRequestDetails.hero.title') }}</h1>
        <p>{{ t('clientRequestDetails.hero.subtitle') }}</p>
      </div>
      <div class="action-stack">
        <RouterLink :to="{ name: 'client-requests' }" class="ghost-btn">{{ t('clientRequestDetails.hero.backToRequests') }}</RouterLink>
        <RouterLink v-if="requestItem?.can_upload_documents || fileUpdateItems.length" :to="{ name: 'client-request-documents', params: { id: requestId } }" class="ghost-btn">{{ t('clientRequestDetails.hero.openDocuments') }}</RouterLink>
        <button
          v-if="requestItem?.current_contract?.contract_pdf_path"
          type="button"
          class="ghost-btn"
          @click="openContractPreview"
        >
          {{ uiText('Preview contract PDF', 'معاينة ملف العقد') }}
        </button>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="clientContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">{{ t('clientRequestDetails.hero.downloadContractPdf') }}</a>
        <RouterLink v-if="requestItem?.can_sign" :to="{ name: 'client-request-sign', params: { id: requestId } }" class="primary-btn">{{ t('clientRequestDetails.hero.reviewAndSign') }}</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('clientRequestDetails.states.loading') }}</p>
    <p v-else-if="errorMessage && !requestItem" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>
    <p v-if="errorMessage && requestItem" class="error-state">{{ errorMessage }}</p>

    <template v-else-if="requestItem">
      <div class="client-status-chip-grid client-status-chip-grid--summary client-reveal-up">
        <div class="client-status-chip-card">
          <strong>{{ requestItem.reference_number }}</strong>
          <span>{{ t('clientRequestDetails.summary.requestReference') }}</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ requestStatusLabel(requestItem.status) }}</strong>
          <span>{{ t('clientRequestDetails.summary.status') }}</span>
        </div>
        <div class="client-status-chip-card">
          <strong>
            <span class="client-stage-badge" :class="stageMeta(requestItem.workflow_stage).className">{{ stageMeta(requestItem.workflow_stage).label }}</span>
          </strong>
          <span>{{ t('clientRequestDetails.summary.workflowStage') }}</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ contractStatusLabel(requestItem.current_contract?.status) }}</strong>
          <span>{{ t('clientRequestDetails.summary.contractState') }}</span>
        </div>
      </div>

      <div class="client-accordion-stack">
        <details v-if="activeUpdateBatch" class="client-accordion-card client-reveal-left" open>
          <summary>
            <div>
              <h2>{{ uiText('Requested updates', 'التحديثات المطلوبة') }}</h2>
              <p>{{ uiText('The team asked you to revise specific fields, answers, or files for this request.', 'طلب الفريق منك تعديل بعض الحقول أو الإجابات أو الملفات لهذا الطلب.') }}</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div class="summary-grid summary-grid--compact summary-grid--three">
              <div>
                <span>{{ uiText('Batch status', 'حالة الدفعة') }}</span>
                <strong>{{ updateBatchStatusLabel(activeUpdateBatch.status) }}</strong>
              </div>
              <div>
                <span>{{ uiText('Opened at', 'تاريخ الفتح') }}</span>
                <strong>{{ dateTimeText(activeUpdateBatch.opened_at) }}</strong>
              </div>
              <div>
                <span>{{ uiText('Requested items', 'العناصر المطلوبة') }}</span>
                <strong>{{ activeUpdateBatch.items?.length || 0 }}</strong>
              </div>
              <div>
                <span>{{ uiText('File updates', 'تحديثات الملفات') }}</span>
                <strong>{{ fileUpdateItems.length }}</strong>
              </div>
            </div>

            <div class="notes-box" v-if="localizedText(activeUpdateBatch.reason_en, activeUpdateBatch.reason_ar, '') !== ''">
              <span>{{ uiText('Reason from the team', 'ملاحظة الفريق') }}</span>
              <p>{{ localizedText(activeUpdateBatch.reason_en, activeUpdateBatch.reason_ar, emptyValueLabel) }}</p>
            </div>

            <div v-if="valueUpdateItems.length" class="client-doc-grid client-doc-grid--detail">
              <article v-for="item in valueUpdateItems" :key="item.id" class="client-doc-card client-update-card">
                <div class="client-card-head">
                  <div>
                    <h3>{{ localizedText(item.label_en, item.label_ar, uiText('Requested update', 'تحديث مطلوب')) }}</h3>
                    <p class="client-subtext">{{ localizedText(item.instruction_en, item.instruction_ar, uiText('Please review and submit the requested update.', 'يرجى مراجعة التحديث المطلوب ثم إرساله.')) }}</p>
                  </div>
                  <span :class="updateStatusClass(item.status)">{{ updateStatusLabel(item.status) }}</span>
                </div>

                <div class="summary-grid summary-grid--compact">
                  <div>
                    <span>{{ uiText('Current value', 'القيمة الحالية') }}</span>
                    <strong>{{ formatValuePreview(item, 'old') }}</strong>
                  </div>
                  <div>
                    <span>{{ uiText('Last submitted', 'آخر قيمة مرسلة') }}</span>
                    <strong>{{ formatValuePreview(item, 'new') }}</strong>
                  </div>
                </div>

                <div class="panel-card slim-card client-update-editor">
                  <template v-if="item.item_type === 'request_answer' && item.question">
                    <ClientQuestionField
                      :question="updateQuestionForItem(item)"
                      v-model="updateDraftValues[item.id]"
                    />
                  </template>

                  <template v-else>
                    <div class="client-form-group">
                      <label class="client-form-label">{{ localizedText(item.label_en, item.label_ar, uiText('Requested update', 'تحديث مطلوب')) }}</label>

                      <select
                        v-if="item.field_key === 'finance_request_type_id'"
                        v-model="updateDraftValues[item.id]"
                        class="client-form-control"
                      >
                        <option value="">{{ financeTypePlaceholder() }}</option>
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

                <div class="client-inline-actions client-inline-actions--stackable client-update-actions">
                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="submittingUpdateItems[item.id] || item.status === 'approved' || !requestItem.can_submit_client_updates"
                    @click="submitValueItem(item)"
                  >
                    {{ updateSubmitButtonLabel(item) }}
                  </button>
                  <RouterLink v-if="fileUpdateItems.length" :to="{ name: 'client-request-documents', params: { id: requestId } }" class="ghost-btn">
                    {{ uiText('Open requested file updates', 'فتح تحديثات الملفات المطلوبة') }}
                  </RouterLink>
                </div>
              </article>
            </div>

            <div v-else class="client-empty-state client-empty-state--inner">
              <h3>{{ uiText('No value fields are waiting here.', 'لا توجد حقول قيم بانتظار التحديث حالياً.') }}</h3>
              <p class="client-muted">{{ uiText('Any requested file replacements can be uploaded from the documents page.', 'يمكن رفع أي تحديثات ملفات مطلوبة من صفحة المستندات.') }}</p>
            </div>
          </div>
        </details>

        <details class="client-accordion-card client-reveal-up" open>
          <summary>
            <div>
              <h2>{{ t('clientRequestDetails.sections.submissionSummaryTitle') }}</h2>
              <p>{{ t('clientRequestDetails.sections.submissionSummarySubtitle') }}</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div class="summary-grid summary-grid--compact summary-grid--three">
              <div><span>{{ t('clientRequestDetails.summary.approvalReference') }}</span><strong>{{ requestItem.approval_reference_number || t('clientRequestDetails.states.pendingApproval') }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.applicantType') }}</span><strong>{{ applicantTypeLabel(requestItem.applicant_type, locale, t('clientRequestDetails.states.individual')) }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.companyName') }}</span><strong>{{ requestItem.company_name || t('clientRequestDetails.states.emptyValue') }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.country') }}</span><strong>{{ countryNameFromCode(requestItem.country_code || intakeCountryCode(requestItem.intake_details_json), locale) }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.requestedAmount') }}</span><strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.submitted') }}</span><strong>{{ dateTimeText(requestItem.submitted_at) }}</strong></div>
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

            <article v-if="requestItem.comments?.length" class="panel-card slim-card">
              <div class="panel-head">
                <h3>{{ uiText('Team comments', 'تعليقات الفريق') }}</h3>
              </div>
              <div class="timeline-list compact-list">
                <div v-for="comment in requestItem.comments" :key="comment.id" class="timeline-item">
                  <strong>{{ comment.user?.name || uiText('Team', 'الفريق') }}</strong>
                  <p>{{ comment.comment_text }}</p>
                  <span>{{ dateTimeText(comment.created_at) }}</span>
                </div>
              </div>
            </article>

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

        <details class="client-accordion-card client-reveal-up">
          <summary>
            <div>
              <h2>{{ t('clientRequestDetails.sections.contractUploadStatusTitle') }}</h2>
              <p>{{ t('clientRequestDetails.sections.contractUploadStatusSubtitle') }}</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div class="summary-grid summary-grid--compact summary-grid--three">
              <div><span>{{ t('clientRequestDetails.summary.contractVersion') }}</span><strong>{{ requestItem.current_contract?.version_no || t('clientRequestDetails.states.emptyValue') }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.contractStatus') }}</span><strong>{{ contractStatusLabel(requestItem.current_contract?.status) }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.adminSigned') }}</span><strong>{{ dateTimeText(requestItem.current_contract?.admin_signed_at, t('clientRequestDetails.states.pending')) }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.clientSigned') }}</span><strong>{{ dateTimeText(requestItem.current_contract?.client_signed_at, t('clientRequestDetails.states.pending')) }}</strong></div>
            </div>

            <article v-if="requestItem.current_contract?.requires_commercial_registration" class="panel-card slim-card client-commercial-card">
              <div class="panel-head">
                <h3>{{ uiText('Commercial registration authentication', 'توثيق الغرفة التجارية') }}</h3>
              </div>
              <div class="summary-grid summary-grid--compact summary-grid--three">
                <div>
                  <span>{{ uiText('Client upload', 'رفع العميل') }}</span>
                  <strong>{{ dateTimeText(requestItem.current_contract?.client_commercial_uploaded_at, uiText('Pending', 'بانتظار الرفع')) }}</strong>
                </div>
                <div>
                  <span>{{ uiText('Admin upload', 'رفع الإدارة') }}</span>
                  <strong>{{ dateTimeText(requestItem.current_contract?.admin_commercial_uploaded_at, uiText('Pending', 'بانتظار الرفع')) }}</strong>
                </div>
                <div>
                  <span>{{ uiText('Current step', 'الخطوة الحالية') }}</span>
                  <strong>{{ stageMeta(requestItem.workflow_stage).label }}</strong>
                </div>
              </div>

              <div v-if="canUploadClientCommercialContract" class="client-inline-actions client-inline-actions--stackable client-commercial-upload">
                <input class="client-form-control" type="file" @change="onCommercialContractFileChange" />
                <button type="button" class="client-btn-primary" :disabled="!commercialContractFile || uploadingCommercialContract" @click="submitCommercialContractUpload">
                  {{ uploadingCommercialContract ? uiText('Uploading...', 'جاري الرفع...') : uiText('Upload authenticated contract', 'رفع العقد الموثق') }}
                </button>
              </div>

              <p v-else class="client-muted">
                {{
                  requestItem.current_contract?.admin_commercial_uploaded_at
                    ? uiText('Commercial registration uploads are complete.', 'تم استكمال رفع توثيق الغرفة التجارية.')
                    : uiText('Waiting for the next commercial registration action.', 'بانتظار الإجراء التالي الخاص بتوثيق الغرفة التجارية.')
                }}
              </p>
            </article>

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
                    <p>{{ additionalDocumentStatusLabel(item.status) }}</p>
                  </div>
                </div>
                <p v-else class="empty-state">{{ t('clientRequestDetails.states.noAdditionalDocuments') }}</p>
              </article>
            </div>
          </div>
        </details>
      </div>
    </template>

    <AppFilePreviewModal
      :model-value="filePreviewOpen"
      @update:model-value="(value) => { filePreviewOpen = value }"
      title="Document preview"
      :file-name="filePreviewName"
      :mime-type="filePreviewMime"
      :preview-url="filePreviewUrl"
      :download-url="fileDownloadUrl"
    />
  </section>
</template>
