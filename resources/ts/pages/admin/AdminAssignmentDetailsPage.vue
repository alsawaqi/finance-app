<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  assignRequestStaff,
  getAdminRequestDetails,
  getStaffDirectory,
  type FinanceRequestDetail,
  type StaffDirectoryMember,
} from '@/services/adminRequests'
import { countryNameFromCode } from '@/utils/countries'
import {
  intakeAddress,
  intakeCountryCode,
  intakeEmail,
  intakeFinanceType,
  intakeFullName,
  intakeNationalAddressNumber,
  intakePhoneDisplay,
  intakeRequestedAmount,
  intakeUnifiedNumber,
} from '@/utils/requestIntake'
import AdminQuickViewModal from './inc/AdminQuickViewModal.vue'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'
import { buildTimelineRows, formatTimelineDate } from '@/utils/requestTimeline'
import { formatContractStatus } from '@/utils/requestStatus'
import { formatDateTime } from '@/utils/dateTime'

const route = useRoute()

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const requestItem = ref<FinanceRequestDetail | null>(null)
const staffDirectory = ref<StaffDirectoryMember[]>([])
const selectedStaffIds = ref<number[]>([])
const primaryStaffId = ref<number | null>(null)
const assignmentNotes = ref('')
const permissionsOpen = ref<number[]>([])
const quickView = ref<'answers' | 'comments' | 'timeline' | null>(null)
const { t, locale } = useI18n()

const requestId = computed(() => route.params.id as string)
const requestDetailsTarget = computed(() => {
  const returnTo = typeof route.query.return_to === 'string' ? route.query.return_to : ''
  return returnTo && returnTo.startsWith('/admin/requests/')
    ? returnTo
    : { name: 'admin-request-details', params: { id: requestId.value } }
})

const activityCounts = computed(() => ({
  answers: requestItem.value?.answers?.length ?? 0,
  comments: requestItem.value?.comments?.length ?? 0,
  timeline: requestItem.value?.timeline?.length ?? 0,
  assignments: requestItem.value?.assignments?.length ?? 0,
}))

const timelineRows = computed(() => buildTimelineRows(requestItem.value?.timeline, locale.value))

function timelineDateLabel(value: unknown) {
  return formatTimelineDate(value, locale.value)
}

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

function answerText(answer: any) {
  if (!answer) return t('adminAssignmentDetails.states.emptyValue')
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return t('adminAssignmentDetails.states.emptyValue')
  return String(value)
}

