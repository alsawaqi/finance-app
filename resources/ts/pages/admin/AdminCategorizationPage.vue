<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import {
  getAdminCategorization,
  type CategorizationFilteredSummary,
  type CategorizedAgent,
  type CategorizedBank,
  type CategorizedClient,
  type CategorizedRequest,
  type CategorizedStaff,
} from '@/services/adminCategorization'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'
import { formatDateTime } from '@/utils/dateTime'
import { formatRequestStatus } from '@/utils/requestStatus'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'

type CategorizationTab = 'agents' | 'staff' | 'clients'

const loading = ref(true)
const errorMessage = ref('')
const activeTab = ref<CategorizationTab>('agents')
const selectedBankId = ref<number | null>(null)
const selectedAgentId = ref<number | null>(null)
const bankSearch = ref('')
const agentSearch = ref('')

const summary = ref({
  total_requests: 0,
  submitted_requests: 0,
  active_requests: 0,
  completed_requests: 0,
  total_clients: 0,
  total_staff: 0,
  total_agents: 0,
  with_additional_document_requests: 0,
})
const signals = ref({
  agents_with_traffic: 0,
  staff_with_assignments: 0,
  clients_needing_action: 0,
})
const filteredSummary = ref<CategorizationFilteredSummary>({
  total_requests: 0,
  total_emails: 0,
  unique_agents: 0,
  unique_banks: 0,
  latest_email_at: null,
})
const statusBreakdown = ref<Record<string, number>>({})
const stageBreakdown = ref<Record<string, number>>({})
const bankBreakdown = ref<CategorizedBank[]>([])
const explorerAgents = ref<CategorizedAgent[]>([])
const relatedRequests = ref<CategorizedRequest[]>([])
const agents = ref<CategorizedAgent[]>([])
const staff = ref<CategorizedStaff[]>([])
const clients = ref<CategorizedClient[]>([])
const tabPagination = ref<Record<CategorizationTab, PaginationMeta>>({
  agents: { ...DEFAULT_PAGINATION, per_page: 12 },
  staff: { ...DEFAULT_PAGINATION, per_page: 12 },
  clients: { ...DEFAULT_PAGINATION, per_page: 12 },
})
const requestPagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 8 })
const { t, locale } = useI18n()

const currentPagination = computed(() => tabPagination.value[activeTab.value])
const activeBank = computed(() => bankBreakdown.value.find((bank) => bank.id === selectedBankId.value) ?? null)
const activeAgent = computed(() => explorerAgents.value.find((agent) => agent.id === selectedAgentId.value) ?? null)

const statCards = computed(() => [
  { label: 'Total Requests', value: summary.value.total_requests, tone: 'emerald' },
  { label: 'Clients', value: summary.value.total_clients, tone: 'blue' },
  { label: 'Staff', value: summary.value.total_staff, tone: 'violet' },
  { label: 'Bank Agents', value: summary.value.total_agents, tone: 'amber' },
])

const focusedStats = computed(() => [
  { label: 'Requests in view', value: filteredSummary.value.total_requests },
  { label: 'Email records sent', value: filteredSummary.value.total_emails },
  { label: 'Agents involved', value: filteredSummary.value.unique_agents },
  { label: 'Banks involved', value: filteredSummary.value.unique_banks },
])

const filteredBanks = computed(() => {
  const query = bankSearch.value.trim().toLowerCase()

  if (!query) {
    return bankBreakdown.value
  }

  return bankBreakdown.value.filter((bank) =>
    [bank.name, bank.short_name, bank.code]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query)),
  )
})

const filteredAgents = computed(() => {
  const query = agentSearch.value.trim().toLowerCase()

  if (!query) {
    return explorerAgents.value
  }

  return explorerAgents.value.filter((agent) =>
    [agent.name, agent.email, agent.bank_name, agent.bank_short_name]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query)),
  )
})

const topStatuses = computed(() =>
  Object.entries(statusBreakdown.value)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5)
    .map(([status, count]) => ({
      key: status,
      label: formatRequestStatus(status, locale, t('adminCategorizationPage.states.emptyValue')),
      count,
    })),
)

const topStages = computed(() =>
  Object.entries(stageBreakdown.value)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5)
    .map(([stage, count]) => ({
      key: stage,
      label: getRequestWorkflowStageMeta(stage).label,
      count,
    })),
)

const scopeTitle = computed(() => {
  if (activeAgent.value) {
    return activeAgent.value.name
  }

  if (activeBank.value) {
    return activeBank.value.short_name || activeBank.value.name
  }

  return 'All banks'
})

