<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getAdminRequestFilterData,
  type FilterAgentOption,
  type FilterBankOption,
  type FilteredRequestItem,
  type FilterStaffOption,
} from '@/services/adminRequestFiltering'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<FilteredRequestItem[]>([])
const statuses = ref<Array<{ value: string; label: string }>>([])
const staffOptions = ref<FilterStaffOption[]>([])
const bankOptions = ref<FilterBankOption[]>([])
const agentOptions = ref<FilterAgentOption[]>([])
const summary = ref({ total_requests: 0, unique_clients: 0, unique_staff: 0, unique_agents: 0 })

const selectedStatus = ref('')
const selectedStaffId = ref<number | ''>('')
const selectedBankId = ref<number | ''>('')
const selectedAgentId = ref<number | ''>('')
const { t } = useI18n()

const usingStaffFilter = computed(() => selectedStaffId.value !== '')
const usingBankAgentFilter = computed(() => selectedBankId.value !== '' || selectedAgentId.value !== '')
const filteredAgents = computed(() => {
  if (selectedBankId.value === '') {
    return []
  }

  return agentOptions.value.filter((agent) => agent.bank_id === Number(selectedBankId.value))
})

const statCards = computed(() => [
  { label: t('adminRequestFiltering.stats.filteredRequests'), value: summary.value.total_requests, tone: 'emerald' },
  { label: t('adminRequestFiltering.stats.clientsInResult'), value: summary.value.unique_clients, tone: 'blue' },
  { label: t('adminRequestFiltering.stats.staffInResult'), value: summary.value.unique_staff, tone: 'violet' },
  { label: t('adminRequestFiltering.stats.agentsInResult'), value: summary.value.unique_agents, tone: 'amber' },
])

watch(selectedStaffId, (value) => {
  if (value !== '') {
    selectedBankId.value = ''
    selectedAgentId.value = ''
  }
})

watch(selectedBankId, (value) => {
  if (value !== '') {
    selectedStaffId.value = ''
  }

  if (value === '' || !filteredAgents.value.some((agent) => agent.id === Number(selectedAgentId.value))) {
    selectedAgentId.value = ''
  }
})

watch(selectedAgentId, (value) => {
  if (value !== '') {
    selectedStaffId.value = ''
  }
})

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminRequestFilterData({
      status: selectedStatus.value || undefined,
      staff_id: selectedStaffId.value === '' ? undefined : Number(selectedStaffId.value),
      bank_id: selectedBankId.value === '' ? undefined : Number(selectedBankId.value),
      agent_id: selectedAgentId.value === '' ? undefined : Number(selectedAgentId.value),
    })

    statuses.value = data.filters?.statuses ?? []
    staffOptions.value = data.filters?.staff ?? []
    bankOptions.value = data.filters?.banks ?? []
    agentOptions.value = data.filters?.agents ?? []
    summary.value = data.summary ?? summary.value
    requests.value = data.requests ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestFiltering.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  selectedStatus.value = ''
  selectedStaffId.value = ''
  selectedBankId.value = ''
  selectedAgentId.value = ''
  load()
}

function dateText(value?: string | null) {
  return value ? new Date(value).toLocaleString() : t('adminRequestFiltering.states.emptyValue')
}

function staffPreview(item: FilteredRequestItem) {
  const staff = item.active_staff ?? []

  if (!staff.length) {
    return t('adminRequestFiltering.states.notAssigned')
  }

  return staff.map((entry) => entry.name).join(', ')
}

