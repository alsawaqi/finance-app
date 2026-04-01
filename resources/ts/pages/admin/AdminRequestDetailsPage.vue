<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  adminContractDownloadUrl,
  adminRequestAttachmentDownloadUrl,
  adminRequestShareholderIdDownloadUrl,
  approveAdminRequest,
  getAdminRequestDetails,
} from '@/services/adminRequests'
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
import AdminQuickViewModal from './inc/AdminQuickViewModal.vue'

const route = useRoute()
const router = useRouter()
const requestItem = ref<any | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const approving = ref(false)
const approvalNotes = ref('')
const quickView = ref<'answers' | 'attachments' | 'shareholders' | 'assignments' | 'comments' | 'timeline' | null>(null)
const { t, locale } = useI18n()

const requestId = computed(() => route.params.id as string)
const activityCounts = computed(() => ({
  comments: requestItem.value?.comments?.length ?? 0,
  timeline: requestItem.value?.timeline?.length ?? 0,
  emails: requestItem.value?.emails?.length ?? 0,
  assignments: requestItem.value?.assignments?.length ?? 0,
  answers: requestItem.value?.answers?.length ?? 0,
  attachments: requestItem.value?.attachments?.length ?? 0,
  shareholders: requestItem.value?.shareholders?.length ?? 0,
}))

