<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
 import {
  adminAdditionalDocumentDownloadUrl,
  adminContractDownloadUrl,
  adminRequiredDocumentDownloadUrl,
} from '@/services/adminRequests'
import {
  addStaffComment,
  getStaffAgents,
  getStaffRequest,
  requestAdditionalDocument,
  requestRequiredDocumentChange,
  staffAttachmentDownloadUrl,
  staffShareholderIdDownloadUrl,
  type AgentOption,
  type BankOption,
  type RequiredDocumentChecklistItem,
} from '@/services/staffWorkspace'
import { countryNameFromCode } from '@/utils/countries'
import {
  intakeAddress,
  intakeCountryCode,
  intakeEmail,
  intakeFinanceType,
  intakeFullName,
  intakeNationalAddressNumber,
  intakeNotes,
  intakePhoneDisplay,
  intakeRequestedAmount,
  intakeUnifiedNumber,
} from '@/utils/requestIntake'

const route = useRoute()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const savingComment = ref(false)
const savingAdditionalDocument = ref(false)
const savingRequiredDocumentChange = ref<Record<number, boolean>>({})
const requiredDocumentChangeReason = ref<Record<number, string>>({})
const errorMessage = ref('')
const successMessage = ref('')

const requestItem = ref<any | null>(null)
const requiredDocuments = ref<RequiredDocumentChecklistItem[]>([])
const agents = ref<AgentOption[]>([])
const banks = ref<BankOption[]>([])

const commentText = ref('')
const commentVisibility = ref<'internal' | 'admin_only'>('internal')
const additionalDocumentTitle = ref('')
const additionalDocumentReason = ref('')

const selectedBankId = ref<number | null>(null)
const selectedAgentIds = ref<number[]>([])
const emailSubject = ref('')
const emailBody = ref('')
const mockFiles = ref<string[]>([])
const { t, locale } = useI18n()

const uploadedRequiredCount = computed(() => requiredDocuments.value.filter((item) => item.is_uploaded).length)
const pendingRequiredCount = computed(() => requiredDocuments.value.filter((item) => !item.is_uploaded).length)

function answerText(answer: any) {
  if (!answer) return t('staffRequestDetails.states.emptyValue')
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return t('staffRequestDetails.states.emptyValue')
  return String(value)
}

async function loadAgents() {
  const response = await getStaffAgents({ bank_id: selectedBankId.value })
  banks.value = response.banks ?? []
  agents.value = response.agents ?? []
}

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [requestResponse] = await Promise.all([getStaffRequest(requestId.value), loadAgents()])
    requestItem.value = requestResponse.request ?? null
    requiredDocuments.value = requestResponse.required_documents ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequestDetails.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

async function submitComment() {
  if (!requestItem.value || !commentText.value.trim()) return

  savingComment.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await addStaffComment(requestItem.value.id, {
      comment_text: commentText.value,
      visibility: commentVisibility.value,
    })
    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    commentText.value = ''
    successMessage.value = data.message || t('staffRequestDetails.success.commentAdded')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequestDetails.errors.commentFailed')
  } finally {
    savingComment.value = false
  }
}

async function submitAdditionalDocumentRequest() {
  if (!requestItem.value || !additionalDocumentTitle.value.trim()) return

  savingAdditionalDocument.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await requestAdditionalDocument(requestItem.value.id, {
      title: additionalDocumentTitle.value,
      reason: additionalDocumentReason.value,
    })
    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    additionalDocumentTitle.value = ''
    additionalDocumentReason.value = ''
    successMessage.value = data.message || t('staffRequestDetails.success.additionalRequestCreated')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequestDetails.errors.additionalRequestFailed')
  } finally {
    savingAdditionalDocument.value = false
  }
}


