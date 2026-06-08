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
const staffEditPermissions = ref<Record<number, boolean>>({})
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
  assignments: activeStaffAssignments.value.length,
}))

const activeStaffAssignments = computed(() =>
  (requestItem.value?.assignments ?? []).filter((entry) => entry?.is_active !== false),
)

const timelineRows = computed(() => buildTimelineRows(requestItem.value?.timeline, locale.value))

function timelineDateLabel(value: unknown) {
  return formatTimelineDate(value, locale.value)
}

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

function isStaffSelected(staffId: number) {
  return selectedStaffIds.value.includes(staffId)
}

function staffInitials(name: string | null | undefined) {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return 'ST'

  return parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('')
}

function assignmentRoleLabel(assignment: any) {
  return assignment?.is_primary
    ? t('adminAssignmentDetails.states.leadOwner')
    : assignment?.assignment_role || t('adminAssignmentDetails.states.support')
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
  const activeAssignments = (item?.assignments ?? []).filter((entry) => entry?.is_active !== false)
  selectedStaffIds.value = activeAssignments.map((entry) => Number(entry.staff_id))
  primaryStaffId.value = activeAssignments.find((entry) => entry.is_primary)?.staff_id ?? selectedStaffIds.value[0] ?? null
  staffEditPermissions.value = activeAssignments.reduce((carry, entry) => {
    carry[Number(entry.staff_id)] = Boolean(entry.can_request_client_updates)
    return carry
  }, {} as Record<number, boolean>)
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
  staffEditPermissions.value = {
    ...staffEditPermissions.value,
    [staffId]: false,
  }

  if (primaryStaffId.value === staffId) {
    primaryStaffId.value = selectedStaffIds.value[0] ?? null
  }
}