function answerText(answer: any) {
  if (!answer) return t('adminRequestDetails.states.emptyValue')
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return t('adminRequestDetails.states.emptyValue')
  return String(value)
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await getAdminRequestDetails(requestId.value)
    requestItem.value = data.request ?? null
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function attachmentDownloadUrl(attachmentId: number | string) {
  return adminRequestAttachmentDownloadUrl(requestId.value, attachmentId)
}

function shareholderIdDownloadUrl(shareholderId: number | string) {
  return adminRequestShareholderIdDownloadUrl(requestId.value, shareholderId)
}

async function approveRequest() {
  if (!requestItem.value) return
  approving.value = true
  try {
    await approveAdminRequest(requestItem.value.id, { approval_notes: approvalNotes.value })
    await router.push({ name: 'admin-request-contract', params: { id: requestItem.value.id } })
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestDetails.errors.approveFailed')
  } finally {
    approving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminRequestDetails.hero.eyebrow') }}</p>
        <h1>{{ t('adminRequestDetails.hero.title') }}</h1>
        <p class="subtext">{{ t('adminRequestDetails.hero.subtitle') }}</p>
      </div>
      <div class="actions-row">
        <RouterLink :to="{ name: 'admin-new-requests' }" class="ghost-btn">{{ t('adminRequestDetails.hero.backToQueue') }}</RouterLink>
        <a v-if="requestItem?.current_contract?.contract_pdf_path" :href="adminContractDownloadUrl(requestId)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.hero.downloadContractPdf') }}</a>
        <RouterLink v-if="requestItem?.approval_reference_number || requestItem?.current_contract" :to="{ name: 'admin-request-contract', params: { id: requestId } }" class="primary-btn">{{ t('adminRequestDetails.hero.goToContract') }}</RouterLink>
        <RouterLink v-if="requestItem?.current_contract?.client_signed_at" :to="{ name: 'admin-assignment-details', params: { id: requestId } }" class="ghost-btn">{{ t('adminRequestDetails.hero.assignStaff') }}</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('adminRequestDetails.states.loading') }}</p>
    <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>

    <template v-else-if="requestItem">
      <div class="admin-workspace-summary-grid">
        <article class="admin-workspace-stat">
          <span>{{ t('adminRequestDetails.summary.status') }}</span>
          <strong>{{ requestItem.status }}</strong>
          <small>{{ t('adminRequestDetails.summary.currentBusinessState') }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>{{ t('adminRequestDetails.summary.stage') }}</span>
          <strong>{{ requestItem.workflow_stage }}</strong>
          <small>{{ t('adminRequestDetails.summary.operationalStage') }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>{{ t('adminRequestDetails.summary.client') }}</span>
          <strong>{{ intakeFullName(requestItem.intake_details_json, requestItem.client?.name || t('adminRequestDetails.states.clientFallback')) }}</strong>
          <small>{{ requestItem.client?.email || t('adminRequestDetails.states.noEmailSaved') }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>{{ t('adminRequestDetails.summary.requestedAmount') }}</span>
          <strong>{{ intakeRequestedAmount(requestItem.intake_details_json) }}</strong>
          <small>{{ intakeFinanceType(requestItem.intake_details_json) }}</small>
        </article>
      </div>

      <article class="panel-card admin-quick-panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('adminRequestDetails.quick.title') }}</h2>
            <p class="subtext">{{ t('adminRequestDetails.quick.subtitle') }}</p>
          </div>
        </div>

        <div class="admin-quick-actions">
          <button type="button" class="admin-quick-action" @click="quickView = 'answers'">
            <strong>{{ t('adminRequestDetails.quick.questionnaire') }}</strong>
            <span>{{ t('adminRequestDetails.quick.answersCount', { count: activityCounts.answers }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'attachments'">
            <strong>{{ t('adminRequestDetails.quick.uploadedFiles') }}</strong>
            <span>{{ t('adminRequestDetails.quick.filesCount', { count: activityCounts.attachments }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'shareholders'">
            <strong>{{ t('adminRequestDetails.quick.shareholders') }}</strong>
            <span>{{ t('adminRequestDetails.quick.recordsCount', { count: activityCounts.shareholders }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'assignments'">
            <strong>{{ t('adminRequestDetails.quick.assignedStaff') }}</strong>
            <span>{{ t('adminRequestDetails.quick.ownersCount', { count: activityCounts.assignments }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'comments'">
            <strong>{{ t('adminRequestDetails.quick.comments') }}</strong>
            <span>{{ t('adminRequestDetails.quick.notesCount', { count: activityCounts.comments }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'timeline'">
            <strong>{{ t('adminRequestDetails.quick.timeline') }}</strong>
            <span>{{ t('adminRequestDetails.quick.eventsCount', { count: activityCounts.timeline }) }}</span>
          </button>
        </div>
      </article>

      <div class="admin-workspace-layout admin-workspace-layout--compact-side">
        <div class="admin-workspace-main">
          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ t('adminRequestDetails.sections.submissionSummary') }}</h2></div>

            <div class="summary-grid">
  <div><span>{{ t('adminRequestDetails.summary.requestReference') }}</span><strong>{{ requestItem.reference_number }}</strong></div>
  <div><span>{{ t('adminRequestDetails.summary.approvalReference') }}</span><strong>{{ requestItem.approval_reference_number || t('adminRequestDetails.states.pendingApproval') }}</strong></div>
  <div><span>{{ t('adminRequestDetails.summary.country') }}</span><strong>{{ countryNameFromCode(intakeCountryCode(requestItem.intake_details_json), locale) }}</strong></div>
  <div><span>{{ t('adminRequestDetails.summary.submitted') }}</span><strong>{{ requestItem.submitted_at ? new Date(requestItem.submitted_at).toLocaleString() : t('adminRequestDetails.states.emptyValue') }}</strong></div>
  <div><span>{{ t('adminRequestDetails.summary.applicantType') }}</span><strong>{{ requestItem.applicant_type || t('adminRequestDetails.states.individual') }}</strong></div>
  <div><span>{{ t('adminRequestDetails.summary.companyName') }}</span><strong>{{ requestItem.company_name || t('adminRequestDetails.states.emptyValue') }}</strong></div>
  <div><span>{{ t('adminRequestDetails.summary.email') }}</span><strong>{{ intakeEmail(requestItem.intake_details_json) }}</strong></div>
  <div><span>{{ t('adminRequestDetails.summary.phone') }}</span><strong>{{ intakePhoneDisplay(requestItem.intake_details_json) }}</strong></div>
  <div><span>{{ t('adminRequestDetails.summary.unifiedNumber') }}</span><strong>{{ intakeUnifiedNumber(requestItem.intake_details_json) }}</strong></div>
  <div><span>{{ t('adminRequestDetails.summary.nationalAddressNo') }}</span><strong>{{ intakeNationalAddressNumber(requestItem.intake_details_json) }}</strong></div>
</div>

<div class="notes-box">
  <span>{{ t('adminRequestDetails.summary.fullAddress') }}</span>
  <p>{{ intakeAddress(requestItem.intake_details_json) }}</p>
</div>

<div class="notes-box">
  <span>{{ t('adminRequestDetails.summary.supportingNotes') }}</span>
  <p>{{ intakeNotes(requestItem.intake_details_json) }}</p>
</div>
           
          </article>
        </div>

        <aside class="admin-workspace-side">
          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ t('adminRequestDetails.sections.contractState') }}</h2></div>
            <div class="summary-grid summary-grid--tight">
              <div><span>{{ t('adminRequestDetails.summary.contractVersion') }}</span><strong>{{ requestItem.current_contract?.version_no || t('adminRequestDetails.states.emptyValue') }}</strong></div>
              <div><span>{{ t('adminRequestDetails.summary.contractStatus') }}</span><strong>{{ requestItem.current_contract?.status || t('adminRequestDetails.states.emptyValue') }}</strong></div>
              <div><span>{{ t('adminRequestDetails.summary.adminSigned') }}</span><strong>{{ requestItem.current_contract?.admin_signed_at ? new Date(requestItem.current_contract.admin_signed_at).toLocaleString() : t('adminRequestDetails.states.pending') }}</strong></div>
              <div><span>{{ t('adminRequestDetails.summary.clientSigned') }}</span><strong>{{ requestItem.current_contract?.client_signed_at ? new Date(requestItem.current_contract.client_signed_at).toLocaleString() : t('adminRequestDetails.states.pending') }}</strong></div>
            </div>
          </article>

          <article class="panel-card slim-card">
            <div class="panel-head"><h2>{{ t('adminRequestDetails.sections.quickCounts') }}</h2></div>
            <div class="catalog-mini-stats">
              <div><span>{{ t('adminRequestDetails.summary.assignments') }}</span><strong>{{ activityCounts.assignments }}</strong></div>
              <div><span>{{ t('adminRequestDetails.summary.comments') }}</span><strong>{{ activityCounts.comments }}</strong></div>
              <div><span>{{ t('adminRequestDetails.summary.timelineEvents') }}</span><strong>{{ activityCounts.timeline }}</strong></div>
              <div><span>{{ t('adminRequestDetails.summary.emailLogs') }}</span><strong>{{ activityCounts.emails }}</strong></div>
            </div>
          </article>

          <article v-if="!requestItem.approval_reference_number" class="panel-card slim-card action-card">
            <div class="panel-head"><h2>{{ t('adminRequestDetails.sections.approveRequest') }}</h2></div>
            <p class="subtext">{{ t('adminRequestDetails.sections.approveSubtitle') }}</p>
            <textarea v-model="approvalNotes" rows="5" class="admin-textarea" :placeholder="t('adminRequestDetails.sections.approvePlaceholder')"></textarea>
            <div class="approve-actions">
              <button class="primary-btn" type="button" :disabled="approving" @click="approveRequest">
                {{ approving ? t('adminRequestDetails.actions.approving') : t('adminRequestDetails.actions.approveAndContinue') }}
              </button>
            </div>
          </article>
        </aside>
      </div>

      <AdminQuickViewModal
        :model-value="quickView !== null"
        @update:model-value="(value) => { if (!value) quickView = null }"
        :title="quickView === 'answers'
          ? t('adminRequestDetails.modal.questionnaireAnswers')
          : quickView === 'attachments'
            ? t('adminRequestDetails.modal.uploadedFiles')
            : quickView === 'shareholders'
              ? t('adminRequestDetails.modal.shareholders')
              : quickView === 'assignments'
                ? t('adminRequestDetails.modal.assignedStaff')
                : quickView === 'comments'
                  ? t('adminRequestDetails.modal.internalComments')
                  : t('adminRequestDetails.modal.timeline')"
        :subtitle="t('adminRequestDetails.modal.subtitle')"
        wide
      >
        <div v-if="quickView === 'answers'" class="qa-list">
          <div v-if="requestItem.answers?.length" v-for="answer in requestItem.answers" :key="answer.id" class="qa-item">
            <h3>{{ answer.question?.question_text || t('adminRequestDetails.states.questionFallback') }}</h3>
            <p>{{ answerText(answer) }}</p>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noAnswersRecorded') }}</p>
        </div>

        <div v-else-if="quickView === 'attachments'" class="file-list">
          <div v-if="requestItem.attachments?.length" v-for="file in requestItem.attachments" :key="file.id" class="file-item">
            <div>
              <strong>{{ file.file_name }}</strong>
              <span>{{ file.category }}</span>
            </div>
            <a :href="attachmentDownloadUrl(file.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.actions.download') }}</a>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noInitialAttachments') }}</p>
        </div>

       <div v-else-if="quickView === 'shareholders'" class="qa-list">
  <div v-if="requestItem.shareholders?.length" v-for="shareholder in requestItem.shareholders" :key="shareholder.id" class="qa-item">
    <strong>{{ shareholder.shareholder_name }}</strong>
    <p v-if="shareholder.phone_number">{{ [shareholder.phone_country_code, shareholder.phone_number].filter(Boolean).join(' ') }}</p>
    <p v-if="shareholder.id_number">{{ t('adminRequestDetails.states.idNumberLabel', { id: shareholder.id_number }) }}</p>
    <p>{{ shareholder.id_file_name }}</p>
    <div class="approve-actions">
      <a :href="shareholderIdDownloadUrl(shareholder.id)" target="_blank" rel="noopener" class="ghost-btn">{{ t('adminRequestDetails.actions.downloadIdFile') }}</a>
    </div>
  </div>
  <p v-else class="empty-state">{{ t('adminRequestDetails.states.noShareholdersRecorded') }}</p>
</div>

        <div v-else-if="quickView === 'assignments'" class="assignment-chip-list assignment-chip-list--stacked">
          <div v-if="requestItem.assignments?.length" v-for="assignment in requestItem.assignments" :key="assignment.id" class="assignment-chip">
            <strong>{{ assignment.staff?.name || t('adminRequestDetails.states.staffMemberFallback') }}</strong>
            <span>{{ assignment.is_primary ? t('adminRequestDetails.states.leadOwner') : assignment.assignment_role || t('adminRequestDetails.states.support') }}</span>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noStaffAssigned') }}</p>
        </div>

        <div v-else-if="quickView === 'comments'" class="timeline-list">
          <div v-if="requestItem.comments?.length" v-for="comment in requestItem.comments" :key="comment.id" class="timeline-item">
            <strong>{{ comment.user?.name || t('adminRequestDetails.states.system') }}</strong>
            <p>{{ comment.comment_text }}</p>
            <span>{{ new Date(comment.created_at).toLocaleString() }} · {{ comment.visibility }}</span>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noCommentsRecorded') }}</p>
        </div>

        <div v-else class="timeline-list">
          <div v-if="requestItem.timeline?.length" v-for="entry in requestItem.timeline" :key="entry.id" class="timeline-item">
            <strong>{{ entry.event_title || entry.event_type }}</strong>
            <p>{{ entry.event_description || t('adminRequestDetails.states.emptyValue') }}</p>
            <span>{{ new Date(entry.created_at).toLocaleString() }}</span>
          </div>
          <p v-else class="empty-state">{{ t('adminRequestDetails.states.noTimelineEvents') }}</p>
        </div>
      </AdminQuickViewModal>
    </template>
  </section>
</template>
