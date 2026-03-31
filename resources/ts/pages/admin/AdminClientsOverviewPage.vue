<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getAdminClientRequests,
  getAdminClientsOverview,
  type ClientOverviewItem,
  type ClientOverviewRequest,
} from '@/services/adminRequestFiltering'

const loading = ref(true)
const detailLoading = ref(false)
const errorMessage = ref('')
const detailErrorMessage = ref('')
const search = ref('')
const clients = ref<ClientOverviewItem[]>([])
const selectedClient = ref<ClientOverviewItem | null>(null)
const selectedClientRequests = ref<ClientOverviewRequest[]>([])
const summary = ref({ total_clients: 0, clients_with_requests: 0, clients_with_active_requests: 0 })
const { t } = useI18n()

const statCards = computed(() => [
  { label: t('adminClientsOverview.stats.clients'), value: summary.value.total_clients, tone: 'emerald' },
  { label: t('adminClientsOverview.stats.withRequests'), value: summary.value.clients_with_requests, tone: 'blue' },
  { label: t('adminClientsOverview.stats.withActiveRequests'), value: summary.value.clients_with_active_requests, tone: 'violet' },
  { label: t('adminClientsOverview.stats.selectedClientRequests'), value: selectedClientRequests.value.length, tone: 'amber' },
])

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminClientsOverview({ search: search.value || undefined })
    clients.value = data.clients ?? []
    summary.value = data.summary ?? summary.value

    if (selectedClient.value) {
      const refreshed = clients.value.find((item) => item.id === selectedClient.value?.id) || null
      selectedClient.value = refreshed

      if (!refreshed) {
        selectedClientRequests.value = []
      }
    }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminClientsOverview.errors.loadDirectoryFailed')
  } finally {
    loading.value = false
  }
}

async function viewClient(client: ClientOverviewItem) {
  selectedClient.value = client
  selectedClientRequests.value = []
  detailLoading.value = true
  detailErrorMessage.value = ''

  try {
    const data = await getAdminClientRequests(client.id)
    selectedClientRequests.value = data.requests ?? []
  } catch (error: any) {
    detailErrorMessage.value = error?.response?.data?.message || t('adminClientsOverview.errors.loadClientRequestsFailed')
  } finally {
    detailLoading.value = false
  }
}

function dateText(value?: string | null) {
  return value ? new Date(value).toLocaleString() : t('adminClientsOverview.states.emptyValue')
}

function staffPreview(item: ClientOverviewRequest) {
  const staff = item.active_staff ?? []

  if (!staff.length) {
    return item.primary_staff?.name || t('adminClientsOverview.states.notAssigned')
  }

  return staff.map((entry) => entry.name).join(', ')
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminClientsOverview.hero.eyebrow') }}</p>
        <h1>{{ t('adminClientsOverview.hero.title') }}</h1>
        <p class="subtext">
          {{ t('adminClientsOverview.hero.subtitle') }}
        </p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="load">{{ t('adminClientsOverview.hero.refresh') }}</button>
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
          <button class="primary-btn" type="button" @click="load">{{ t('adminClientsOverview.search.apply') }}</button>
        </div>
      </div>
    </article>

    <article class="panel-card">
      <div class="panel-head">
        <h2>{{ t('adminClientsOverview.table.clientsTitle') }}</h2>
        <span class="count-pill">{{ t('adminClientsOverview.table.clientsCount', { count: clients.length }) }}</span>
      </div>

      <p v-if="loading" class="empty-state">{{ t('adminClientsOverview.states.loadingDirectory') }}</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!clients.length" class="empty-state">{{ t('adminClientsOverview.states.noClients') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminClientsOverview.table.client') }}</th>
              <th>{{ t('adminClientsOverview.table.totalRequests') }}</th>
              <th>{{ t('adminClientsOverview.table.activeRequests') }}</th>
              <th>{{ t('adminClientsOverview.table.lastRequest') }}</th>
              <th>{{ t('adminClientsOverview.table.lastLogin') }}</th>
              <th></th>
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
              <td>{{ dateText(client.last_request_at) }}</td>
              <td>{{ dateText(client.last_login_at) }}</td>
              <td>
                <button class="primary-btn small-btn" type="button" @click="viewClient(client)">{{ t('adminClientsOverview.actions.viewRequests') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>

    <article v-if="selectedClient" class="panel-card">
      <div class="panel-head">
        <div>
          <h2>{{ selectedClient.name }}</h2>
          <p class="subtext">{{ selectedClient.email }} · {{ selectedClient.phone || t('adminClientsOverview.states.noPhoneSaved') }}</p>
        </div>
        <span class="count-pill">{{ t('adminClientsOverview.table.requestsCount', { count: selectedClientRequests.length }) }}</span>
      </div>

      <p v-if="detailLoading" class="empty-state">{{ t('adminClientsOverview.states.loadingClientRequests') }}</p>
      <p v-else-if="detailErrorMessage" class="error-state">{{ detailErrorMessage }}</p>
      <p v-else-if="!selectedClientRequests.length" class="empty-state">{{ t('adminClientsOverview.states.noRequestsForClient') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminClientsOverview.table.request') }}</th>
              <th>{{ t('adminClientsOverview.table.assignedStaff') }}</th>
              <th>{{ t('adminClientsOverview.table.emailRecords') }}</th>
              <th>{{ t('adminClientsOverview.table.submitted') }}</th>
              <th>{{ t('adminClientsOverview.table.lastActivity') }}</th>
              <th>{{ t('adminClientsOverview.table.status') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in selectedClientRequests" :key="item.id">
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || t('adminClientsOverview.states.awaitingApprovalRef') }}</div>
              </td>
              <td>{{ staffPreview(item) }}</td>
              <td>{{ item.emails_count }}</td>
              <td>{{ dateText(item.submitted_at) }}</td>
              <td>{{ dateText(item.latest_activity_at) }}</td>
              <td>
                <span class="status-badge">{{ item.status }}</span>
                <div class="muted-small">{{ item.workflow_stage }}</div>
              </td>
              <td>
                <RouterLink :to="{ name: 'admin-request-details', params: { id: item.id } }" class="primary-btn small-btn">{{ t('adminClientsOverview.actions.open') }}</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</template>