function handleStaffEditPermission(staffId: number, event: Event) {
  staffEditPermissions.value = {
    ...staffEditPermissions.value,
    [staffId]: (event.target as HTMLInputElement).checked,
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
      staff_edit_permissions: selectedStaffIds.value.reduce((carry, staffId) => {
        carry[staffId] = Boolean(staffEditPermissions.value[staffId])
        return carry
      }, {} as Record<number, boolean>),
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
          <div v-if="activeStaffAssignments.length" class="assignment-owner-grid">
            <article
              v-for="assignment in activeStaffAssignments"
              :key="assignment.id"
              class="assignment-owner-card"
              :class="{ 'is-primary': assignment.is_primary, 'can-request-updates': assignment.can_request_client_updates }"
            >
              <div class="assignment-owner-card__avatar">
                {{ staffInitials(assignment.staff?.name) }}
              </div>
              <div class="assignment-owner-card__body">
                <div class="assignment-owner-card__top">
                  <strong>{{ assignment.staff?.name || t('adminAssignmentDetails.states.staffMemberFallback') }}</strong>
                  <span class="assignment-owner-role" :class="{ 'is-primary': assignment.is_primary }">
                    {{ assignmentRoleLabel(assignment) }}
                  </span>
                </div>
                <p>{{ assignment.staff?.email || t('adminAssignmentDetails.states.noEmailSaved') }}</p>
                <span class="assignment-owner-badge" :class="{ 'is-enabled': assignment.can_request_client_updates }">
                  {{ assignment.can_request_client_updates ? t('adminAssignmentDetails.states.clientUpdatesEnabled') : t('adminAssignmentDetails.states.followUpOnly') }}
                </span>
              </div>
            </article>
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

            <div class="staff-picker-summary">
              <strong>{{ t('adminAssignmentDetails.states.selectedCount', { count: selectedStaffIds.length }) }}</strong>
              <span>{{ t('adminAssignmentDetails.sections.assignStaffSubtitle') }}</span>
            </div>

            <div class="staff-picker-grid">
              <article
                v-for="staff in staffDirectory"
                :key="staff.id"
                class="staff-picker-card"
                :class="{ 'is-selected': isStaffSelected(staff.id), 'is-primary': primaryStaffId === staff.id }"
              >
                <div class="staff-picker-card__main">
                  <label class="staff-picker-card__identity">
                    <input type="checkbox" :checked="isStaffSelected(staff.id)" @change="handleStaffCheckbox(staff.id, $event)" />
                    <span class="staff-picker-card__avatar">{{ staffInitials(staff.name) }}</span>
                    <span class="staff-picker-card__person">
                      <strong>{{ staff.name }}</strong>
                      <span>{{ staff.email }}</span>
                    </span>
                  </label>
                  <span v-if="isStaffSelected(staff.id)" class="staff-state-badge">
                    {{ primaryStaffId === staff.id ? t('adminAssignmentDetails.states.leadOwner') : t('adminAssignmentDetails.states.selected') }}
                  </span>
                </div>

                <div class="staff-picker-options">
                  <label class="staff-option-toggle" :class="{ 'is-active': primaryStaffId === staff.id, 'is-disabled': !isStaffSelected(staff.id) }">
                    <input
                      type="radio"
                      name="primary-staff"
                      :value="staff.id"
                      :checked="primaryStaffId === staff.id"
                      :disabled="!isStaffSelected(staff.id)"
                      @change="primaryStaffId = staff.id"
                    />
                    <span>
                      <strong>{{ t('adminAssignmentDetails.states.leadOwner') }}</strong>
                      <small>{{ t('adminAssignmentDetails.states.leadOwnerHint') }}</small>
                    </span>
                  </label>

                  <label class="staff-option-toggle staff-option-toggle--accent" :class="{ 'is-active': Boolean(staffEditPermissions[staff.id]), 'is-disabled': !isStaffSelected(staff.id) }">
                    <input
                      type="checkbox"
                      :checked="Boolean(staffEditPermissions[staff.id])"
                      :disabled="!isStaffSelected(staff.id)"
                      @change="handleStaffEditPermission(staff.id, $event)"
                    />
                    <span>
                      <strong>{{ t('adminAssignmentDetails.states.requestUpdatesTitle') }}</strong>
                      <small>{{ t('adminAssignmentDetails.states.requestUpdatesHint') }}</small>
                    </span>
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
              </article>
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

<style scoped>
.assignment-owner-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 14px;
}

.assignment-owner-card {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  min-width: 0;
  padding: 16px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 18px;
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.84)),
    #fff;
  box-shadow: 0 16px 36px rgba(15, 23, 42, 0.06);
}

.assignment-owner-card.is-primary {
  border-color: rgba(79, 70, 229, 0.34);
  background:
    linear-gradient(135deg, rgba(238, 242, 255, 0.88), rgba(255, 255, 255, 0.96)),
    #fff;
}

.assignment-owner-card.can-request-updates {
  border-color: rgba(20, 184, 166, 0.34);
}

.assignment-owner-card__avatar,
.staff-picker-card__avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 42px;
  height: 42px;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--admin-primary), #06b6d4);
  color: #fff;
  font-size: 0.82rem;
  font-weight: 900;
}

.assignment-owner-card__body {
  display: grid;
  gap: 8px;
  min-width: 0;
}

.assignment-owner-card__top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.assignment-owner-card__top strong {
  min-width: 0;
  color: var(--admin-text);
  font-size: 0.95rem;
  line-height: 1.35;
  word-break: break-word;
}

.assignment-owner-card__body p {
  margin: 0;
  color: var(--admin-text-muted);
  font-size: 0.82rem;
  line-height: 1.45;
  word-break: break-word;
}

.assignment-owner-role,
.assignment-owner-badge,
.staff-state-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: fit-content;
  min-height: 26px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 800;
  line-height: 1;
}

.assignment-owner-role {
  background: rgba(226, 232, 240, 0.78);
  color: var(--admin-text-muted);
}

.assignment-owner-role.is-primary {
  background: rgba(79, 70, 229, 0.12);
  color: var(--admin-primary-soft-text);
}

