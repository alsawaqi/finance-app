<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import {
  getAdminClientRequests,
  getAdminClientsOverview,
  toggleAdminClientActive,
  type ClientOverviewItem,
  type ClientOverviewRequest,
} from '@/services/adminRequestFiltering'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'
import { intakeCompanyName } from '@/utils/requestIntake'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'
import { formatDateTime } from '@/utils/dateTime'
import { formatRequestStatus } from '@/utils/requestStatus'

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()

function goToRequestDetail(id: number) {
  router.push({ name: 'admin-request-details', params: { id: String(id) } })
}

const loading = ref(true)
const detailLoading = ref(false)
const actionLoadingId = ref<number | null>(null)
const errorMessage = ref('')
const detailErrorMessage = ref('')
const actionMessage = ref('')
const search = ref('')
const clients = ref<ClientOverviewItem[]>([])
const selectedClient = ref<ClientOverviewItem | null>(null)
const selectedClientRequests = ref<ClientOverviewRequest[]>([])
const summary = ref({ total_clients: 0, clients_with_requests: 0, clients_with_active_requests: 0 })
const clientsPagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 12 })
const requestsPagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 12 })

const currentState = computed<'active' | 'inactive'>(() =>
  route.name === 'admin-clients-overview-deactivated' ? 'inactive' : 'active',
)
const isDeactivatedView = computed(() => currentState.value === 'inactive')

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

const statCards = computed(() => [
  { label: t('adminClientsOverview.stats.clients'), value: summary.value.total_clients, tone: 'emerald' },
  { label: t('adminClientsOverview.stats.withRequests'), value: summary.value.clients_with_requests, tone: 'blue' },
  { label: t('adminClientsOverview.stats.withActiveRequests'), value: summary.value.clients_with_active_requests, tone: 'violet' },
  { label: t('adminClientsOverview.stats.selectedClientRequests'), value: requestsPagination.value.total, tone: 'amber' },
])

async function loadClients(page = clientsPagination.value.current_page) {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminClientsOverview({
      search: search.value || undefined,
      state: currentState.value,
      page,
      per_page: clientsPagination.value.per_page,
    })

    clients.value = data.clients ?? []
    summary.value = data.summary ?? summary.value
    clientsPagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: clientsPagination.value.per_page }

    if (selectedClient.value) {
      const refreshed = clients.value.find((item) => item.id === selectedClient.value?.id) || null
      selectedClient.value = refreshed

      if (!refreshed) {
        selectedClientRequests.value = []
        requestsPagination.value = { ...DEFAULT_PAGINATION, per_page: requestsPagination.value.per_page }
      }
    }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminClientsOverview.errors.loadDirectoryFailed')
  } finally {
    loading.value = false
  }
}

async function loadClientRequests(client: ClientOverviewItem | null = selectedClient.value, page = requestsPagination.value.current_page) {
  if (!client) {
    return
  }

  selectedClient.value = client
  detailLoading.value = true
  detailErrorMessage.value = ''

  try {
    const data = await getAdminClientRequests(client.id, {
      page,
      per_page: requestsPagination.value.per_page,
    })
    selectedClientRequests.value = data.requests ?? []
    requestsPagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: requestsPagination.value.per_page }
  } catch (error: any) {
    detailErrorMessage.value = error?.response?.data?.message || t('adminClientsOverview.errors.loadClientRequestsFailed')
  } finally {
    detailLoading.value = false
  }
}

async function toggleClient(client: ClientOverviewItem) {
  actionLoadingId.value = client.id
  actionMessage.value = ''
  errorMessage.value = ''

  try {
    const data = await toggleAdminClientActive(client.id)
    actionMessage.value = data.message

    if (selectedClient.value?.id === client.id) {
      selectedClient.value = data.client
    }

    await loadClients(clientsPagination.value.current_page)

    if (selectedClient.value && selectedClient.value.id === client.id && selectedClient.value.is_active === isDeactivatedView.value) {
      selectedClient.value = null
      selectedClientRequests.value = []
      requestsPagination.value = { ...DEFAULT_PAGINATION, per_page: requestsPagination.value.per_page }
    }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminClientsOverview.errors.toggleClientFailed')
  } finally {
    actionLoadingId.value = null
  }
}

