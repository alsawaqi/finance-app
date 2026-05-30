<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import {
  getAdminClientRequests,
  getAdminClientsOverview,
  type ClientOverviewItem,
  type ClientOverviewRequest,
} from '@/services/adminRequestFiltering'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'
import { formatDateTime } from '@/utils/dateTime'
import { intakeCompanyName } from '@/utils/requestIntake'
import { formatRequestStatus } from '@/utils/requestStatus'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'

const { t, locale } = useI18n()
const router = useRouter()

const loadingClients = ref(true)
const loadingRequests = ref(false)
const errorMessage = ref('')
const requestErrorMessage = ref('')
const search = ref('')
const clients = ref<ClientOverviewItem[]>([])
const selectedClient = ref<ClientOverviewItem | null>(null)
const selectedClientRequests = ref<ClientOverviewRequest[]>([])
const summary = ref({ total_clients: 0, clients_with_requests: 0, clients_with_active_requests: 0 })
const clientsPagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 12 })
const requestsPagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 10 })

const statCards = computed(() => [
  { label: t('adminClientRequests.stats.clientGroups'), value: clientsPagination.value.total, tone: 'emerald' },
  { label: t('adminClientRequests.stats.activeGroups'), value: summary.value.clients_with_active_requests, tone: 'blue' },
  { label: t('adminClientRequests.stats.visibleClients'), value: clients.value.length, tone: 'violet' },
  { label: t('adminClientRequests.stats.selectedRequests'), value: requestsPagination.value.total, tone: 'amber' },
])

function dateText(value?: string | null) {
  return formatDateTime(value, locale, t('adminClientRequests.states.emptyValue'))
}

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

function companyName(item: ClientOverviewRequest) {
  return item.company_name || intakeCompanyName(item.intake_details_json, t('adminClientRequests.states.emptyValue'))
}

function staffPreview(item: ClientOverviewRequest) {
  const staff = item.active_staff ?? []

  if (!staff.length) {
    return item.primary_staff?.name || t('adminClientRequests.states.notAssigned')
  }

  return staff.map((entry) => entry.name).join(', ')
}

async function loadClients(page = clientsPagination.value.current_page) {
  loadingClients.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminClientsOverview({
      search: search.value.trim() || undefined,
      state: 'all',
      with_requests: true,
      page,
      per_page: clientsPagination.value.per_page,
    })

    clients.value = data.clients ?? []
    summary.value = data.summary ?? summary.value
    clientsPagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: clientsPagination.value.per_page }

    if (selectedClient.value && !clients.value.some((client) => client.id === selectedClient.value?.id)) {
      selectedClient.value = null
      selectedClientRequests.value = []
      requestsPagination.value = { ...DEFAULT_PAGINATION, per_page: requestsPagination.value.per_page }
    }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminClientRequests.errors.loadClientsFailed')
  } finally {
    loadingClients.value = false
  }
}

async function loadClientRequests(client: ClientOverviewItem | null = selectedClient.value, page = 1) {
  if (!client) return

  selectedClient.value = client
  loadingRequests.value = true
  requestErrorMessage.value = ''

  try {
    const data = await getAdminClientRequests(client.id, {
      page,
      per_page: requestsPagination.value.per_page,
    })

    selectedClientRequests.value = data.requests ?? []
    requestsPagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: requestsPagination.value.per_page }
  } catch (error: any) {
    requestErrorMessage.value = error?.response?.data?.message || t('adminClientRequests.errors.loadRequestsFailed')
  } finally {
    loadingRequests.value = false
  }
}

function applySearch() {
  selectedClient.value = null
  selectedClientRequests.value = []
  requestsPagination.value = { ...DEFAULT_PAGINATION, per_page: requestsPagination.value.per_page }
  loadClients(1)
}

function resetSearch() {
  search.value = ''
  applySearch()
}

function goToRequestDetail(id: number) {
  router.push({ name: 'admin-request-details', params: { id: String(id) } })
}