function agentPreview(item: FilteredRequestItem) {
  const agents = item.agents ?? []

  if (!agents.length) {
    return t('adminRequestFiltering.states.noAgentLinked')
  }

  return agents.slice(0, 2).map((entry) => entry.name).join(', ')
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminRequestFiltering.hero.eyebrow') }}</p>
        <h1>{{ t('adminRequestFiltering.hero.title') }}</h1>
        <p class="subtext">
          {{ t('adminRequestFiltering.hero.subtitle') }}
        </p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="resetFilters">{{ t('adminRequestFiltering.actions.reset') }}</button>
        <button class="primary-btn" type="button" @click="load">{{ t('adminRequestFiltering.actions.applyFilters') }}</button>
      </div>
    </div>

    <div class="admin-question-stats-grid admin-reveal-up">
      <article v-for="stat in statCards" :key="stat.label" class="admin-question-stat" :class="`tone-${stat.tone}`">
        <strong>{{ stat.value }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </div>

    <article class="panel-card">
      <div class="panel-head">
        <div>
          <h2>{{ t('adminRequestFiltering.filters.title') }}</h2>
          <p class="subtext">{{ t('adminRequestFiltering.filters.subtitle') }}</p>
        </div>
      </div>

      <div class="filter-bar filter-bar--dense">
        <div class="field-block">
          <span>{{ t('adminRequestFiltering.filters.status') }}</span>
          <select v-model="selectedStatus" class="admin-select">
            <option value="">{{ t('adminRequestFiltering.filters.allStatuses') }}</option>
            <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
          </select>
        </div>

        <div class="field-block">
          <span>{{ t('adminRequestFiltering.filters.staffMember') }}</span>
          <select v-model="selectedStaffId" class="admin-select" :disabled="usingBankAgentFilter">
            <option value="">{{ t('adminRequestFiltering.filters.allStaff') }}</option>
            <option v-for="staff in staffOptions" :key="staff.id" :value="staff.id">{{ staff.name }}{{ staff.email ? ` · ${staff.email}` : '' }}</option>
          </select>
        </div>

        <div class="field-block">
          <span>{{ t('adminRequestFiltering.filters.bank') }}</span>
          <select v-model="selectedBankId" class="admin-select" :disabled="usingStaffFilter">
            <option value="">{{ t('adminRequestFiltering.filters.allBanks') }}</option>
            <option v-for="bank in bankOptions" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
          </select>
        </div>

        <div class="field-block">
          <span>{{ t('adminRequestFiltering.filters.agent') }}</span>
          <select v-model="selectedAgentId" class="admin-select" :disabled="usingStaffFilter || selectedBankId === ''">
            <option value="">{{ t('adminRequestFiltering.filters.allAgentsForBank') }}</option>
            <option v-for="agent in filteredAgents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
          </select>
        </div>
      </div>

      <div class="selection-card">
        <strong>{{ t('adminRequestFiltering.logic.title') }}</strong>
        <p>
          <template v-if="usingStaffFilter">
            {{ t('adminRequestFiltering.logic.staffMode') }}
          </template>
          <template v-else-if="usingBankAgentFilter">
            {{ t('adminRequestFiltering.logic.bankAgentMode') }}
          </template>
          <template v-else>
            {{ t('adminRequestFiltering.logic.neutralMode') }}
          </template>
        </p>
      </div>
    </article>

    <article class="panel-card">
      <div class="panel-head">
        <h2>{{ t('adminRequestFiltering.table.title') }}</h2>
        <span class="count-pill">{{ t('adminRequestFiltering.table.count', { count: requests.length }) }}</span>
      </div>

      <p v-if="loading" class="empty-state">{{ t('adminRequestFiltering.states.loading') }}</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!requests.length" class="empty-state">{{ t('adminRequestFiltering.states.empty') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminRequestFiltering.table.request') }}</th>
              <th>{{ t('adminRequestFiltering.table.client') }}</th>
              <th>{{ t('adminRequestFiltering.table.assignedStaff') }}</th>
              <th>{{ t('adminRequestFiltering.table.bankAgent') }}</th>
              <th>{{ t('adminRequestFiltering.table.emailRecords') }}</th>
              <th>{{ t('adminRequestFiltering.table.lastActivity') }}</th>
              <th>{{ t('adminRequestFiltering.table.status') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests" :key="item.id">
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || t('adminRequestFiltering.states.awaitingApprovalRef') }}</div>
              </td>
              <td>
                <strong>{{ item.client?.name || t('adminRequestFiltering.states.clientFallback') }}</strong>
                <div class="muted-small">{{ item.client?.email || t('adminRequestFiltering.states.emptyValue') }}</div>
              </td>
              <td>
                <div>{{ staffPreview(item) }}</div>
                <div class="muted-small">{{ t('adminRequestFiltering.table.primary') }}: {{ item.primary_staff?.name || t('adminRequestFiltering.states.notAssigned') }}</div>
              </td>
              <td>
                <div>{{ item.agents?.[0]?.bank_name || t('adminRequestFiltering.states.noBankLinked') }}</div>
                <div class="muted-small">{{ agentPreview(item) }}</div>
              </td>
              <td>
                <div>{{ item.emails_count }}</div>
                <div class="muted-small">{{ t('adminRequestFiltering.table.lastEmail') }}: {{ dateText(item.latest_email_at) }}</div>
              </td>
              <td>{{ dateText(item.latest_activity_at || item.submitted_at) }}</td>
              <td>
                <span class="status-badge">{{ item.status }}</span>
                <div class="muted-small">{{ item.workflow_stage }}</div>
              </td>
              <td>
                <RouterLink :to="{ name: 'admin-request-details', params: { id: item.id } }" class="primary-btn small-btn">{{ t('adminRequestFiltering.actions.view') }}</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</template>