async function submitRequiredDocumentChange(stepId: number) {
  if (!requestItem.value) return

  const reason = (requiredDocumentChangeReason.value[stepId] || '').trim()
  if (!reason) return

  savingRequiredDocumentChange.value[stepId] = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await requestRequiredDocumentChange(requestItem.value.id, stepId, { reason })
    requestItem.value = data.request
    requiredDocuments.value = data.required_documents ?? requiredDocuments.value
    requiredDocumentChangeReason.value[stepId] = ''
    successMessage.value = data.message || t('staffRequestDetails.success.changeRequestSent')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequestDetails.errors.changeRequestFailed')
  } finally {
    savingRequiredDocumentChange.value[stepId] = false
  }
}


function requiredDocumentDownloadUrl(uploadId: number | string) {
  return adminRequiredDocumentDownloadUrl(requestId.value, uploadId)
}

function additionalDocumentDownloadUrl(additionalDocumentId: number | string) {
  return adminAdditionalDocumentDownloadUrl(requestId.value, additionalDocumentId)
}

function attachmentDownloadUrl(attachmentId: number | string) {
  return staffAttachmentDownloadUrl(requestId.value, attachmentId)
}

function shareholderIdDownloadUrl(shareholderId: number | string) {
  return staffShareholderIdDownloadUrl(requestId.value, shareholderId)
}

 
 

function handleMockAttachments(event: Event) {
  const input = event.target as HTMLInputElement
  mockFiles.value = Array.from(input.files || []).map((file) => file.name)
}

