<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { getStaffRequests, type StaffWorkspaceRequestSummary } from '@/services/staffWorkspace'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'

const loading = ref(true)
const errorMessage = ref('')
const search = ref('')
const workflowStage = ref('')
const requests = ref<StaffWorkspaceRequestSummary[]>([])

const availableStages = computed(() => ['assigned_to_staff', 'processing', 'ready_for_processing'])

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getStaffRequests({
      search: search.value || undefined,
      workflow_stage: workflowStage.value || undefined,
    })
    requests.value = data.requests ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load assigned requests.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">Staff workspace</p>
        <h1>Assigned Requests</h1>
        <p class="subtext">View every request currently assigned to you, track progress, and open the request workspace for comments and follow-up.</p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="load">Refresh</button>
      </div>
    </div>

    <article class="panel-card">
      <div class="panel-head">
        <div>
          <h2>Filters</h2>
          <p class="subtext">Narrow the queue by request reference, client, or current workflow stage.</p>
        </div>
      </div>

      <div class="filter-bar">
        <div class="field-block">
          <span>Search</span>
          <input v-model="search" type="text" class="admin-input" placeholder="Request reference, approval reference, client name or email" />
        </div>
        <div class="field-block">
          <span>Stage</span>
          <select v-model="workflowStage" class="admin-select">
            <option value="">All stages</option>
            <option v-for="stage in availableStages" :key="stage" :value="stage">{{ stage }}</option>
          </select>
        </div>
        <div class="filter-actions">
          <button class="primary-btn" type="button" @click="load">Apply filters</button>
        </div>
      </div>
    </article>

    <article class="panel-card">
      <div class="panel-head">
        <h2>Assigned request queue</h2>
        <span class="count-pill">{{ requests.length }} requests</span>
      </div>

      <p v-if="loading" class="empty-state">Loading assigned requests…</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!requests.length" class="empty-state">No requests are currently assigned to your workspace.</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>Request</th>
              <th>Client</th>
              <th>Country</th>
              <th>Finance Type</th>
              <th>Comments</th>
              <th>Last Activity</th>
              <th>Stage</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests" :key="item.id">
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || 'Awaiting approval ref' }}</div>
              </td>
              <td>
                <strong>{{ intakeFullName(item.intake_details_json, item.client?.name || 'Client') }}</strong>
                <div class="muted-small">{{ item.client?.email || '—' }}</div>
              </td>
              <td>{{ countryNameFromCode(intakeCountryCode(item.intake_details_json)) }}</td>
              <td>
                <div>{{ intakeFinanceType(item.intake_details_json) }}</div>
                <div class="muted-small">{{ intakeRequestedAmount(item.intake_details_json) }}</div>
              </td>
              <td>{{ item.comments_count || 0 }}</td>
              <td>{{ item.latest_activity_at ? new Date(item.latest_activity_at).toLocaleString() : '—' }}</td>
              <td><span class="status-badge">{{ item.workflow_stage }}</span></td>
              <td>
                <RouterLink :to="{ name: 'staff-request-details', params: { id: item.id } }" class="primary-btn small-btn">View</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</template>