onMounted(() => loadClients(1))
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminClientRequests.hero.eyebrow') }}</p>
        <h4>{{ t('adminClientRequests.hero.title') }}</h4>
        <p class="subtext">{{ t('adminClientRequests.hero.subtitle') }}</p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="loadClients()">{{ t('adminClientRequests.actions.refresh') }}</button>
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
          <h2>{{ t('adminClientRequests.search.title') }}</h2>
          <p class="subtext">{{ t('adminClientRequests.search.subtitle') }}</p>
        </div>
      </div>

      <div class="filter-bar">
        <div class="field-block field-block--wide">
          <span>{{ t('adminClientRequests.search.label') }}</span>
          <input
            v-model="search"
            type="search"
            class="admin-input"
            :placeholder="t('adminClientRequests.search.placeholder')"
            @keyup.enter="applySearch"
          >
        </div>
        <div class="filter-actions">
          <button class="primary-btn" type="button" @click="applySearch">{{ t('adminClientRequests.actions.search') }}</button>
          <button class="ghost-btn" type="button" @click="resetSearch">{{ t('adminClientRequests.actions.reset') }}</button>
        </div>
      </div>
    </article>

    <article class="panel-card">
      <div class="panel-head">
        <h2>{{ t('adminClientRequests.clients.title') }}</h2>
        <span class="count-pill">{{ t('adminClientRequests.clients.count', { count: clientsPagination.total }) }}</span>
      </div>

      <p v-if="loadingClients" class="empty-state">{{ t('adminClientRequests.states.loadingClients') }}</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!clients.length" class="empty-state">{{ t('adminClientRequests.states.noClients') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminClientRequests.clients.client') }}</th>
              <th>{{ t('adminClientRequests.clients.totalRequests') }}</th>
              <th>{{ t('adminClientRequests.clients.activeRequests') }}</th>
              <th>{{ t('adminClientRequests.clients.lastRequest') }}</th>
              <th>{{ t('adminClientRequests.clients.lastLogin') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="client in clients"
              :key="client.id"
              class="is-clickable-row"
              :class="{ 'is-selected-row': selectedClient?.id === client.id }"
              role="button"
              tabindex="0"
              @click="loadClientRequests(client, 1)"
              @keydown.enter.prevent="loadClientRequests(client, 1)"
              @keydown.space.prevent="loadClientRequests(client, 1)"
            >
              <td>
                <strong>{{ client.name }}</strong>
                <div class="muted-small">{{ client.email }}</div>
              </td>
              <td>{{ client.requests_count }}</td>
              <td>{{ client.active_requests_count }}</td>
              <td>{{ dateText(client.last_request_at) }}</td>
              <td>{{ dateText(client.last_login_at) }}</td>
              <td @click.stop>
                <button class="primary-btn small-btn" type="button" @click="loadClientRequests(client, 1)">
                  {{ t('adminClientRequests.actions.viewRequests') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination :pagination="clientsPagination" :disabled="loadingClients" @change="loadClients" />
    </article>

    <article class="panel-card">
      <div class="panel-head">
        <div>
          <h2>{{ selectedClient?.name || t('adminClientRequests.requests.title') }}</h2>
          <p class="subtext">
            <template v-if="selectedClient">
              {{ selectedClient.email }} - {{ selectedClient.phone || t('adminClientRequests.states.noPhoneSaved') }}
            </template>
            <template v-else>
              {{ t('adminClientRequests.requests.subtitle') }}
            </template>
          </p>
        </div>
        <span class="count-pill">{{ t('adminClientRequests.requests.count', { count: requestsPagination.total }) }}</span>
      </div>

      <p v-if="!selectedClient" class="empty-state">{{ t('adminClientRequests.states.selectClient') }}</p>
      <p v-else-if="loadingRequests" class="empty-state">{{ t('adminClientRequests.states.loadingRequests') }}</p>
      <p v-else-if="requestErrorMessage" class="error-state">{{ requestErrorMessage }}</p>
      <p v-else-if="!selectedClientRequests.length" class="empty-state">{{ t('adminClientRequests.states.noRequests') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminClientRequests.requests.request') }}</th>
              <th>{{ t('adminClientRequests.requests.company') }}</th>
              <th>{{ t('adminClientRequests.requests.assignedStaff') }}</th>
              <th>{{ t('adminClientRequests.requests.emailRecords') }}</th>
              <th>{{ t('adminClientRequests.requests.submitted') }}</th>
              <th>{{ t('adminClientRequests.requests.lastActivity') }}</th>
              <th>{{ t('adminClientRequests.requests.status') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in selectedClientRequests"
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
                <div class="muted-small">{{ item.approval_reference_number || t('adminClientRequests.states.awaitingApprovalRef') }}</div>
              </td>
              <td>{{ companyName(item) }}</td>
              <td>{{ staffPreview(item) }}</td>
              <td>{{ item.emails_count }}</td>
              <td>{{ dateText(item.submitted_at) }}</td>
              <td>{{ dateText(item.latest_activity_at) }}</td>
              <td>
                <span class="status-badge">{{ formatRequestStatus(item.status, locale, t('adminClientRequests.states.emptyValue')) }}</span>
                <div class="muted-small">{{ stageMeta(item.workflow_stage).label }}</div>
              </td>
              <td @click.stop>
                <RouterLink :to="{ name: 'admin-request-details', params: { id: item.id } }" class="primary-btn small-btn">
                  {{ t('adminClientRequests.actions.open') }}
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination
        :pagination="requestsPagination"
        :disabled="loadingRequests || !selectedClient"
        @change="(page) => loadClientRequests(selectedClient, page)"
      />
    </article>
  </section>
</template>
