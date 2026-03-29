<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  assignRequestStaff,
  getAdminRequestDetails,
  getStaffDirectory,
  listReadyForAssignment,
  type AssignmentReadyRequest,
  type FinanceRequestDetail,
  type StaffDirectoryMember,
} from '@/services/adminRequests'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const detailLoading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const requests = ref<AssignmentReadyRequest[]>([])
const staffDirectory = ref<StaffDirectoryMember[]>([])
const selectedRequestId = ref<number | null>(null)
const selectedRequest = ref<FinanceRequestDetail | null>(null)

const selectedStaffIds = ref<number[]>([])
const primaryStaffId = ref<number | null>(null)
const assignmentNotes = ref('')

const selectedSummary = computed(() => requests.value.find((item) => item.id === selectedRequestId.value) ?? null)

function answerText(answer: any) {
  if (!answer) return '—'
  if (answer.answer_text) return answer.answer_text
  const value = answer.answer_value_json
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

function prefillAssignmentState(requestItem: FinanceRequestDetail | null) {
  const activeAssignments = requestItem?.assignments ?? []
  selectedStaffIds.value = activeAssignments.map((item) => Number(item.staff_id))
  primaryStaffId.value = activeAssignments.find((item) => item.is_primary)?.staff_id ?? selectedStaffIds.value[0] ?? null
  assignmentNotes.value = activeAssignments[0]?.notes || ''
}

async function loadCollections() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [requestsResponse, staffResponse] = await Promise.all([listReadyForAssignment(), getStaffDirectory()])
    requests.value = requestsResponse.requests ?? []
    staffDirectory.value = staffResponse.staff ?? []

    const routeRequestId = Number(route.query.requestId)
    if (Number.isFinite(routeRequestId) && requests.value.some((item) => item.id === routeRequestId)) {
      selectedRequestId.value = routeRequestId
    } else {
      selectedRequestId.value = requests.value[0]?.id ?? null
    }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load assignment workspace.'
  } finally {
    loading.value = false
  }
}

async function loadSelectedRequest() {
  if (!selectedRequestId.value) {
    selectedRequest.value = null
    selectedStaffIds.value = []
    primaryStaffId.value = null
    assignmentNotes.value = ''
    return
  }

  detailLoading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await getAdminRequestDetails(selectedRequestId.value)
    selectedRequest.value = data.request ?? null
    prefillAssignmentState(selectedRequest.value)
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load selected request.'
  } finally {
    detailLoading.value = false
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

async function saveAssignment() {
  if (!selectedRequestId.value || !selectedStaffIds.value.length) {
    errorMessage.value = 'Please select at least one staff member before saving the assignment.'
    return
  }

  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await assignRequestStaff(selectedRequestId.value, {
      staff_ids: selectedStaffIds.value,
      primary_staff_id: primaryStaffId.value,
      notes: assignmentNotes.value,
    })

    successMessage.value = 'Assignment saved successfully.'
    await loadCollections()
    await loadSelectedRequest()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to save the staff assignment.'
  } finally {
    saving.value = false
  }
}

watch(selectedRequestId, async (value) => {
  const nextQuery = value ? { ...route.query, requestId: String(value) } : { ...route.query }
  if (!value) delete nextQuery.requestId
  await router.replace({ query: nextQuery })
  await loadSelectedRequest()
})