function setPagination(tab: CategorizationTab, pagination: PaginationMeta | undefined) {
  tabPagination.value[tab] = pagination ?? { ...DEFAULT_PAGINATION, per_page: tabPagination.value[tab].per_page }
}

async function load(page = tabPagination.value[activeTab.value].current_page) {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminCategorization({
      tab: activeTab.value,
      bank_id: selectedBankId.value,
      agent_id: selectedAgentId.value,
      page,
      per_page: tabPagination.value[activeTab.value].per_page,
      request_page: requestPagination.value.current_page,
      request_per_page: requestPagination.value.per_page,
    })

    summary.value = data.summary
    signals.value = data.signals ?? signals.value
    filteredSummary.value = data.filtered_summary ?? filteredSummary.value
    statusBreakdown.value = data.status_breakdown ?? {}
    stageBreakdown.value = data.stage_breakdown ?? {}
    bankBreakdown.value = data.bank_breakdown ?? []
    explorerAgents.value = data.explorer_agents ?? []
    relatedRequests.value = data.related_requests ?? []
    requestPagination.value = data.request_pagination ?? requestPagination.value

    if (data.tab === 'agents') {
      agents.value = data.agents ?? []
      setPagination('agents', data.pagination)
    } else if (data.tab === 'staff') {
      staff.value = data.staff ?? []
      setPagination('staff', data.pagination)
    } else {
      clients.value = data.clients ?? []
      setPagination('clients', data.pagination)
    }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminCategorizationPage.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function selectBank(bankId: number | null) {
  selectedBankId.value = bankId
  selectedAgentId.value = null
  requestPagination.value = { ...requestPagination.value, current_page: 1 }
  load(currentPagination.value.current_page)
}

function selectAgent(agentId: number | null) {
  selectedAgentId.value = agentId
  requestPagination.value = { ...requestPagination.value, current_page: 1 }
  load(currentPagination.value.current_page)
}

function changeRequestPage(page: number) {
  requestPagination.value = { ...requestPagination.value, current_page: page }
  load(currentPagination.value.current_page)
}

function resetExplorer() {
  selectedBankId.value = null
  selectedAgentId.value = null
  bankSearch.value = ''
  agentSearch.value = ''
  requestPagination.value = { ...requestPagination.value, current_page: 1 }
  load(currentPagination.value.current_page)
}

function dateText(value?: string | null) {
  return formatDateTime(value, locale, t('adminCategorizationPage.states.emptyValue'))
}

function numberText(value?: number | null) {
  return new Intl.NumberFormat(locale.value === 'ar' ? 'ar' : 'en').format(value ?? 0)
}

function stageLabel(stage?: string | null) {
  return getRequestWorkflowStageMeta(stage).label
}

function statusLabel(status?: string | null) {
  return formatRequestStatus(status, locale, t('adminCategorizationPage.states.emptyValue'))
}

watch(
  activeTab,
  () => {
    load(1)
  },
  { immediate: true },
)
</script>

<template>
  <section class="admin-page-shell admin-catalog-page admin-categorization-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">Bank intelligence</p>
        <h4>Bank, Agent & Request Explorer</h4>
        <p class="subtext">Track which banks and agents are attached to sent email records and the exact requests behind them.</p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="load()">Refresh</button>
      </div>
    </div>

    <div class="admin-question-stats-grid admin-reveal-up">
      <article v-for="stat in statCards" :key="stat.label" class="admin-question-stat" :class="`tone-${stat.tone}`">
        <strong>{{ numberText(stat.value) }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </div>

    <div v-if="loading" class="panel-card empty-state">{{ t('adminCategorizationPage.states.loading') }}</div>
    <div v-else-if="errorMessage" class="panel-card error-state">{{ errorMessage }}</div>

    <template v-else>
      <section class="catalog-intelligence-grid">
        <article class="panel-card catalog-bank-panel">
          <div class="panel-head">
            <div>
              <span class="eyebrow">Step 1</span>
              <h2>Filter by bank</h2>
            </div>
            <button type="button" class="ghost-btn" @click="selectBank(null)">All banks</button>
          </div>

          <input v-model="bankSearch" class="admin-input" type="search" placeholder="Search bank name or code" />

          <div class="catalog-bank-list">
            <button
              v-for="bank in filteredBanks"
              :key="bank.id"
              type="button"
              class="catalog-bank-card"
              :class="{ 'is-active': selectedBankId === bank.id }"
              @click="selectBank(bank.id)"
            >
              <span>
                <strong>{{ bank.short_name || bank.name }}</strong>
                <small>{{ bank.name }}</small>
              </span>
              <span class="catalog-card-metrics">
                <b>{{ numberText(bank.agents_count) }}</b><small>agents</small>
                <b>{{ numberText(bank.emails_count) }}</b><small>emails</small>
                <b>{{ numberText(bank.requests_count) }}</b><small>requests</small>
              </span>
            </button>
          </div>
        </article>

        <article class="panel-card catalog-agent-panel">
          <div class="panel-head">
            <div>
              <span class="eyebrow">Step 2</span>
              <h2>{{ activeBank ? `${activeBank.short_name || activeBank.name} agents` : 'Agents across all banks' }}</h2>
            </div>
            <button v-if="selectedAgentId" type="button" class="ghost-btn" @click="selectAgent(null)">Clear agent</button>
          </div>

          <input v-model="agentSearch" class="admin-input" type="search" placeholder="Search agent or email" />

          <div v-if="filteredAgents.length" class="catalog-agent-grid">
            <button
              v-for="agent in filteredAgents"
              :key="agent.id"
              type="button"
              class="catalog-agent-card"
              :class="{ 'is-active': selectedAgentId === agent.id }"
              @click="selectAgent(agent.id)"
            >
              <span class="catalog-avatar">{{ agent.name.slice(0, 2).toUpperCase() }}</span>
              <span class="catalog-agent-card__body">
                <strong>{{ agent.name }}</strong>
                <small>{{ agent.email || 'No email saved' }}</small>
                <small>{{ agent.bank_short_name || agent.bank_name || 'No bank linked' }}</small>
              </span>
              <span class="catalog-agent-card__stats">
                <b>{{ numberText(agent.emails_count) }}</b><small>emails</small>
                <b>{{ numberText(agent.requests_count) }}</b><small>requests</small>
              </span>
            </button>
          </div>
          <p v-else class="empty-state">No agents match this bank or search.</p>
        </article>

        <article class="panel-card catalog-focus-panel">
          <div class="panel-head">
            <div>
              <span class="eyebrow">Current view</span>
              <h2>{{ scopeTitle }}</h2>
              <p class="subtext">{{ activeAgent?.email || activeBank?.name || 'All linked bank email traffic' }}</p>
            </div>
            <button type="button" class="ghost-btn" @click="resetExplorer">Reset</button>
          </div>

          <div class="catalog-focus-metrics">
            <div v-for="item in focusedStats" :key="item.label">
              <span>{{ item.label }}</span>
              <strong>{{ numberText(item.value) }}</strong>
            </div>
          </div>

          <div class="catalog-signal-row">
            <span class="soft-tag">{{ numberText(signals.agents_with_traffic) }} agents with traffic</span>
            <span class="soft-tag">{{ numberText(signals.staff_with_assignments) }} staff with workload</span>
            <span class="soft-tag">{{ numberText(signals.clients_needing_action) }} clients needing action</span>
          </div>

          <div class="catalog-mini-section">
            <h3>Request status mix</h3>
            <div class="catalog-chip-grid">
              <span v-for="entry in topStatuses" :key="entry.key" class="soft-tag">{{ entry.label }} · {{ numberText(entry.count) }}</span>
            </div>
          </div>

          <div class="catalog-mini-section">
            <h3>Workflow stage mix</h3>
            <div class="catalog-chip-grid">
              <span v-for="entry in topStages" :key="entry.key" class="soft-tag">{{ entry.label }} · {{ numberText(entry.count) }}</span>
            </div>
          </div>
        </article>
      </section>

      <article class="panel-card catalog-request-panel">
        <div class="panel-head">
          <div>
            <span class="eyebrow">Exact records</span>
            <h2>Requests linked to {{ scopeTitle }}</h2>
            <p class="subtext">The request numbers below are attached through sent email records to the selected bank or agent.</p>
          </div>
          <span class="soft-tag">{{ numberText(filteredSummary.total_emails) }} email records</span>
        </div>

        <div v-if="relatedRequests.length" class="table-wrap">
          <table class="request-table">
            <thead>
              <tr>
                <th>Request</th>
                <th>Client / Company</th>
                <th>Matched bank agents</th>
                <th>Status</th>
                <th>Email records</th>
                <th>Latest email</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="request in relatedRequests" :key="request.id">
                <td>
                  <RouterLink :to="{ name: 'admin-request-details', params: { id: request.id } }" class="catalog-record-link">
                    {{ request.reference_number }}
                  </RouterLink>
                  <div v-if="request.approval_reference_number" class="muted-small">{{ request.approval_reference_number }}</div>
                </td>
                <td>
                  <strong>{{ request.client?.name || 'Unknown client' }}</strong>
                  <div class="muted-small">{{ request.company_name || request.client?.email || 'No company saved' }}</div>
                </td>
                <td>
                  <div class="catalog-inline-chips">
                    <span v-for="agent in request.agents" :key="agent.id" class="soft-tag">
                      {{ agent.name }}
                    </span>
                  </div>
                </td>
                <td>
                  <strong>{{ statusLabel(request.status) }}</strong>
                  <div class="muted-small">{{ stageLabel(request.workflow_stage) }}</div>
                </td>
                <td>
                  <strong>{{ numberText(request.matched_emails_count) }}</strong>
                  <div class="muted-small">{{ numberText(request.emails_count) }} total on request</div>
                </td>
                <td>{{ dateText(request.latest_email_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="empty-state">No requests are linked to this bank or agent yet.</p>

        <AppPagination :pagination="requestPagination" :disabled="loading" @change="changeRequestPage" />
      </article>

      <article class="panel-card catalog-panel">
        <div class="panel-head">
          <div>
            <span class="eyebrow">Directory</span>
            <h2>Agents, staff, and clients</h2>
          </div>
        </div>

        <div class="catalog-tabbar">
          <button type="button" class="catalog-tab" :class="{ 'is-active': activeTab === 'agents' }" @click="activeTab = 'agents'">
            Agents
          </button>
          <button type="button" class="catalog-tab" :class="{ 'is-active': activeTab === 'staff' }" @click="activeTab = 'staff'">
            Staff
          </button>
          <button type="button" class="catalog-tab" :class="{ 'is-active': activeTab === 'clients' }" @click="activeTab = 'clients'">
            Clients
          </button>
        </div>

        <div v-if="activeTab === 'agents'" class="table-wrap">
          <table class="request-table">
            <thead>
              <tr>
                <th>Agent</th>
                <th>Bank</th>
                <th>Email Records</th>
                <th>Requests Touched</th>
                <th>Last Contact</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="agent in agents" :key="agent.id">
                <td>
                  <strong>{{ agent.name }}</strong>
                  <div class="muted-small">{{ agent.email || 'No email saved' }}</div>
                </td>
                <td>{{ agent.bank_name || 'No bank linked' }}</td>
                <td>{{ numberText(agent.emails_count) }}</td>
                <td>{{ numberText(agent.requests_count) }}</td>
                <td>{{ dateText(agent.last_contact_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else-if="activeTab === 'staff'" class="table-wrap">
          <table class="request-table">
            <thead>
              <tr>
                <th>Staff</th>
                <th>Active Assignments</th>
                <th>Lead Requests</th>
                <th>Comments</th>
                <th>Permissions</th>
                <th>Last Assignment</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in staff" :key="member.id">
                <td>
                  <strong>{{ member.name }}</strong>
                  <div class="muted-small">{{ member.email }}</div>
                </td>
                <td>{{ numberText(member.active_assignments_count) }}</td>
                <td>{{ numberText(member.lead_requests_count) }}</td>
                <td>{{ numberText(member.comments_count) }}</td>
                <td>
                  <div class="catalog-inline-chips">
                    <span v-for="permission in member.permission_names.slice(0, 3)" :key="permission" class="soft-tag">{{ permission }}</span>
                    <span v-if="member.permission_names.length > 3" class="muted-small">+{{ member.permission_names.length - 3 }} more</span>
                  </div>
                </td>
                <td>{{ dateText(member.last_assigned_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="table-wrap">
          <table class="request-table">
            <thead>
              <tr>
                <th>Client</th>
                <th>Total Requests</th>
                <th>Active Requests</th>
                <th>Needs Action</th>
                <th>Last Request</th>
                <th>Last Login</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="client in clients" :key="client.id">
                <td>
                  <strong>{{ client.name }}</strong>
                  <div class="muted-small">{{ client.email }}</div>
                </td>
                <td>{{ numberText(client.requests_count) }}</td>
                <td>{{ numberText(client.active_requests_count) }}</td>
                <td>{{ numberText(client.needs_action_count) }}</td>
                <td>{{ dateText(client.last_request_at) }}</td>
                <td>{{ dateText(client.last_login_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <AppPagination :pagination="currentPagination" :disabled="loading" @change="load" />
      </article>
    </template>
  </section>
</template>