.assignment-owner-badge {
  background: rgba(241, 245, 249, 0.95);
  color: var(--admin-text-muted);
}

.assignment-owner-badge.is-enabled {
  background: rgba(20, 184, 166, 0.12);
  color: #0f766e;
}

.staff-picker-summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 16px;
  padding: 12px 14px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  border-radius: 16px;
  background: rgba(248, 250, 252, 0.78);
}

.staff-picker-summary strong {
  color: var(--admin-text);
  font-size: 0.9rem;
}

.staff-picker-summary span {
  color: var(--admin-text-muted);
  font-size: 0.82rem;
  line-height: 1.45;
}

.staff-picker-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
  gap: 16px;
  margin-bottom: 18px;
}

.staff-picker-card {
  display: grid;
  gap: 14px;
  min-width: 0;
  padding: 16px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 18px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.76)),
    #fff;
  box-shadow: 0 18px 42px rgba(15, 23, 42, 0.06);
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    transform 0.18s ease;
}

.staff-picker-card:hover {
  border-color: rgba(79, 70, 229, 0.26);
  transform: translateY(-1px);
  box-shadow: 0 22px 48px rgba(15, 23, 42, 0.08);
}

.staff-picker-card.is-selected {
  border-color: rgba(79, 70, 229, 0.4);
  background:
    linear-gradient(135deg, rgba(238, 242, 255, 0.78), rgba(255, 255, 255, 0.98)),
    #fff;
}

.staff-picker-card.is-primary {
  box-shadow: 0 20px 52px rgba(79, 70, 229, 0.16);
}

.staff-picker-card__main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
}

.staff-picker-card__identity {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  cursor: pointer;
}

.staff-picker-card__identity input,
.staff-option-toggle input {
  flex: 0 0 auto;
  width: 18px;
  height: 18px;
  margin: 0;
  accent-color: var(--admin-primary);
}

.staff-picker-card__person {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.staff-picker-card__person strong {
  color: var(--admin-text);
  font-size: 0.96rem;
  font-weight: 800;
  line-height: 1.35;
  word-break: break-word;
}

.staff-picker-card__person span {
  color: var(--admin-text-muted);
  font-size: 0.8rem;
  line-height: 1.4;
  word-break: break-word;
}

.staff-state-badge {
  flex: 0 0 auto;
  background: var(--admin-primary-soft);
  color: var(--admin-primary-soft-text);
}

.staff-picker-options {
  display: grid;
  gap: 10px;
}

.staff-option-toggle {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  min-width: 0;
  padding: 12px;
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.78);
  cursor: pointer;
  transition:
    border-color 0.18s ease,
    background 0.18s ease;
}

.staff-option-toggle.is-active {
  border-color: rgba(79, 70, 229, 0.36);
  background: rgba(238, 242, 255, 0.8);
}

.staff-option-toggle--accent.is-active {
  border-color: rgba(20, 184, 166, 0.38);
  background: rgba(240, 253, 250, 0.82);
}

.staff-option-toggle.is-disabled {
  opacity: 0.58;
  cursor: not-allowed;
}

.staff-option-toggle span {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.staff-option-toggle strong {
  color: var(--admin-text);
  font-size: 0.86rem;
  line-height: 1.35;
}

.staff-option-toggle small {
  color: var(--admin-text-muted);
  font-size: 0.76rem;
  line-height: 1.45;
}

.staff-picker-card__actions {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding-top: 2px;
}

.staff-permission-panel {
  padding: 12px;
  border: 1px dashed rgba(148, 163, 184, 0.28);
  border-radius: 14px;
  background: rgba(248, 250, 252, 0.76);
}

@media (max-width: 720px) {
  .assignment-owner-grid,
  .staff-picker-grid {
    grid-template-columns: 1fr;
  }

  .staff-picker-card__main {
    flex-direction: column;
  }
}
</style>
