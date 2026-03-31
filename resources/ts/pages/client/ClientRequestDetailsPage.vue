<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { clientContractDownloadUrl, getClientRequest } from '@/services/clientPortal'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeRequestedAmount } from '@/utils/requestIntake'
import { getClientWorkflowStageMeta } from '@/utils/clientRequestStage'

const route = useRoute()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const errorMessage = ref('')
const requestItem = ref<any | null>(null)
const { t } = useI18n()

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

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await getClientRequest(requestId.value)
    requestItem.value = data.request ?? null
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
        <RouterLink v-if="requestItem?.can_upload_documents" :to="{ name: 'client-request-documents', params: { id: requestId } }" class="ghost-btn">{{ t('clientRequestDetails.hero.openDocuments') }}</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="clientContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">{{ t('clientRequestDetails.hero.downloadContractPdf') }}</a>
        <RouterLink v-if="requestItem?.can_sign" :to="{ name: 'client-request-sign', params: { id: requestId } }" class="primary-btn">{{ t('clientRequestDetails.hero.reviewAndSign') }}</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('clientRequestDetails.states.loading') }}</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

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
              <div><span>{{ t('clientRequestDetails.summary.country') }}</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json)) }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.requestedAmount') }}</span><strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong></div>
              <div><span>{{ t('clientRequestDetails.summary.submitted') }}</span><strong>{{ requestItem.submitted_at ? new Date(requestItem.submitted_at).toLocaleString() : t('clientRequestDetails.states.emptyValue') }}</strong></div>
            </div>

            <div class="client-two-col-grid">
              <article class="panel-card slim-card">
                <div class="panel-head"><h3>{{ t('clientRequestDetails.sections.yourAnswers') }}</h3></div>
                <div class="qa-list compact-list" v-if="requestItem.answers?.length">
                  <div class="qa-item" v-for="answer in requestItem.answers" :key="answer.id">
                    <strong>{{ answer.question_text || answer.question?.question_text || 'Question' }}</strong>
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
                    <p>{{ item.reason || t('clientRequestDetails.states.noReasonAdded') }}</p>
                    <span>{{ item.status }}</span>
                  </div>
                </div>
                <p v-else class="empty-state">{{ t('clientRequestDetails.states.noAdditionalDocuments') }}</p>
              </article>
            </div>
          </div>
        </details>
      </div>
    </template>
  </section>
</template>
