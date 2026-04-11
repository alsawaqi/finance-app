<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import VueApexCharts from 'vue3-apexcharts'
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
    label: t('adminNewRequests.queue.pendingReview'),
    value: summary.value.pending_queue_requests,
    tone: 'amber',
    route: { name: 'admin-new-requests', query: { queue: 'pending' } },
  },
  {
    label: t('adminNewRequests.queue.contractStage'),
    value: summary.value.contract_queue_requests,
    tone: 'violet',
    route: { name: 'admin-new-requests', query: { queue: 'contract' } },
  },
  {
    label: t('adminSidebar.menu.assignedRequests'),
    value: summary.value.assigned_queue_requests,
    tone: 'green',
    route: { name: 'admin-assignments' },
  },
  {
    label: t('adminDashboard.totals.totalRequests'),
    value: summary.value.total_requests,
    tone: 'blue',
    route: { name: 'admin-request-filtration' },
  },
  {
    label: t('adminDashboard.totals.clients'),
    value: summary.value.total_clients,
    tone: 'blue',
    route: { name: 'admin-clients-overview' },
  },
  {
    label: t('adminSidebar.menu.agents'),
    value: summary.value.total_agents,
    tone: 'emerald',
    route: { name: 'admin-agents' },
  },
])

const heroStats = computed(() => [
  { label: t('adminDashboard.totals.totalRequests'), value: summary.value.total_requests },
  { label: t('adminNewRequests.queue.pendingReview'), value: summary.value.pending_queue_requests },
  { label: t('adminSidebar.menu.assignedRequests'), value: summary.value.assigned_queue_requests },
])

const totals = computed(() => [
  { label: t('adminDashboard.totals.totalRequests'), value: summary.value.total_requests },
  { label: t('adminDashboard.totals.completed'), value: summary.value.completed_requests },
  { label: t('adminDashboard.totals.clients'), value: summary.value.total_clients },
  { label: t('adminDashboard.totals.additionalDocs'), value: summary.value.with_additional_document_requests },
])

const hasRequestTrend = computed(() =>
  requestTrend.value.labels.length > 0
  && requestTrend.value.series.some((value) => Number(value) > 0),
)

const hasBankBreakdown = computed(() =>
  bankEmailBreakdown.value.labels.length > 0
  && (
    bankEmailBreakdown.value.email_series.some((value) => Number(value) > 0)
    || bankEmailBreakdown.value.request_series.some((value) => Number(value) > 0)
  ),
)

const requestTrendOptions = computed(() => ({
  chart: {
    id: 'request-trend',
    toolbar: { show: false },
    zoom: { enabled: false },
  },
  colors: ['#4f46e5'],
  xaxis: {
    categories: requestTrend.value.labels,
    labels: {
      style: {
        colors: '#64748b',
        fontSize: '12px',
      },
    },
  },
  yaxis: {
    labels: {
      style: {
        colors: '#64748b',
        fontSize: '12px',
      },
    },
  },
  grid: {
    borderColor: 'rgba(148, 163, 184, 0.18)',
    strokeDashArray: 4,
  },
  stroke: {
    curve: 'smooth' as const,
    width: 3,
  },
  dataLabels: { enabled: false },
  markers: { size: 4, strokeWidth: 0, hover: { sizeOffset: 3 } },
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
  colors: ['#4f46e5', '#06b6d4'],
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 6,
      barHeight: '54%',
    },
  },
  xaxis: {
    categories: bankEmailBreakdown.value.labels,
    labels: {
      style: {
        colors: '#64748b',
        fontSize: '12px',
      },
    },
  },
  yaxis: {
    labels: {
      style: {
        colors: '#475569',
        fontSize: '12px',
      },
    },
  },
  grid: {
    borderColor: 'rgba(148, 163, 184, 0.18)',
    strokeDashArray: 4,
  },
  legend: {
    position: 'top' as const,
    horizontalAlign: 'left' as const,
    labels: {
      colors: '#475569',
    },
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
    <section class="admin-hero admin-dashboard-hero admin-reveal-up">
      <div class="admin-hero__content admin-dashboard-hero__content">
        <span class="admin-hero__eyebrow">{{ t('adminDashboard.hero.eyebrow') }}</span>
        <h2>{{ t('adminDashboard.hero.title') }}</h2>
        <p>{{ t('adminDashboard.hero.subtitle') }}</p>
      </div>

      <div class="admin-dashboard-hero__aside">
        <div class="admin-dashboard-hero__stats">
          <article v-for="item in heroStats" :key="item.label" class="admin-dashboard-hero__stat">
            <span>{{ item.label }}</span>
            <strong>{{ loading ? '...' : item.value }}</strong>
          </article>
        </div>

        <div class="admin-hero__actions admin-dashboard-hero__actions">
          <RouterLink :to="{ name: 'admin-new-requests' }" class="admin-primary-btn">{{ t('adminDashboard.actions.openReviewQueue') }}</RouterLink>
          <RouterLink :to="{ name: 'admin-categorization' }" class="admin-secondary-btn">{{ t('adminDashboard.actions.openCategorization') }}</RouterLink>
        </div>
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
        <strong>{{ loading ? '...' : card.value }}</strong>
        <span>{{ card.label }}</span>
        <span class="admin-inline-link">{{ t('adminDashboard.actions.open') }}</span>
      </RouterLink>
    </div>

    <div class="admin-dashboard-grid admin-dashboard-grid--main">
      <article class="panel-card admin-chart-card">
        <div class="panel-head">
          <h2>{{ t('adminDashboard.charts.requestIntakeTrend') }}</h2>
        </div>

        <div v-if="loading" class="empty-state">{{ t('adminDashboard.states.chartLoading') }}</div>
        <div v-else-if="hasRequestTrend" class="admin-chart-card__body">
          <VueApexCharts
            type="line"
            height="320"
            :options="requestTrendOptions"
            :series="requestTrendSeries"
          />
        </div>
        <div v-else class="empty-state">{{ t('adminDashboard.states.noRequestTrend') }}</div>
      </article>

      <article class="panel-card admin-chart-card">
        <div class="panel-head">
          <h2>{{ t('adminDashboard.charts.emailsByBank') }}</h2>
        </div>

        <div v-if="loading" class="empty-state">{{ t('adminDashboard.states.chartLoading') }}</div>
        <div v-else-if="hasBankBreakdown" class="admin-chart-card__body">
          <VueApexCharts
            type="bar"
            height="320"
            :options="bankChartOptions"
            :series="bankChartSeries"
          />
        </div>
        <div v-else class="empty-state">{{ t('adminDashboard.states.noBankActivity') }}</div>
      </article>

      <article class="panel-card catalog-highlight-card">
        <div class="panel-head">
          <h2>{{ t('adminDashboard.sections.quickDistribution') }}</h2>
        </div>

        <div class="catalog-chip-grid">
          <span v-for="entry in topStatuses" :key="entry[0]" class="soft-tag">{{ entry[0] }} / {{ entry[1] }}</span>
          <span v-if="!topStatuses.length" class="soft-tag soft-tag--muted">{{ t('adminDashboard.states.noWorkflowBuckets') }}</span>
        </div>
      </article>

      <article class="panel-card catalog-highlight-card">
        <div class="panel-head">
          <h2>{{ t('adminDashboard.sections.currentTotals') }}</h2>
        </div>

        <div class="catalog-mini-stats">
          <div v-for="item in totals" :key="item.label">
            <span>{{ item.label }}</span>
            <strong>{{ loading ? '...' : item.value }}</strong>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
