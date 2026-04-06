<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import {
  getAdminRequestFilterData,
  type FilterAgentOption,
  type FilterBankOption,
  type FilterBreakdownAgent,
  type FilterBreakdownBank,
  type FilteredRequestItem,
  type FilterStaffOption,
} from '@/services/adminRequestFiltering'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'
import { intakeCompanyName } from '@/utils/requestIntake'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'
import { formatDateTime } from '@/utils/dateTime'
import { formatRequestStatus } from '@/utils/requestStatus'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<FilteredRequestItem[]>([])
const pagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 15 })
const stages = ref<Array<{ value: string; label: string }>>([])
const staffOptions = ref<FilterStaffOption[]>([])
const bankOptions = ref<FilterBankOption[]>([])
const agentOptions = ref<FilterAgentOption[]>([])
const bankBreakdown = ref<FilterBreakdownBank[]>([])
const agentBreakdown = ref<FilterBreakdownAgent[]>([])
const summary = ref({ total_requests: 0, unique_clients: 0, unique_staff: 0, unique_agents: 0, total_emails: 0 })

const selectedStage = ref('')
const selectedStaffId = ref<number | ''>('')
const selectedBankId = ref<number | ''>('')
const selectedAgentId = ref<number | ''>('')
const { t, locale } = useI18n()
const router = useRouter()

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

const usingStaffFilter = computed(() => selectedStaffId.value !== '')
const usingBankAgentFilter = computed(() => selectedBankId.value !== '' || selectedAgentId.value !== '')
const filteredAgents = computed(() => {
  if (selectedBankId.value === '') {
    return []
  }

  return agentOptions.value.filter((agent) => agent.bank_id === Number(selectedBankId.value))
})

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

