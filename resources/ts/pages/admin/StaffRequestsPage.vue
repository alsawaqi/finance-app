<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { getStaffRequests, type StaffWorkspaceRequestSummary } from '@/services/staffWorkspace'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'

const loading = ref(true)
const errorMessage = ref('')
const search = ref('')
const workflowStage = ref('')
const requests = ref<StaffWorkspaceRequestSummary[]>([])
const { t, locale } = useI18n()

const availableStages = computed(() => ['document_collection', 'awaiting_additional_documents', 'processing', 'completed'])

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getStaffRequests({
      search: search.value || undefined,
      workflow_stage: workflowStage.value || undefined,
    })
    requests.value = data.requests ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('staffRequests.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ t('staffRequests.hero.eyebrow') }}</p>
        <h1>{{ t('staffRequests.hero.title') }}</h1>
        <p class="subtext">{{ t('staffRequests.hero.subtitle') }}</p>
      </div>
      <div class="actions-row">
        <button class="ghost-btn" type="button" @click="load">{{ t('staffRequests.actions.refresh') }}</button>
      </div>
    </div>

    <article class="panel-card">
      <div class="panel-head">
        <div>
          <h2>{{ t('staffRequests.filters.title') }}</h2>
          <p class="subtext">{{ t('staffRequests.filters.subtitle') }}</p>
        </div>
      </div>

      <div class="filter-bar">
        <div class="field-block">
          <span>{{ t('staffRequests.filters.search') }}</span>
          <input v-model="search" type="text" class="admin-input" :placeholder="t('staffRequests.filters.searchPlaceholder')" />
        </div>
        <div class="field-block">
          <span>{{ t('staffRequests.filters.stage') }}</span>
          <select v-model="workflowStage" class="admin-select">
            <option value="">{{ t('staffRequests.filters.allStages') }}</option>
            <option v-for="stage in availableStages" :key="stage" :value="stage">{{ stage }}</option>
          </select>
        </div>
        <div class="filter-actions">
          <button class="primary-btn" type="button" @click="load">{{ t('staffRequests.actions.applyFilters') }}</button>
        </div>
      </div>
    </article>

    <article class="panel-card">
      <div class="panel-head">
        <h2>{{ t('staffRequests.table.title') }}</h2>
        <span class="count-pill">{{ t('staffRequests.table.count', { count: requests.length }) }}</span>
      </div>

      <p v-if="loading" class="empty-state">{{ t('staffRequests.states.loading') }}</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!requests.length" class="empty-state">{{ t('staffRequests.states.empty') }}</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>{{ t('staffRequests.table.request') }}</th>
              <th>{{ t('staffRequests.table.client') }}</th>
              <th>{{ t('staffRequests.table.country') }}</th>
              <th>{{ t('staffRequests.table.financeType') }}</th>
              <th>{{ t('staffRequests.table.comments') }}</th>
              <th>{{ t('staffRequests.table.lastActivity') }}</th>
              <th>{{ t('staffRequests.table.stage') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests" :key="item.id">
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || t('staffRequests.states.awaitingApprovalRef') }}</div>
              </td>
              <td>
                <strong>{{ intakeFullName(item.intake_details_json, item.client?.name || t('staffRequests.states.clientFallback')) }}</strong>
                <div class="muted-small">{{ item.client?.email || t('staffRequests.states.emptyValue') }}</div>
              </td>
              <td>{{ countryNameFromCode(intakeCountryCode(item.intake_details_json), locale) }}</td>
              <td>
                <div>{{ intakeFinanceType(item.intake_details_json) }}</div>
                <div class="muted-small">{{ intakeRequestedAmount(item.intake_details_json) }}</div>
              </td>
              <td>{{ item.comments_count || 0 }}</td>
              <td>{{ item.latest_activity_at ? new Date(item.latest_activity_at).toLocaleString() : t('staffRequests.states.emptyValue') }}</td>
              <td><span class="status-badge">{{ item.workflow_stage }}</span></td>
              <td>
                <RouterLink :to="{ name: 'staff-request-details', params: { id: item.id } }" class="primary-btn small-btn">{{ t('staffRequests.actions.view') }}</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</template>