function dateText(value?: string | null) {
  return formatDateTime(value, locale, t('adminClientsOverview.states.emptyValue'))
}

function staffPreview(item: ClientOverviewRequest) {
  const staff = item.active_staff ?? []

  if (!staff.length) {
    return item.primary_staff?.name || t('adminClientsOverview.states.notAssigned')
  }

  return staff.map((entry) => entry.name).join(', ')
}

function companyName(item: ClientOverviewRequest) {
  return item.company_name || intakeCompanyName(item.intake_details_json, t('adminClientsOverview.states.emptyValue'))
}

function applySearch() {
  loadClients(1)
}

watch(
  () => route.name,
  () => {
    selectedClient.value = null
    selectedClientRequests.value = []
    clientsPagination.value.current_page = 1
    requestsPagination.value = { ...DEFAULT_PAGINATION, per_page: requestsPagination.value.per_page }
    loadClients(1)
  },
  { immediate: true },
)
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminClientsOverview.hero.eyebrow') }}</p>
        <h4>{{ isDeactivatedView ? t('adminClientsOverview.hero.deactivatedTitle') : t('adminClientsOverview.hero.title') }}</h4>
        <p class="subtext">
          {{ isDeactivatedView ? t('adminClientsOverview.hero.deactivatedSubtitle') : t('adminClientsOverview.hero.subtitle') }}
        </p>
      </div>
      <div class="actions-row">
        <RouterLink
          class="ghost-btn"
          :to="isDeactivatedView ? { name: 'admin-clients-overview' } : { name: 'admin-clients-overview-deactivated' }"
        >
          {{ isDeactivatedView ? t('adminClientsOverview.actions.viewActiveClients') : t('adminClientsOverview.actions.viewDeactivatedClients') }}
        </RouterLink>
        <button class="ghost-btn" type="button" @click="loadClients()">{{ t('adminClientsOverview.hero.refresh') }}</button>
      </div>
    </div>

    <div class="admin-question-stats-grid admin-reveal-up">
      <article v-for="stat in statCards" :key="stat.label" class="admin-question-stat" :class="`tone-${stat.tone}`">
        <strong>{{ stat.value }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </div>

    <div v-if="errorMessage" class="admin-alert admin-alert--error">{{ errorMessage }}</div>
    <div v-if="actionMessage" class="admin-alert admin-alert--success">{{ actionMessage }}</div>

    <article class="panel-card">
      <div class="panel-head">
        <div>
          <h2>{{ t('adminClientsOverview.search.title') }}</h2>
          <p class="subtext">{{ t('adminClientsOverview.search.subtitle') }}</p>
        </div>
      </div>

      <div class="filter-bar">
        <div class="field-block field-block--wide">
          <span>{{ t('adminClientsOverview.search.label') }}</span>
          <input v-model="search" type="text" class="admin-input" :placeholder="t('adminClientsOverview.search.placeholder')" />
        </div>
        <div class="filter-actions">
          <button class="primary-btn" type="button" @click="applySearch">{{ t('adminClientsOverview.search.apply') }}</button>
        </div>
      </div>
    </article>

    <article class="panel-card">
      <div class="panel-head">
        <h2>{{ isDeactivatedView ? t('adminClientsOverview.table.deactivatedClientsTitle') : t('adminClientsOverview.table.clientsTitle') }}</h2>
        <span class="count-pill">{{ t('adminClientsOverview.table.clientsCount', { count: clientsPagination.total }) }}</span>
      </div>

      <p v-if="loading" class="empty-state">{{ t('adminClientsOverview.states.loadingDirectory') }}</p>
      <p v-else-if="!clients.length" class="empty-state">{{ t('adminClientsOverview.states.noClients') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminClientsOverview.table.client') }}</th>
              <th>{{ t('adminClientsOverview.table.totalRequests') }}</th>
              <th>{{ t('adminClientsOverview.table.activeRequests') }}</th>
              <th>{{ t('adminClientsOverview.table.status') }}</th>
              <th>{{ t('adminClientsOverview.table.lastRequest') }}</th>
              <th>{{ t('adminClientsOverview.table.lastLogin') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="client in clients"
              :key="client.id"
              class="is-clickable-row"
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
              <td>
                <span class="status-badge">{{ client.is_active ? t('adminClientsOverview.states.active') : t('adminClientsOverview.states.inactive') }}</span>
              </td>
              <td>{{ dateText(client.last_request_at) }}</td>
              <td>{{ dateText(client.last_login_at) }}</td>
              <td @click.stop>
                <div class="actions-row">
                  <button class="primary-btn small-btn" type="button" @click="loadClientRequests(client, 1)">{{ t('adminClientsOverview.actions.viewRequests') }}</button>
                  <button class="ghost-btn small-btn" type="button" :disabled="actionLoadingId === client.id" @click="toggleClient(client)">
                    {{
                      actionLoadingId === client.id
                        ? t('adminClientsOverview.actions.processing')
                        : client.is_active
                          ? t('adminClientsOverview.actions.deactivate')
                          : t('adminClientsOverview.actions.activate')
                    }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination :pagination="clientsPagination" :disabled="loading" @change="loadClients" />
    </article>

    <article v-if="selectedClient" class="panel-card">
      <div class="panel-head">
        <div>
          <h2>{{ selectedClient.name }}</h2>
          <p class="subtext">{{ selectedClient.email }} | {{ selectedClient.phone || t('adminClientsOverview.states.noPhoneSaved') }}</p>
        </div>
        <span class="count-pill">{{ t('adminClientsOverview.table.requestsCount', { count: requestsPagination.total }) }}</span>
      </div>

      <p v-if="detailLoading" class="empty-state">{{ t('adminClientsOverview.states.loadingClientRequests') }}</p>
      <p v-else-if="detailErrorMessage" class="error-state">{{ detailErrorMessage }}</p>
      <p v-else-if="!selectedClientRequests.length" class="empty-state">{{ t('adminClientsOverview.states.noRequestsForClient') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminClientsOverview.table.request') }}</th>
              <th>{{ t('adminClientsOverview.table.company') }}</th>
              <th>{{ t('adminClientsOverview.table.assignedStaff') }}</th>
              <th>{{ t('adminClientsOverview.table.emailRecords') }}</th>
              <th>{{ t('adminClientsOverview.table.submitted') }}</th>
              <th>{{ t('adminClientsOverview.table.lastActivity') }}</th>
              <th>{{ t('adminClientsOverview.table.status') }}</th>
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
                <div class="muted-small">{{ item.approval_reference_number || t('adminClientsOverview.states.awaitingApprovalRef') }}</div>
              </td>
              <td>{{ companyName(item) }}</td>
              <td>{{ staffPreview(item) }}</td>
              <td>{{ item.emails_count }}</td>
              <td>{{ dateText(item.submitted_at) }}</td>
              <td>{{ dateText(item.latest_activity_at) }}</td>
              <td>
                <span class="status-badge">{{ formatRequestStatus(item.status, locale, t('adminClientsOverview.states.emptyValue')) }}</span>
                <div class="muted-small">{{ stageMeta(item.workflow_stage).label }}</div>
              </td>
              <td @click.stop>
                <RouterLink :to="{ name: 'admin-request-details', params: { id: item.id } }" class="primary-btn small-btn">{{ t('adminClientsOverview.actions.open') }}</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination :pagination="requestsPagination" :disabled="detailLoading" @change="(page) => loadClientRequests(selectedClient, page)" />
    </article>
  </section>
</template>