function prefillAssignmentState(item: FinanceRequestDetail | null) {
  const activeAssignments = item?.assignments ?? []
  selectedStaffIds.value = activeAssignments.map((entry) => Number(entry.staff_id))
  primaryStaffId.value = activeAssignments.find((entry) => entry.is_primary)?.staff_id ?? selectedStaffIds.value[0] ?? null
  assignmentNotes.value = activeAssignments[0]?.notes || ''
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const [requestResponse, staffResponse] = await Promise.all([
      getAdminRequestDetails(requestId.value),
      getStaffDirectory(),
    ])

    requestItem.value = requestResponse.request ?? null
    staffDirectory.value = staffResponse.staff ?? []
    prefillAssignmentState(requestItem.value)
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminAssignmentDetails.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function handleStaffCheckbox(staffId: number, event: Event) {
  const checked = (event.target as HTMLInputElement).checked

  if (checked) {
    if (!selectedStaffIds.value.includes(staffId)) {
      selectedStaffIds.value = [...selectedStaffIds.value, staffId]
    }
    if (!primaryStaffId.value) {
      primaryStaffId.value = staffId
    }
    return
  }

  selectedStaffIds.value = selectedStaffIds.value.filter((id) => id !== staffId)

  if (primaryStaffId.value === staffId) {
    primaryStaffId.value = selectedStaffIds.value[0] ?? null
  }
}

function togglePermissions(staffId: number) {
  permissionsOpen.value = permissionsOpen.value.includes(staffId)
    ? permissionsOpen.value.filter((id) => id !== staffId)
    : [...permissionsOpen.value, staffId]
}

async function saveAssignment() {
  if (!requestItem.value || !selectedStaffIds.value.length) {
    errorMessage.value = t('adminAssignmentDetails.errors.selectStaffFirst')
    return
  }

  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await assignRequestStaff(requestItem.value.id, {
      staff_ids: selectedStaffIds.value,
      primary_staff_id: primaryStaffId.value,
      notes: assignmentNotes.value,
    })

    successMessage.value = t('adminAssignmentDetails.success.saved')
    await load()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminAssignmentDetails.errors.saveFailed')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminAssignmentDetails.hero.eyebrow') }}</p>
        <h4>{{ t('adminAssignmentDetails.hero.title') }}</h4>
        <p class="subtext">{{ t('adminAssignmentDetails.hero.subtitle') }}</p>
      </div>
      <div class="actions-row">
        <RouterLink :to="{ name: 'admin-assignments' }" class="ghost-btn">{{ t('adminAssignmentDetails.hero.backToQueue') }}</RouterLink>
        <RouterLink :to="requestDetailsTarget" class="ghost-btn">{{ t('adminAssignmentDetails.hero.openRequestReview') }}</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('adminAssignmentDetails.states.loading') }}</p>
    <p v-else-if="errorMessage && !requestItem" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>

    <template v-else-if="requestItem">
      <div class="admin-workspace-summary-grid">
        <article class="admin-workspace-stat">
          <span>{{ t('adminAssignmentDetails.summary.request') }}</span>
          <strong>{{ requestItem.reference_number }}</strong>
          <small>{{ requestItem.approval_reference_number || t('adminAssignmentDetails.states.approvalPending') }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>{{ t('adminAssignmentDetails.summary.client') }}</span>
          <strong>{{ intakeFullName(requestItem.intake_details_json, requestItem.client?.name || t('adminAssignmentDetails.states.clientFallback')) }}</strong>
          <small>{{ requestItem.client?.email || t('adminAssignmentDetails.states.noEmailSaved') }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>{{ t('adminAssignmentDetails.summary.country') }}</span>
          <strong>{{ countryNameFromCode(requestItem.country_code || intakeCountryCode(requestItem.intake_details_json), locale) }}</strong>
          <small>{{ intakeFinanceType(requestItem.intake_details_json, t('adminAssignmentDetails.states.emptyValue'), locale) }}</small>
        </article>
        <article class="admin-workspace-stat">
          <span>{{ t('adminAssignmentDetails.summary.amount') }}</span>
          <strong>{{ intakeRequestedAmount(requestItem.intake_details_json, t('adminAssignmentDetails.states.emptyValue'), true) }}</strong>
          <small>{{ stageMeta(requestItem.workflow_stage).label }}</small>
        </article>
      </div>

      <article class="panel-card admin-quick-panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('adminAssignmentDetails.quick.title') }}</h2>
            <p class="subtext">{{ t('adminAssignmentDetails.quick.subtitle') }}</p>
          </div>
        </div>

        <div class="admin-quick-actions">
          <button type="button" class="admin-quick-action" @click="quickView = 'answers'">
            <strong>{{ t('adminAssignmentDetails.quick.questionnaire') }}</strong>
            <span>{{ t('adminAssignmentDetails.quick.answersCount', { count: activityCounts.answers }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'comments'">
            <strong>{{ t('adminAssignmentDetails.quick.comments') }}</strong>
            <span>{{ t('adminAssignmentDetails.quick.notesCount', { count: activityCounts.comments }) }}</span>
          </button>
          <button type="button" class="admin-quick-action" @click="quickView = 'timeline'">
            <strong>{{ t('adminAssignmentDetails.quick.timeline') }}</strong>
            <span>{{ t('adminAssignmentDetails.quick.eventsCount', { count: activityCounts.timeline }) }}</span>
          </button>
        </div>
      </article>

      <div class="request-top-panel-grid">
        <article class="panel-card slim-card">
          <div class="panel-head"><h2>{{ t('adminAssignmentDetails.sections.contractSnapshot') }}</h2></div>
          <div class="summary-grid summary-grid--tight">
            <div><span>{{ t('adminAssignmentDetails.summary.version') }}</span><strong>{{ requestItem.current_contract?.version_no || t('adminAssignmentDetails.states.emptyValue') }}</strong></div>
            <div><span>{{ t('adminAssignmentDetails.summary.status') }}</span><strong>{{ formatContractStatus(requestItem.current_contract?.status, locale, t('adminAssignmentDetails.states.emptyValue')) }}</strong></div>
            <div><span>{{ t('adminAssignmentDetails.summary.adminSigned') }}</span><strong>{{ formatDateTime(requestItem.current_contract?.admin_signed_at, locale, t('adminAssignmentDetails.states.pending')) }}</strong></div>
            <div><span>{{ t('adminAssignmentDetails.summary.clientSigned') }}</span><strong>{{ formatDateTime(requestItem.current_contract?.client_signed_at, locale, t('adminAssignmentDetails.states.pending')) }}</strong></div>
          </div>
        </article>

        <article class="panel-card slim-card">
          <div class="panel-head"><h2>{{ t('adminAssignmentDetails.sections.applicantDetails') }}</h2></div>
          <div class="summary-grid summary-grid--tight">
            <div><span>{{ t('adminAssignmentDetails.summary.email') }}</span><strong>{{ intakeEmail(requestItem.intake_details_json) }}</strong></div>
            <div><span>{{ t('adminAssignmentDetails.summary.phone') }}</span><strong>{{ intakePhoneDisplay(requestItem.intake_details_json) }}</strong></div>
            <div><span>{{ t('adminAssignmentDetails.summary.unifiedNumber') }}</span><strong>{{ intakeUnifiedNumber(requestItem.intake_details_json) }}</strong></div>
            <div><span>{{ t('adminAssignmentDetails.summary.nationalAddressNo') }}</span><strong>{{ intakeNationalAddressNumber(requestItem.intake_details_json) }}</strong></div>
          </div>
          <div class="notes-box">
            <span>{{ t('adminAssignmentDetails.summary.address') }}</span>
            <p>{{ intakeAddress(requestItem.intake_details_json) }}</p>
          </div>
        </article>

        <article class="panel-card slim-card request-top-panel--span-2">
          <div class="panel-head"><h2>{{ t('adminAssignmentDetails.sections.currentOwners') }}</h2></div>
          <div v-if="requestItem.assignments?.length" class="assignment-chip-list assignment-chip-list--stacked">
            <div v-for="assignment in requestItem.assignments" :key="assignment.id" class="assignment-chip">
              <strong>{{ assignment.staff?.name || t('adminAssignmentDetails.states.staffMemberFallback') }}</strong>
              <span>{{ assignment.is_primary ? t('adminAssignmentDetails.states.leadOwner') : assignment.assignment_role || t('adminAssignmentDetails.states.support') }}</span>
            </div>
          </div>
          <p v-else class="empty-state">{{ t('adminAssignmentDetails.states.noStaffAssigned') }}</p>
        </article>
      </div>

      <div class="admin-workspace-layout admin-workspace-layout--single">
        <div class="admin-workspace-main">
          <article class="panel-card wide-card action-card">
            <div class="panel-head">
              <div>
                <h2>{{ t('adminAssignmentDetails.sections.assignStaff') }}</h2>
                <p class="subtext">{{ t('adminAssignmentDetails.sections.assignStaffSubtitle') }}</p>
              </div>
            </div>

            <div class="staff-picker-grid">
              <label v-for="staff in staffDirectory" :key="staff.id" class="staff-picker-card">
                <div class="staff-picker-card__main">
                  <div class="staff-picker-card__identity">
                    <input type="checkbox" :checked="selectedStaffIds.includes(staff.id)" @change="handleStaffCheckbox(staff.id, $event)" />
                    <div>
                      <strong>{{ staff.name }}</strong>
                      <p>{{ staff.email }}</p>
                    </div>
                  </div>
                  <label class="staff-primary-toggle">
                    <input type="radio" name="primary-staff" :value="staff.id" :checked="primaryStaffId === staff.id" :disabled="!selectedStaffIds.includes(staff.id)" @change="primaryStaffId = staff.id" />
                    <span>{{ t('adminAssignmentDetails.states.leadOwner') }}</span>
                  </label>
                </div>

                <div class="staff-picker-card__actions">
                  <button type="button" class="link-btn" @click.prevent="togglePermissions(staff.id)">
                    {{ permissionsOpen.includes(staff.id) ? t('adminAssignmentDetails.actions.hidePermissions') : t('adminAssignmentDetails.actions.showPermissions') }}
                  </button>
                </div>

                <div v-if="permissionsOpen.includes(staff.id)" class="staff-permission-panel">
                  <span v-if="!staff.permission_names?.length" class="muted-small">{{ t('adminAssignmentDetails.states.noDirectPermissions') }}</span>
                  <div v-else class="tag-row">
                    <span v-for="permission in staff.permission_names" :key="permission" class="soft-tag">{{ permission }}</span>
                  </div>
                </div>
              </label>
            </div>

            <div class="notes-box">
              <span>{{ t('adminAssignmentDetails.sections.assignmentNotes') }}</span>
              <textarea v-model="assignmentNotes" rows="4" class="admin-textarea" :placeholder="t('adminAssignmentDetails.sections.assignmentNotesPlaceholder')"></textarea>
            </div>

            <div class="approve-actions">
              <button class="primary-btn" type="button" :disabled="saving || !selectedStaffIds.length" @click="saveAssignment">
                {{ saving ? t('adminAssignmentDetails.actions.savingAssignment') : t('adminAssignmentDetails.actions.saveAssignment') }}
              </button>
            </div>
          </article>
        </div>

      </div>

      <AdminQuickViewModal :model-value="quickView !== null" @update:model-value="(value) => { if (!value) quickView = null }" :title="quickView === 'answers' ? t('adminAssignmentDetails.modal.questionnaireAnswers') : quickView === 'comments' ? t('adminAssignmentDetails.modal.internalComments') : t('adminAssignmentDetails.modal.timeline')" :subtitle="t('adminAssignmentDetails.modal.subtitle')" wide>
        <div v-if="quickView === 'answers'" class="qa-list">
          <div v-if="requestItem.answers?.length" v-for="answer in requestItem.answers" :key="answer.id" class="qa-item">
            <h3>{{ answer.question?.question_text || t('adminAssignmentDetails.states.questionFallback') }}</h3>
            <p>{{ answerText(answer) }}</p>
          </div>
          <p v-else class="empty-state">{{ t('adminAssignmentDetails.states.noAnswersRecorded') }}</p>
        </div>

        <div v-else-if="quickView === 'comments'" class="timeline-list">
          <div v-if="requestItem.comments?.length" v-for="comment in requestItem.comments" :key="comment.id" class="timeline-item">
            <strong>{{ comment.user?.name || t('adminAssignmentDetails.states.system') }}</strong>
            <p>{{ comment.comment_text }}</p>
                    <span>{{ formatDateTime(comment.created_at, locale, t('adminAssignmentDetails.states.emptyValue')) }} · {{ comment.visibility }}</span>
          </div>
          <p v-else class="empty-state">{{ t('adminAssignmentDetails.states.noFollowUpComments') }}</p>
        </div>

        <div v-else class="timeline-list">
          <template v-if="timelineRows.length">
            <template v-for="(row, index) in timelineRows" :key="row.entry.id ?? `${row.entry.event_type ?? 'timeline'}-${index}`">
              <div v-if="row.gapLabel" class="timeline-gap-indicator">{{ row.gapLabel }}</div>
              <div class="timeline-item timeline-item--event">
                <strong>{{ row.entry.event_title || row.entry.event_type }}</strong>
                <p>{{ row.entry.event_description || t('adminAssignmentDetails.states.emptyValue') }}</p>
                <span>{{ timelineDateLabel(row.entry.created_at) }}</span>
              </div>
            </template>
          </template>
          <p v-else class="empty-state">{{ t('adminAssignmentDetails.states.noTimelineEvents') }}</p>
        </div>
      </AdminQuickViewModal>
    </template>
  </section>
</template>