const statCards = computed(() => [
  { label: t('adminRequestFiltering.stats.filteredRequests'), value: summary.value.total_requests, tone: 'emerald' },
  { label: t('adminRequestFiltering.stats.clientsInResult'), value: summary.value.unique_clients, tone: 'blue' },
  { label: t('adminRequestFiltering.stats.staffInResult'), value: summary.value.unique_staff, tone: 'violet' },
  { label: t('adminRequestFiltering.stats.agentsInResult'), value: summary.value.unique_agents, tone: 'amber' },
  { label: uiText('Emails in result', 'الرسائل ضمن النتائج'), value: summary.value.total_emails, tone: 'slate' },
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

async function load(page = pagination.value.current_page) {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminRequestFilterData({
      stage: selectedStage.value || undefined,
      staff_id: selectedStaffId.value === '' ? undefined : Number(selectedStaffId.value),
      bank_id: selectedBankId.value === '' ? undefined : Number(selectedBankId.value),
      agent_id: selectedAgentId.value === '' ? undefined : Number(selectedAgentId.value),
      page,
      per_page: pagination.value.per_page,
    })

    stages.value = data.filters?.stages ?? []
    staffOptions.value = data.filters?.staff ?? []
    bankOptions.value = data.filters?.banks ?? []
    agentOptions.value = data.filters?.agents ?? []
    summary.value = data.summary ?? summary.value
    bankBreakdown.value = data.bank_breakdown ?? []
    agentBreakdown.value = data.agent_breakdown ?? []
    requests.value = data.requests ?? []
    pagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: pagination.value.per_page }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminRequestFiltering.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  load(1)
}

function applyBreakdownBank(bank: FilterBreakdownBank) {
  selectedStaffId.value = ''
  selectedBankId.value = bank.id
  selectedAgentId.value = ''
  load(1)
}

function applyBreakdownAgent(agent: FilterBreakdownAgent) {
  selectedStaffId.value = ''
  let bankId: number | '' = ''
  if (agent.bank_id != null) {
    bankId = agent.bank_id
  } else if (agent.bank_name) {
    const match = bankOptions.value.find((b) => b.name === agent.bank_name)
    if (match) bankId = match.id
  }
  selectedBankId.value = bankId
  selectedAgentId.value = bankId !== '' ? agent.id : ''
  load(1)
}

function goToRequestDetail(id: number) {
  router.push({ name: 'admin-request-details', params: { id: String(id) } })
}

function resetFilters() {
  selectedStage.value = ''
  selectedStaffId.value = ''
  selectedBankId.value = ''
  selectedAgentId.value = ''
  load(1)
}

function dateText(value?: string | null) {
  return formatDateTime(value, locale, t('adminRequestFiltering.states.emptyValue'))
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

function companyName(item: FilteredRequestItem) {
  return item.company_name || intakeCompanyName(item.intake_details_json, t('adminRequestFiltering.states.emptyValue'))
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminRequestFiltering.hero.eyebrow') }}</p>
        <h4>{{ t('adminRequestFiltering.hero.title') }}</h4>
        <p class="subtext">
          {{ t('adminRequestFiltering.hero.subtitle') }}
        </p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="resetFilters">{{ t('adminRequestFiltering.actions.reset') }}</button>
        <button class="primary-btn" type="button" @click="applyFilters">{{ t('adminRequestFiltering.actions.applyFilters') }}</button>
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
          <span>{{ t('adminRequestFiltering.filters.stage') }}</span>
          <select v-model="selectedStage" class="admin-select">
            <option value="">{{ t('adminRequestFiltering.filters.allStages') }}</option>
            <option v-for="stage in stages" :key="stage.value" :value="stage.value">{{ stageMeta(stage.value).label }}</option>
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

    <div class="admin-dashboard-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
      <article class="panel-card">
        <div class="panel-head">
          <h2>{{ uiText('Bank traffic breakdown', 'تفصيل حركة البنوك') }}</h2>
          <span class="count-pill">{{ bankBreakdown.length }}</span>
        </div>

        <p v-if="!bankBreakdown.length" class="empty-state">{{ uiText('No bank activity found for the current filter.', 'لا توجد حركة بنكية ضمن الفلتر الحالي.') }}</p>
        <div v-else class="table-wrap">
          <table class="request-table">
            <thead>
              <tr>
                <th>{{ uiText('Bank', 'البنك') }}</th>
                <th>{{ uiText('Agents', 'الوكلاء') }}</th>
                <th>{{ uiText('Requests', 'الطلبات') }}</th>
                <th>{{ uiText('Emails', 'الرسائل') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="bank in bankBreakdown.slice(0, 8)"
                :key="bank.id"
                class="is-clickable-row"
                role="button"
                tabindex="0"
                @click="applyBreakdownBank(bank)"
                @keydown.enter.prevent="applyBreakdownBank(bank)"
                @keydown.space.prevent="applyBreakdownBank(bank)"
              >
                <td>
                  <strong>{{ bank.short_name || bank.name }}</strong>
                  <div class="muted-small">{{ bank.name }}</div>
                </td>
                <td>{{ bank.agents_count }}</td>
                <td>{{ bank.requests_count }}</td>
                <td>{{ bank.emails_count }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>

      <article class="panel-card">
        <div class="panel-head">
          <h2>{{ uiText('Agent traffic breakdown', 'تفصيل حركة الوكلاء') }}</h2>
          <span class="count-pill">{{ agentBreakdown.length }}</span>
        </div>

        <p v-if="!agentBreakdown.length" class="empty-state">{{ uiText('No agent activity found for the current filter.', 'لا توجد حركة وكلاء ضمن الفلتر الحالي.') }}</p>
        <div v-else class="table-wrap">
          <table class="request-table">
            <thead>
              <tr>
                <th>{{ uiText('Agent', 'الوكيل') }}</th>
                <th>{{ uiText('Bank', 'البنك') }}</th>
                <th>{{ uiText('Requests', 'الطلبات') }}</th>
                <th>{{ uiText('Emails', 'الرسائل') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="agent in agentBreakdown.slice(0, 8)"
                :key="agent.id"
                class="is-clickable-row"
                role="button"
                tabindex="0"
                @click="applyBreakdownAgent(agent)"
                @keydown.enter.prevent="applyBreakdownAgent(agent)"
                @keydown.space.prevent="applyBreakdownAgent(agent)"
              >
                <td>
                  <strong>{{ agent.name }}</strong>
                  <div class="muted-small">{{ agent.email || t('adminRequestFiltering.states.emptyValue') }}</div>
                </td>
                <td>{{ agent.bank_short_name || agent.bank_name || t('adminRequestFiltering.states.emptyValue') }}</td>
                <td>{{ agent.requests_count }}</td>
                <td>{{ agent.emails_count }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </div>

    <article class="panel-card">
      <div class="panel-head">
        <h2>{{ t('adminRequestFiltering.table.title') }}</h2>
        <span class="count-pill">{{ t('adminRequestFiltering.table.count', { count: pagination.total }) }}</span>
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
              <th>{{ t('adminRequestFiltering.table.company') }}</th>
              <th>{{ t('adminRequestFiltering.table.assignedStaff') }}</th>
              <th>{{ t('adminRequestFiltering.table.bankAgent') }}</th>
              <th>{{ t('adminRequestFiltering.table.emailRecords') }}</th>
              <th>{{ t('adminRequestFiltering.table.lastActivity') }}</th>
              <th>{{ t('adminRequestFiltering.table.status') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in requests"
              :key="item.id"
              class="is-clickable-row"
              role="button"
              tabindex="0"
              @click="goToRequestDetail(item.id)"
              @keydown.enter.prevent="goToRequestDetail(item.id)"
              @keydown.space.prevent="goToRequestDetail(item.id)"
            >
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || t('adminRequestFiltering.states.awaitingApprovalRef') }}</div>
              </td>
              <td>
                <strong>{{ item.client?.name || t('adminRequestFiltering.states.clientFallback') }}</strong>
                <div class="muted-small">{{ item.client?.email || t('adminRequestFiltering.states.emptyValue') }}</div>
              </td>
              <td>{{ companyName(item) }}</td>
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
                <span class="status-badge">{{ formatRequestStatus(item.status, locale, t('adminRequestFiltering.states.emptyValue')) }}</span>
                <div class="muted-small">{{ stageMeta(item.workflow_stage).label }}</div>
              </td>
              <td @click.stop>
                <RouterLink :to="{ name: 'admin-request-details', params: { id: item.id } }" class="primary-btn small-btn">{{ t('adminRequestFiltering.actions.view') }}</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination :pagination="pagination" :disabled="loading" @change="load" />
    </article>
  </section>
</template>
