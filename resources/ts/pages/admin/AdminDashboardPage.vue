<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { getAdminCategorization } from '@/services/adminCategorization'

const loading = ref(true)
const errorMessage = ref('')
const { t } = useI18n()

const summary = ref({
  total_requests: 0,
  submitted_requests: 0,
  active_requests: 0,
  completed_requests: 0,
  total_clients: 0,
  total_staff: 0,
  total_agents: 0,
  with_additional_document_requests: 0,
  pending_queue_requests: 0,
  contract_queue_requests: 0,
  assigned_queue_requests: 0,
})

const statusBreakdown = ref<Record<string, number>>({})
const requestTrend = ref<{ labels: string[]; series: number[] }>({ labels: [], series: [] })
const bankEmailBreakdown = ref<{ labels: string[]; email_series: number[]; request_series: number[] }>({
  labels: [],
  email_series: [],
  request_series: [],
})

const cards = computed(() => [
  {
    label: 'Pending review',
    value: summary.value.pending_queue_requests,
    tone: 'amber',
    route: { name: 'admin-new-requests', query: { queue: 'pending' } },
  },
  {
    label: 'Contract queue',
    value: summary.value.contract_queue_requests,
    tone: 'violet',
    route: { name: 'admin-new-requests', query: { queue: 'contract' } },
  },
  {
    label: 'Assigned requests',
    value: summary.value.assigned_queue_requests,
    tone: 'green',
    route: { name: 'admin-assignments' },
  },
  {
    label: 'Total requests',
    value: summary.value.total_requests,
    tone: 'blue',
    route: { name: 'admin-request-filtration' },
  },
  {
    label: 'Clients',
    value: summary.value.total_clients,
    tone: 'blue',
    route: { name: 'admin-clients-overview' },
  },
  {
    label: 'Agents',
    value: summary.value.total_agents,
    tone: 'emerald',
    route: { name: 'admin-agents' },
  },
])

const requestTrendOptions = computed(() => ({
  chart: {
    id: 'request-trend',
    toolbar: { show: false },
    zoom: { enabled: false },
  },
  xaxis: {
    categories: requestTrend.value.labels,
  },
  stroke: {
    curve: 'smooth',
    width: 3,
  },
  dataLabels: { enabled: false },
  markers: { size: 4 },
  tooltip: { shared: true },
}))

const requestTrendSeries = computed(() => [
  {
    name: 'Requests',
    data: requestTrend.value.series,
  },
])

const bankChartOptions = computed(() => ({
  chart: {
    id: 'bank-traffic',
    toolbar: { show: false },
  },
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 6,
    },
  },
  xaxis: {
    categories: bankEmailBreakdown.value.labels,
  },
  dataLabels: { enabled: false },
  tooltip: { shared: true, intersect: false },
}))

const bankChartSeries = computed(() => [
  {
    name: 'Emails',
    data: bankEmailBreakdown.value.email_series,
  },
  {
    name: 'Requests',
    data: bankEmailBreakdown.value.request_series,
  },
])

const topStatuses = computed(() =>
  Object.entries(statusBreakdown.value).sort((a, b) => b[1] - a[1]).slice(0, 5),
)

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getAdminCategorization()
    summary.value = data.summary
    statusBreakdown.value = data.status_breakdown ?? {}
    requestTrend.value = data.charts?.request_trend ?? { labels: [], series: [] }
    bankEmailBreakdown.value = data.charts?.bank_email_breakdown ?? { labels: [], email_series: [], request_series: [] }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminDashboard.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-dashboard-page">
    <section class="admin-hero admin-reveal-up">
      <div class="admin-hero__content">
        <span class="admin-hero__eyebrow">{{ t('adminDashboard.hero.eyebrow') }}</span>
        <h2>{{ t('adminDashboard.hero.title') }}</h2>
        <p>{{ t('adminDashboard.hero.subtitle') }}</p>
      </div>

      <div class="admin-hero__actions admin-hero__actions--stacked">
        <RouterLink :to="{ name: 'admin-new-requests' }" class="admin-primary-btn">Open new queue</RouterLink>
        <RouterLink :to="{ name: 'admin-request-filtration' }" class="admin-secondary-btn">Open filtration</RouterLink>
      </div>
    </section>

    <div v-if="errorMessage" class="admin-alert admin-alert--error">{{ errorMessage }}</div>

    <div class="admin-question-stats-grid admin-reveal-up admin-reveal-delay-1">
      <RouterLink
        v-for="card in cards"
        :key="card.label"
        :to="card.route"
        class="admin-question-stat admin-question-stat--link"
        :class="`tone-${card.tone}`"
      >
        <strong>{{ loading ? '…' : card.value }}</strong>
        <span>{{ card.label }}</span>
        <span class="admin-inline-link">{{ t('adminDashboard.actions.open') }}</span>
      </RouterLink>
    </div>

    <div class="admin-dashboard-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
      <article class="panel-card">
        <div class="panel-head">
          <h2>Request intake trend</h2>
        </div>

        <apexchart
          v-if="!loading"
          type="line"
          height="320"
          :options="requestTrendOptions"
          :series="requestTrendSeries"
        />
      </article>

      <article class="panel-card">
        <div class="panel-head">
          <h2>Emails by bank</h2>
        </div>

        <apexchart
          v-if="!loading"
          type="bar"
          height="320"
          :options="bankChartOptions"
          :series="bankChartSeries"
        />
      </article>

      <article class="panel-card catalog-highlight-card">
        <div class="panel-head"><h2>Top workflow buckets</h2></div>
        <div class="catalog-chip-grid">
          <span v-for="entry in topStatuses" :key="entry[0]" class="soft-tag">{{ entry[0] }} · {{ entry[1] }}</span>
        </div>
      </article>

      <article class="panel-card catalog-highlight-card">
        <div class="panel-head"><h2>Current totals</h2></div>
        <div class="catalog-mini-stats">
          <div>
            <span>Total requests</span>
            <strong>{{ loading ? '…' : summary.total_requests }}</strong>
          </div>
          <div>
            <span>Completed</span>
            <strong>{{ loading ? '…' : summary.completed_requests }}</strong>
          </div>
          <div>
            <span>Clients</span>
            <strong>{{ loading ? '…' : summary.total_clients }}</strong>
          </div>
          <div>
            <span>Additional docs</span>
            <strong>{{ loading ? '…' : summary.with_additional_document_requests }}</strong>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
