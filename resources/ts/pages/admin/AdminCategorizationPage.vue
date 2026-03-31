<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAdminCategorization, type CategorizedAgent, type CategorizedClient, type CategorizedStaff } from '@/services/adminCategorization'

const loading = ref(true)
const errorMessage = ref('')
const activeTab = ref<'agents' | 'staff' | 'clients'>('agents')

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
const statusBreakdown = ref<Record<string, number>>({})
const stageBreakdown = ref<Record<string, number>>({})
const agents = ref<CategorizedAgent[]>([])
const staff = ref<CategorizedStaff[]>([])
const clients = ref<CategorizedClient[]>([])
const { t } = useI18n()

const statCards = computed(() => [
  { label: t('adminCategorizationPage.stats.totalRequests'), value: summary.value.total_requests, tone: 'emerald' },
  { label: t('adminCategorizationPage.stats.clients'), value: summary.value.total_clients, tone: 'blue' },
  { label: t('adminCategorizationPage.stats.staff'), value: summary.value.total_staff, tone: 'violet' },
  { label: t('adminCategorizationPage.stats.agents'), value: summary.value.total_agents, tone: 'amber' },
])

const topStatuses = computed(() => Object.entries(statusBreakdown.value).sort((a, b) => b[1] - a[1]).slice(0, 6))
const topStages = computed(() => Object.entries(stageBreakdown.value).sort((a, b) => b[1] - a[1]).slice(0, 6))
const agentsWithTraffic = computed(() => agents.value.filter((item) => item.emails_count > 0).length)
const staffWithAssignments = computed(() => staff.value.filter((item) => item.active_assignments_count > 0).length)
const clientsNeedingAction = computed(() => clients.value.filter((item) => item.needs_action_count > 0).length)

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminCategorization()
    summary.value = data.summary
    statusBreakdown.value = data.status_breakdown ?? {}
    stageBreakdown.value = data.stage_breakdown ?? {}
    agents.value = data.agents ?? []
    staff.value = data.staff ?? []
    clients.value = data.clients ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminCategorizationPage.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function dateText(value?: string | null) {
  return value ? new Date(value).toLocaleString() : t('adminCategorizationPage.states.emptyValue')
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-catalog-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminCategorizationPage.hero.eyebrow') }}</p>
        <h1>{{ t('adminCategorizationPage.hero.title') }}</h1>
        <p class="subtext">
          {{ t('adminCategorizationPage.hero.subtitle') }}
        </p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="load">{{ t('adminCategorizationPage.actions.refresh') }}</button>
      </div>
    </div>

    <div class="admin-question-stats-grid admin-reveal-up">
      <article v-for="stat in statCards" :key="stat.label" class="admin-question-stat" :class="`tone-${stat.tone}`">
        <strong>{{ stat.value }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </div>

    <div class="catalog-top-grid">
      <article class="panel-card catalog-highlight-card">
        <div class="panel-head">
          <h2>{{ t('adminCategorizationPage.sections.operationalHighlights') }}</h2>
        </div>
        <div class="catalog-mini-stats">
          <div>
            <span>{{ t('adminCategorizationPage.kpi.submitted') }}</span>
            <strong>{{ summary.submitted_requests }}</strong>
          </div>
          <div>
            <span>{{ t('adminCategorizationPage.kpi.active') }}</span>
            <strong>{{ summary.active_requests }}</strong>
          </div>
          <div>
            <span>{{ t('adminCategorizationPage.kpi.completed') }}</span>
            <strong>{{ summary.completed_requests }}</strong>
          </div>
          <div>
            <span>{{ t('adminCategorizationPage.kpi.additionalDocs') }}</span>
            <strong>{{ summary.with_additional_document_requests }}</strong>
          </div>
        </div>
      </article>

      <article class="panel-card catalog-highlight-card">
        <div class="panel-head">
          <h2>{{ t('adminCategorizationPage.sections.quickSignals') }}</h2>
        </div>
        <div class="catalog-mini-stats">
          <div>
            <span>{{ t('adminCategorizationPage.kpi.agentsWithTraffic') }}</span>
            <strong>{{ agentsWithTraffic }}</strong>
          </div>
          <div>
            <span>{{ t('adminCategorizationPage.kpi.staffWithWorkload') }}</span>
            <strong>{{ staffWithAssignments }}</strong>
          </div>
          <div>
            <span>{{ t('adminCategorizationPage.kpi.clientsNeedingAction') }}</span>
            <strong>{{ clientsNeedingAction }}</strong>
          </div>
        </div>
      </article>

      <article class="panel-card catalog-breakdown-card">
        <div class="panel-head">
          <h2>{{ t('adminCategorizationPage.sections.requestStatusMix') }}</h2>
        </div>
        <div class="catalog-chip-grid">
          <span v-for="entry in topStatuses" :key="entry[0]" class="soft-tag">{{ entry[0] }} · {{ entry[1] }}</span>
        </div>
      </article>

      <article class="panel-card catalog-breakdown-card">
        <div class="panel-head">
          <h2>{{ t('adminCategorizationPage.sections.workflowStageMix') }}</h2>
        </div>
        <div class="catalog-chip-grid">
          <span v-for="entry in topStages" :key="entry[0]" class="soft-tag">{{ entry[0] }} · {{ entry[1] }}</span>
        </div>
      </article>
    </div>

    <div v-if="loading" class="panel-card empty-state">{{ t('adminCategorizationPage.states.loading') }}</div>
    <div v-else-if="errorMessage" class="panel-card error-state">{{ errorMessage }}</div>

    <div v-else class="panel-card catalog-panel">
      <div class="catalog-tabbar">
        <button type="button" class="catalog-tab" :class="{ 'is-active': activeTab === 'agents' }" @click="activeTab = 'agents'">
          {{ t('adminCategorizationPage.tabs.agents') }}
        </button>
        <button type="button" class="catalog-tab" :class="{ 'is-active': activeTab === 'staff' }" @click="activeTab = 'staff'">
          {{ t('adminCategorizationPage.tabs.staff') }}
        </button>
        <button type="button" class="catalog-tab" :class="{ 'is-active': activeTab === 'clients' }" @click="activeTab = 'clients'">
          {{ t('adminCategorizationPage.tabs.clients') }}
        </button>
      </div>

      <div v-if="activeTab === 'agents'" class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminCategorizationPage.tables.agents.agent') }}</th>
              <th>{{ t('adminCategorizationPage.tables.agents.bank') }}</th>
              <th>{{ t('adminCategorizationPage.tables.agents.emailRecords') }}</th>
              <th>{{ t('adminCategorizationPage.tables.agents.requestsTouched') }}</th>
              <th>{{ t('adminCategorizationPage.tables.agents.lastContact') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="agent in agents" :key="agent.id">
              <td>
                <strong>{{ agent.name }}</strong>
                <div class="muted-small">{{ agent.email || t('adminCategorizationPage.states.noEmailSaved') }}</div>
              </td>
              <td>{{ agent.bank_name || t('adminCategorizationPage.states.noBankLinked') }}</td>
              <td>{{ agent.emails_count }}</td>
              <td>{{ agent.requests_count }}</td>
              <td>{{ dateText(agent.last_contact_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="activeTab === 'staff'" class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminCategorizationPage.tables.staff.staff') }}</th>
              <th>{{ t('adminCategorizationPage.tables.staff.activeAssignments') }}</th>
              <th>{{ t('adminCategorizationPage.tables.staff.leadRequests') }}</th>
              <th>{{ t('adminCategorizationPage.tables.staff.comments') }}</th>
              <th>{{ t('adminCategorizationPage.tables.staff.permissions') }}</th>
              <th>{{ t('adminCategorizationPage.tables.staff.lastAssignment') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in staff" :key="member.id">
              <td>
                <strong>{{ member.name }}</strong>
                <div class="muted-small">{{ member.email }}</div>
              </td>
              <td>{{ member.active_assignments_count }}</td>
              <td>{{ member.lead_requests_count }}</td>
              <td>{{ member.comments_count }}</td>
              <td>
                <div class="catalog-inline-chips">
                  <span v-for="permission in member.permission_names.slice(0, 3)" :key="permission" class="soft-tag">{{ permission }}</span>
                  <span v-if="member.permission_names.length > 3" class="muted-small">+{{ member.permission_names.length - 3 }} {{ t('adminCategorizationPage.states.more') }}</span>
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
              <th>{{ t('adminCategorizationPage.tables.clients.client') }}</th>
              <th>{{ t('adminCategorizationPage.tables.clients.totalRequests') }}</th>
              <th>{{ t('adminCategorizationPage.tables.clients.activeRequests') }}</th>
              <th>{{ t('adminCategorizationPage.tables.clients.needsAction') }}</th>
              <th>{{ t('adminCategorizationPage.tables.clients.lastRequest') }}</th>
              <th>{{ t('adminCategorizationPage.tables.clients.lastLogin') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="client in clients" :key="client.id">
              <td>
                <strong>{{ client.name }}</strong>
                <div class="muted-small">{{ client.email }}</div>
              </td>
              <td>{{ client.requests_count }}</td>
              <td>{{ client.active_requests_count }}</td>
              <td>{{ client.needs_action_count }}</td>
              <td>{{ dateText(client.last_request_at) }}</td>
              <td>{{ dateText(client.last_login_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
