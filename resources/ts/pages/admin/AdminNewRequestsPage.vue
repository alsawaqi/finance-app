<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import { listNewRequests, type AdminRequestListItem } from '@/services/adminRequests'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCompanyName, intakeCountryCode, intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'
import { formatRequestStatus } from '@/utils/requestStatus'
import { formatDateTime } from '@/utils/dateTime'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<AdminRequestListItem[]>([])
const pagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 12 })
const queueSummary = ref({ all: 0, pending: 0, contract: 0 })
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()

const selectedQueue = ref<'all' | 'pending' | 'contract'>('all')

const queueCards = computed(() => [
  { key: 'all', label: t('adminNewRequests.queue.allPreAssignment'), value: queueSummary.value.all },
  { key: 'pending', label: t('adminNewRequests.queue.pendingReview'), value: queueSummary.value.pending },
  { key: 'contract', label: t('adminNewRequests.queue.contractStage'), value: queueSummary.value.contract },
])

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

async function load(page = pagination.value.current_page) {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await listNewRequests({
      queue: selectedQueue.value,
      page,
      per_page: pagination.value.per_page,
    })
    requests.value = data.requests ?? []
    queueSummary.value = data.queue_summary ?? queueSummary.value
    pagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: pagination.value.per_page }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminNewRequests.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function setQueue(queue: 'all' | 'pending' | 'contract') {
  if (selectedQueue.value === queue) {
    load(1)
    return
  }

  pagination.value.current_page = 1
  selectedQueue.value = queue
}

watch(
  () => route.query.queue,
  (value) => {
    const normalized = value === 'pending' || value === 'contract' || value === 'all'
      ? value
      : 'all'

    if (selectedQueue.value !== normalized) {
      pagination.value.current_page = 1
      selectedQueue.value = normalized
      return
    }

    load(pagination.value.current_page)
  },
  { immediate: true },
)

watch(selectedQueue, async (value, oldValue) => {
  if (value === oldValue) {
    return
  }

  await router.replace({
    name: 'admin-new-requests',
    query: value === 'all' ? {} : { queue: value },
  })
})

onMounted(() => {
  if (!route.query.queue) {
    load(pagination.value.current_page)
  }
})
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminNewRequests.hero.eyebrow') }}</p>
        <h1>{{ t('adminNewRequests.hero.title') }}</h1>
        <p class="subtext">
          {{ t('adminNewRequests.hero.subtitle') }}
        </p>
      </div>

      <button class="ghost-btn" type="button" @click="load">{{ t('adminNewRequests.actions.refresh') }}</button>
    </div>

    <div class="admin-question-stats-grid admin-reveal-up">
      <button
        v-for="card in queueCards"
        :key="card.key"
        type="button"
        class="admin-question-stat"
        :class="selectedQueue === card.key ? 'tone-blue' : 'tone-slate'"
        @click="setQueue(card.key as 'all' | 'pending' | 'contract')"
      >
        <strong>{{ loading ? '...' : card.value }}</strong>
        <span>{{ card.label }}</span>
      </button>
    </div>

    <div class="panel-card">
      <div class="panel-head">
        <h2>{{ t('adminNewRequests.table.title') }}</h2>
        <span class="count-pill">{{ t('adminNewRequests.table.totalCount', { count: pagination.total }) }}</span>
      </div>

      <p v-if="loading" class="empty-state">{{ t('adminNewRequests.states.loading') }}</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!requests.length" class="empty-state">{{ t('adminNewRequests.states.empty') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminNewRequests.table.request') }}</th>
              <th>{{ t('adminNewRequests.table.client') }}</th>
              <th>{{ t('adminNewRequests.table.company') }}</th>
              <th>{{ t('adminNewRequests.table.country') }}</th>
              <th>{{ t('adminNewRequests.table.requestedAmount') }}</th>
              <th>{{ t('adminNewRequests.table.financeType') }}</th>
              <th>{{ t('adminNewRequests.table.submitted') }}</th>
              <th>{{ t('adminNewRequests.table.queue') }}</th>
              <th>{{ t('adminNewRequests.table.status') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests" :key="item.id">
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || t('adminNewRequests.states.awaitingApproval') }}</div>
              </td>

              <td>
                <strong>{{ intakeFullName(item.intake_details_json, item.client?.name || t('adminNewRequests.states.clientFallback')) }}</strong>
                <div class="muted-small">{{ item.client?.email || t('adminNewRequests.states.emptyValue') }}</div>
              </td>

              <td>{{ item.company_name || intakeCompanyName(item.intake_details_json, t('adminNewRequests.states.emptyValue')) }}</td>

              <td>{{ countryNameFromCode(item.country_code || intakeCountryCode(item.intake_details_json), locale) }}</td>
              <td>{{ intakeRequestedAmount(item.intake_details_json) }}</td>
              <td>{{ intakeFinanceType(item.intake_details_json, t('adminNewRequests.states.emptyValue'), locale) }}</td>
              <td>{{ formatDateTime(item.submitted_at, locale, t('adminNewRequests.states.emptyValue')) }}</td>

              <td>
                <span class="status-badge">
                  {{
                    ['admin_contract_preparation', 'contract', 'awaiting_client_signature'].includes(String(item.workflow_stage))
                      ? t('adminNewRequests.queue.contract')
                      : t('adminNewRequests.queue.pending')
                  }}
                </span>
              </td>

              <td>
                <span class="status-badge">{{ formatRequestStatus(item.status, locale, t('adminNewRequests.states.emptyValue')) }}</span>
                <div class="muted-small">{{ stageMeta(item.workflow_stage).label }}</div>
              </td>

              <td>
                <RouterLink :to="{ name: 'admin-request-details', params: { id: item.id } }" class="primary-btn small-btn">
                  {{ t('adminNewRequests.actions.review') }}
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination :pagination="pagination" :disabled="loading" @change="load" />
    </div>
  </section>
</template>