watch(selectedBankId, async () => {
  await loadAgents()
  selectedAgentIds.value = selectedAgentIds.value.filter((id) => agents.value.some((agent) => agent.id === id))
})

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('staffRequestDetails.hero.eyebrow') }}</p>
        <h1>{{ t('staffRequestDetails.hero.title') }}</h1>
        <p class="subtext">{{ t('staffRequestDetails.hero.subtitle') }}</p>
      </div>
      <div class="actions-row">
        <RouterLink :to="{ name: 'staff-requests' }" class="ghost-btn">{{ t('staffRequestDetails.hero.backToAssignedRequests') }}</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="primary-btn">{{ t('staffRequestDetails.hero.downloadContractPdf') }}</a>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('staffRequestDetails.states.loading') }}</p>
    <p v-else-if="errorMessage && !requestItem" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>

    <template v-else-if="requestItem">
      <div class="admin-workspace-summary-grid">
        <article class="admin-workspace-stat">
          <span>{{ t('staffRequestDetails.summary.client') }}</span>
          <strong>{{ intakeFullName(requestItem.intake_details_json, requestItem.client?.name || t('staffRequestDetails.states.clientFallback')) }}</strong>
          <small>{{ requestItem.reference_number }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>{{ t('staffRequestDetails.summary.workflow') }}</span>
          <strong>{{ requestItem.workflow_stage }}</strong>
          <small>{{ intakeFinanceType(requestItem.intake_details_json) }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>{{ t('staffRequestDetails.summary.requiredDocsUploaded') }}</span>
          <strong>{{ uploadedRequiredCount }}/{{ requiredDocuments.length }}</strong>
          <small>{{ t('staffRequestDetails.summary.pendingCount', { count: pendingRequiredCount }) }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>{{ t('staffRequestDetails.summary.additionalRequests') }}</span>
          <strong>{{ requestItem.additional_documents?.length || 0 }}</strong>
          <small>{{ t('staffRequestDetails.summary.customFollowUpItems') }}</small>
        </article>
      </div>

      <div class="admin-workspace-layout">
        <div class="admin-workspace-main">
          <details class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.requiredChecklistTitle') }}</h2>
                <p>{{ t('staffRequestDetails.sections.requiredChecklistSubtitle') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div v-if="requiredDocuments.length" class="checklist-grid">
                <article v-for="item in requiredDocuments" :key="item.document_upload_step_id" class="checklist-card" :class="{ 'is-complete': item.is_uploaded && !item.is_change_requested }">
                  <div class="checklist-card__head">
                    <strong>{{ item.name }}</strong>
                    <span class="status-badge">
                      {{ item.is_change_requested ? t('staffRequestDetails.states.changeRequested') : item.is_uploaded ? t('staffRequestDetails.states.uploaded') : t('staffRequestDetails.states.pending') }}
                    </span>
                  </div>
                  <p>{{ item.is_uploaded || item.is_change_requested ? t('staffRequestDetails.states.latestFileLabel', { file: item.upload?.file_name || t('staffRequestDetails.states.uploadedFile') }) : t('staffRequestDetails.states.waitingForClientUpload') }}</p>
                  <div v-if="item.upload?.id" class="approve-actions">
                    <a :href="requiredDocumentDownloadUrl(item.upload.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.downloadLatestFile') }}</a>
                  </div>
                  <p v-if="item.rejection_reason" class="form-help form-help--error">{{ t('staffRequestDetails.states.reasonLabel') }}: {{ item.rejection_reason }}</p>

                  <div v-if="item.is_uploaded && !item.is_change_requested" class="field-stack">
                    <textarea
                      v-model="requiredDocumentChangeReason[item.document_upload_step_id]"
                      rows="3"
                      class="admin-textarea"
                      :placeholder="t('staffRequestDetails.placeholders.changeReason')"
                    ></textarea>
                    <div class="approve-actions">
                      <button
                        class="ghost-btn"
                        type="button"
                        :disabled="savingRequiredDocumentChange[item.document_upload_step_id] || !(requiredDocumentChangeReason[item.document_upload_step_id] || '').trim()"
                        @click="submitRequiredDocumentChange(item.document_upload_step_id)"
                      >
                        {{ savingRequiredDocumentChange[item.document_upload_step_id] ? t('staffRequestDetails.actions.sending') : t('staffRequestDetails.actions.requestChanges') }}
                      </button>
                    </div>
                  </div>
                </article>
              </div>
              <p v-else class="empty-state">{{ t('staffRequestDetails.states.noRequiredDocuments') }}</p>
            </div>
          </details>


          <details class="admin-accordion-card">
  <summary>
    <div>
      <h2>{{ t('staffRequestDetails.sections.initialUploadedFilesTitle') }}</h2>
      <p>{{ t('staffRequestDetails.sections.initialUploadedFilesSubtitle') }}</p>
    </div>
  </summary>
  <div class="admin-accordion-card__body">
    <div v-if="requestItem.attachments?.length" class="file-list">
      <div v-for="file in requestItem.attachments" :key="file.id" class="file-item">
        <div>
          <strong>{{ file.file_name }}</strong>
          <span>{{ file.category }}</span>
        </div>
        <a :href="attachmentDownloadUrl(file.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.download') }}</a>
      </div>
    </div>
    <p v-else class="empty-state">{{ t('staffRequestDetails.states.noInitialFilesUploaded') }}</p>
  </div>
</details>

<details class="admin-accordion-card">
  <summary>
    <div>
      <h2>{{ t('staffRequestDetails.sections.shareholdersTitle') }}</h2>
      <p>{{ t('staffRequestDetails.sections.shareholdersSubtitle') }}</p>
    </div>
  </summary>
  <div class="admin-accordion-card__body">
    <div v-if="requestItem.shareholders?.length" class="file-list">
      <div v-for="shareholder in requestItem.shareholders" :key="shareholder.id" class="file-item">
        <div>
          <strong>{{ shareholder.shareholder_name }}</strong>
          <span v-if="shareholder.phone_number">{{ [shareholder.phone_country_code, shareholder.phone_number].filter(Boolean).join(' ') }}</span>
          <span v-if="shareholder.id_number">{{ t('staffRequestDetails.states.idNumberLabel', { id: shareholder.id_number }) }}</span>
          <span>{{ shareholder.id_file_name }}</span>
        </div>
        <a :href="shareholderIdDownloadUrl(shareholder.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.downloadIdFile') }}</a>
      </div>
    </div>
    <p v-else class="empty-state">{{ t('staffRequestDetails.states.noShareholdersRecorded') }}</p>
  </div>
</details>

          <details class="admin-accordion-card" open>
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.followUpTitle') }}</h2>
                <p>{{ t('staffRequestDetails.sections.followUpSubtitle') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="admin-inline-block-grid">
                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.addInternalComment') }}</h3></div>
                  <div class="field-block field-block--grow">
                    <span>{{ t('staffRequestDetails.form.visibility') }}</span>
                    <select v-model="commentVisibility" class="admin-select">
                      <option value="internal">{{ t('staffRequestDetails.form.internal') }}</option>
                      <option value="admin_only">{{ t('staffRequestDetails.form.adminOnly') }}</option>
                    </select>
                  </div>
                  <textarea v-model="commentText" rows="5" class="admin-textarea" :placeholder="t('staffRequestDetails.placeholders.commentText')"></textarea>
                  <div class="approve-actions">
                    <button class="primary-btn" type="button" :disabled="savingComment || !commentText.trim()" @click="submitComment">
                      {{ savingComment ? t('staffRequestDetails.actions.saving') : t('staffRequestDetails.actions.saveComment') }}
                    </button>
                  </div>
                </article>

                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.requestAdditionalDocument') }}</h3></div>
                  <input v-model="additionalDocumentTitle" type="text" class="admin-input" :placeholder="t('staffRequestDetails.placeholders.documentTitle')" />
                  <textarea v-model="additionalDocumentReason" rows="5" class="admin-textarea" :placeholder="t('staffRequestDetails.placeholders.additionalReason')"></textarea>
                  <div class="approve-actions">
                    <button class="primary-btn" type="button" :disabled="savingAdditionalDocument || !additionalDocumentTitle.trim()" @click="submitAdditionalDocumentRequest">
                      {{ savingAdditionalDocument ? t('staffRequestDetails.actions.saving') : t('staffRequestDetails.actions.createRequest') }}
                    </button>
                  </div>
                </article>
              </div>

              <article class="panel-card slim-card">
                <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.requestedAdditionalDocuments') }}</h3></div>
                <div v-if="requestItem.additional_documents?.length" class="timeline-list compact-list">
                  <div v-for="item in requestItem.additional_documents" :key="item.id" class="timeline-item">
                    <strong>{{ item.title }}</strong>
                    <p>{{ item.reason || t('staffRequestDetails.states.noReasonAdded') }}</p>
                    <span>{{ item.status }}<template v-if="item.file_name"> · {{ item.file_name }}</template></span>
                    <div v-if="item.file_name" class="approve-actions">
                      <a :href="additionalDocumentDownloadUrl(item.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('staffRequestDetails.actions.downloadFile') }}</a>
                    </div>
                  </div>
                </div>
                <p v-else class="empty-state">{{ t('staffRequestDetails.states.noAdditionalDocumentsRequested') }}</p>
              </article>
            </div>
          </details>

          <details class="admin-accordion-card">
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.emailComposerTitle') }}</h2>
                <p>{{ t('staffRequestDetails.sections.emailComposerSubtitle') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div class="admin-inline-block-grid">
                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.recipients') }}</h3></div>
                  <div class="field-block field-block--grow">
                    <span>{{ t('staffRequestDetails.form.bank') }}</span>
                    <select v-model="selectedBankId" class="admin-select">
                      <option :value="null">{{ t('staffRequestDetails.form.allBanks') }}</option>
                      <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                    </select>
                  </div>
                  <div class="field-block field-block--grow">
                    <span>{{ t('staffRequestDetails.form.agents') }}</span>
                    <select v-model="selectedAgentIds" class="admin-select" multiple size="6">
                      <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                        {{ agent.name }}<template v-if="agent.bank_name"> · {{ agent.bank_name }}</template>
                      </option>
                    </select>
                  </div>
                </article>

                <article class="panel-card slim-card">
                  <div class="panel-head"><h3>{{ t('staffRequestDetails.sections.emailBody') }}</h3></div>
                  <input v-model="emailSubject" type="text" class="admin-input" :placeholder="t('staffRequestDetails.placeholders.emailSubject')" />
                  <textarea v-model="emailBody" rows="7" class="admin-textarea" :placeholder="t('staffRequestDetails.placeholders.emailBody')"></textarea>
                  <input type="file" class="admin-input" multiple @change="handleMockAttachments" />
                  <div class="tag-row">
                    <span v-for="fileName in mockFiles" :key="fileName" class="soft-tag">{{ fileName }}</span>
                  </div>
                </article>
              </div>
            </div>
          </details>
        </div>

        <aside class="admin-workspace-side">
         <article class="panel-card slim-card">
  <div class="panel-head"><h2>{{ t('staffRequestDetails.sections.requestSummary') }}</h2></div>
  <div class="summary-grid">
    <div><span>{{ t('staffRequestDetails.summary.country') }}</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json), locale) }}</strong></div>
    <div><span>{{ t('staffRequestDetails.summary.requestedAmount') }}</span><strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong></div>
    <div><span>{{ t('staffRequestDetails.summary.currentContract') }}</span><strong>{{ requestItem.current_contract?.status || t('staffRequestDetails.states.emptyValue') }}</strong></div>
    <div><span>{{ t('staffRequestDetails.summary.assignments') }}</span><strong>{{ requestItem.assignments?.length || 0 }}</strong></div>
    <div><span>{{ t('staffRequestDetails.summary.email') }}</span><strong>{{ intakeEmail(requestItem.intake_details_json) }}</strong></div>
    <div><span>{{ t('staffRequestDetails.summary.phone') }}</span><strong>{{ intakePhoneDisplay(requestItem.intake_details_json) }}</strong></div>
    <div><span>{{ t('staffRequestDetails.summary.unifiedNumber') }}</span><strong>{{ intakeUnifiedNumber(requestItem.intake_details_json) }}</strong></div>
    <div><span>{{ t('staffRequestDetails.summary.nationalAddressNo') }}</span><strong>{{ intakeNationalAddressNumber(requestItem.intake_details_json) }}</strong></div>
  </div>
  <div class="notes-box">
    <span>{{ t('staffRequestDetails.summary.address') }}</span>
    <p>{{ intakeAddress(requestItem.intake_details_json) }}</p>
  </div>
  <div class="notes-box">
    <span>{{ t('staffRequestDetails.summary.requestNotes') }}</span>
    <p>{{ intakeNotes(requestItem.intake_details_json) }}</p>
  </div>
</article>

          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ t('staffRequestDetails.sections.recentInternalHistory') }}</h2></div>
            <div v-if="requestItem.comments?.length" class="timeline-list compact-list">
              <div v-for="comment in requestItem.comments.slice(0, 4)" :key="comment.id" class="timeline-item">
                <strong>{{ comment.user?.name || t('staffRequestDetails.states.system') }}</strong>
                <p>{{ comment.comment_text }}</p>
                <span>{{ new Date(comment.created_at).toLocaleString() }}</span>
              </div>
            </div>
            <p v-else class="empty-state">{{ t('staffRequestDetails.states.noInternalComments') }}</p>
          </article>

          <details class="admin-accordion-card slim-accordion">
            <summary>
              <div>
                <h2>{{ t('staffRequestDetails.sections.questionnaireAnswers') }}</h2>
                <p>{{ t('staffRequestDetails.sections.expandWhenNeeded') }}</p>
              </div>
            </summary>
            <div class="admin-accordion-card__body">
              <div v-if="requestItem.answers?.length" class="qa-list compact-list">
                <div v-for="answer in requestItem.answers" :key="answer.id" class="qa-item">
                  <h3>{{ answer.question?.question_text || t('staffRequestDetails.states.questionFallback') }}</h3>
                  <p>{{ answerText(answer) }}</p>
                </div>
              </div>
              <p v-else class="empty-state">{{ t('staffRequestDetails.states.noAnswersRecorded') }}</p>
            </div>
          </details>
        </aside>
      </div>
    </template>
  </section>
</template>
