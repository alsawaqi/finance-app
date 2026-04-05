<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import {
  listReadyForAssignment,
  type AssignmentReadyRequest,
} from '@/services/adminRequests'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'
import { intakeCompanyName, intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'
import { formatDateTime } from '@/utils/dateTime'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<AssignmentReadyRequest[]>([])
const pagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 12 })
const { t, locale } = useI18n()

const signedCount = computed(() => requests.value.filter((item) => Boolean(item.current_contract?.client_signed_at)).length)

async function load(page = pagination.value.current_page) {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await listReadyForAssignment({
      page,
      per_page: pagination.value.per_page,
    })
    requests.value = data.requests ?? []
    pagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: pagination.value.per_page }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminAssignments.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function staffPreview(item: AssignmentReadyRequest) {
  if (!item.assignments?.length) return t('adminAssignments.states.notAssignedYet')
  return item.assignments.map((entry) => entry.staff?.name).filter(Boolean).join(', ')
}

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('adminAssignments.hero.eyebrow') }}</p>
        <h1>{{ t('adminAssignments.hero.title') }}</h1>
        <p class="subtext">{{ t('adminAssignments.hero.subtitle') }}</p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="load">{{ t('adminAssignments.hero.refreshQueue') }}</button>
      </div>
    </div>

    <div class="admin-question-stats-grid admin-reveal-up">
      <article class="admin-question-stat tone-blue">
        <strong>{{ pagination.total }}</strong>
        <span>{{ t('adminAssignments.stats.readyRequests') }}</span>
      </article>
      <article class="admin-question-stat tone-emerald">
        <strong>{{ signedCount }}</strong>
        <span>{{ t('adminAssignments.stats.fullySigned') }}</span>
      </article>
      <article class="admin-question-stat tone-violet">
        <strong>{{ requests.filter((item) => item.assignments?.length).length }}</strong>
        <span>{{ t('adminAssignments.stats.alreadyAssigned') }}</span>
      </article>
      <article class="admin-question-stat tone-amber">
        <strong>{{ requests.filter((item) => !item.assignments?.length).length }}</strong>
        <span>{{ t('adminAssignments.stats.needAssignment') }}</span>
      </article>
    </div>

    <article class="panel-card">
      <div class="panel-head">
        <div>
          <h2>{{ t('adminAssignments.table.title') }}</h2>
          <p class="subtext">{{ t('adminAssignments.table.subtitle') }}</p>
        </div>
        <span class="count-pill">{{ t('adminAssignments.table.count', { count: pagination.total }) }}</span>
      </div>

      <p v-if="loading" class="empty-state">{{ t('adminAssignments.states.loading') }}</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!requests.length" class="empty-state">{{ t('adminAssignments.states.empty') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('adminAssignments.table.request') }}</th>
              <th>{{ t('adminAssignments.table.client') }}</th>
              <th>{{ t('adminAssignments.table.company') }}</th>
              <th>{{ t('adminAssignments.table.financeType') }}</th>
              <th>{{ t('adminAssignments.table.signed') }}</th>
              <th>{{ t('adminAssignments.table.currentHandling') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests" :key="item.id">
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || t('adminAssignments.states.approvalPending') }}</div>
              </td>
              <td>
                <strong>{{ intakeFullName(item.intake_details_json, item.client?.name || t('adminAssignments.states.clientFallback')) }}</strong>
                <div class="muted-small">{{ item.client?.email || t('adminAssignments.states.emptyValue') }}</div>
              </td>
              <td>{{ item.company_name || intakeCompanyName(item.intake_details_json, t('adminAssignments.states.emptyValue')) }}</td>
              <td>
                <div>{{ intakeFinanceType(item.intake_details_json, t('adminAssignments.states.emptyValue'), locale) }}</div>
                <div class="muted-small">{{ intakeRequestedAmount(item.intake_details_json) }}</div>
              </td>
              <td>{{ formatDateTime(item.current_contract?.client_signed_at, locale, t('adminAssignments.states.pending')) }}</td>
              <td>
                <span class="status-badge">{{ stageMeta(item.workflow_stage).label }}</span>
                <div class="muted-small">{{ staffPreview(item) }}</div>
              </td>
              <td>
                <RouterLink :to="{ name: 'admin-assignment-details', params: { id: item.id } }" class="primary-btn small-btn">
                  {{ t('adminAssignments.actions.manage') }}
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination :pagination="pagination" :disabled="loading" @change="load" />
    </article>
  </section>
</template>