onMounted(async () => {
  await loadCollections()
  await loadSelectedRequest()
})
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">Admin assignment</p>
        <h1>Assign Signed Requests</h1>
        <p class="subtext">Once the contract is fully signed, assign the request to one or more staff members so the follow-up workspace can begin.</p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="loadCollections">Refresh queue</button>
        <RouterLink v-if="selectedRequestId" :to="{ name: 'admin-request-details', params: { id: selectedRequestId } }" class="primary-btn">Open request details</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading signed requests…</p>
    <p v-else-if="errorMessage && !selectedRequest" class="error-state">{{ errorMessage }}</p>

    <template v-else>
      <article class="panel-card">
        <div class="panel-head">
          <div>
            <h2>Ready for assignment</h2>
            <p class="subtext">Only requests with a signed contract are shown here.</p>
          </div>
          <span class="count-pill">{{ requests.length }} requests</span>
        </div>

        <p v-if="!requests.length" class="empty-state">No signed requests are waiting for staff assignment.</p>

        <div v-else class="table-wrap">
          <table class="request-table">
            <thead>
              <tr>
                <th>Request</th>
                <th>Client</th>
                <th>Finance type</th>
                <th>Signed</th>
                <th>Current handling</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in requests" :key="item.id" :class="{ 'is-selected-row': selectedRequestId === item.id }">
                <td>
                  <strong>{{ item.reference_number }}</strong>
                  <div class="muted-small">{{ item.approval_reference_number || 'Approval pending' }}</div>
                </td>
                <td>
                  <strong>{{ intakeFullName(item.intake_details_json, item.client?.name || 'Client') }}</strong>
                  <div class="muted-small">{{ item.client?.email || '—' }}</div>
                </td>
                <td>
                  <div>{{ intakeFinanceType(item.intake_details_json) }}</div>
                  <div class="muted-small">{{ intakeRequestedAmount(item.intake_details_json) }}</div>
                </td>
                <td>{{ item.current_contract?.client_signed_at ? new Date(item.current_contract.client_signed_at).toLocaleString() : 'Pending' }}</td>
                <td>
                  <span class="status-badge">{{ item.workflow_stage }}</span>
                  <div class="muted-small">
                    <template v-if="item.assignments?.length">
                      {{ item.assignments.map((entry) => entry.staff?.name).filter(Boolean).join(', ') }}
                    </template>
                    <template v-else>Not assigned yet</template>
                  </div>
                </td>
                <td>
                  <button class="primary-btn small-btn" type="button" @click="selectedRequestId = item.id">Manage</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>

      <p v-if="errorMessage && selectedRequest" class="error-state">{{ errorMessage }}</p>
      <p v-if="successMessage" class="success-state">{{ successMessage }}</p>

      <div v-if="selectedRequest" class="details-grid">
        <article class="panel-card info-card">
          <div class="panel-head"><h2>Request summary</h2></div>
          <div class="summary-grid">
            <div><span>Request</span><strong>{{ selectedRequest.reference_number }}</strong></div>
            <div><span>Client</span><strong>{{ selectedRequest.client?.name || 'Client' }}</strong></div>
            <div><span>Country</span><strong>{{ countryNameFromCode(intakeCountryCode(selectedRequest.intake_details_json)) }}</strong></div>
            <div><span>Finance Type</span><strong>{{ intakeFinanceType(selectedRequest.intake_details_json) }}</strong></div>
            <div><span>Requested Amount</span><strong>{{ intakeRequestedAmount(selectedRequest.intake_details_json) }}</strong></div>
            <div><span>Workflow Stage</span><strong>{{ selectedRequest.workflow_stage }}</strong></div>
          </div>
        </article>

        <article class="panel-card info-card">
          <div class="panel-head"><h2>Contract and ownership</h2></div>
          <div class="summary-grid">
            <div><span>Contract Status</span><strong>{{ selectedRequest.current_contract?.status || '—' }}</strong></div>
            <div><span>Contract Version</span><strong>{{ selectedRequest.current_contract?.version_no || '—' }}</strong></div>
            <div><span>Client Signed</span><strong>{{ selectedRequest.current_contract?.client_signed_at ? new Date(selectedRequest.current_contract.client_signed_at).toLocaleString() : 'Pending' }}</strong></div>
            <div><span>Primary Staff</span><strong>{{ selectedRequest.assignments?.find((item) => item.is_primary)?.staff?.name || 'Not assigned' }}</strong></div>
          </div>
        </article>

        <article class="panel-card wide-card action-card">
          <div class="panel-head">
            <div>
              <h2>Assign staff members</h2>
              <p class="subtext">Choose one or more staff members. Mark one as the lead owner for this request.</p>
            </div>
          </div>

          <div v-if="detailLoading" class="empty-state">Loading request data…</div>

          <template v-else>
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
                    <span>Lead owner</span>
                  </label>
                </div>
                <div class="tag-row">
                  <span v-for="permission in staff.permission_names?.slice(0, 3) || []" :key="permission" class="soft-tag">{{ permission }}</span>
                </div>
              </label>
            </div>

            <div class="notes-box">
              <span>Assignment notes</span>
              <textarea v-model="assignmentNotes" rows="4" class="admin-textarea" placeholder="Add instructions for the assigned staff members, follow-up expectations, or handover notes."></textarea>
            </div>

            <div class="approve-actions">
              <button class="primary-btn" type="button" :disabled="saving || !selectedStaffIds.length" @click="saveAssignment">
                {{ saving ? 'Saving assignment…' : 'Save assignment' }}
              </button>
            </div>
          </template>
        </article>

        <article class="panel-card wide-card">
          <div class="panel-head"><h2>Questionnaire answers</h2></div>
          <div v-if="selectedRequest.answers?.length" class="qa-list">
            <div v-for="answer in selectedRequest.answers" :key="answer.id" class="qa-item">
              <h3>{{ answer.question?.question_text || 'Question' }}</h3>
              <p>{{ answerText(answer) }}</p>
            </div>
          </div>
          <p v-else class="empty-state">No answers recorded for this request.</p>
        </article>

        <article class="panel-card">
          <div class="panel-head"><h2>Internal comments</h2></div>
          <div v-if="selectedRequest.comments?.length" class="timeline-list">
            <div v-for="comment in selectedRequest.comments" :key="comment.id" class="timeline-item">
              <strong>{{ comment.user?.name || 'System' }}</strong>
              <p>{{ comment.comment_text }}</p>
              <span>{{ new Date(comment.created_at).toLocaleString() }} · {{ comment.visibility }}</span>
            </div>
          </div>
          <p v-else class="empty-state">No follow-up comments yet.</p>
        </article>

        <article class="panel-card">
          <div class="panel-head"><h2>Timeline</h2></div>
          <div v-if="selectedRequest.timeline?.length" class="timeline-list">
            <div v-for="entry in selectedRequest.timeline" :key="entry.id" class="timeline-item">
              <strong>{{ entry.event_title || entry.event_type }}</strong>
              <p>{{ entry.event_description || '—' }}</p>
              <span>{{ new Date(entry.created_at).toLocaleString() }}</span>
            </div>
          </div>
          <p v-else class="empty-state">No timeline events yet.</p>
        </article>
      </div>
    </template>
  </section>
</template>
