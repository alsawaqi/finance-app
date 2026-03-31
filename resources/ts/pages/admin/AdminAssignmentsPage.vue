<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  listReadyForAssignment,
  type AssignmentReadyRequest,
} from '@/services/adminRequests'
import { intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<AssignmentReadyRequest[]>([])
const { t } = useI18n()

const signedCount = computed(() => requests.value.filter((item) => Boolean(item.current_contract?.client_signed_at)).length)

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await listReadyForAssignment()
    requests.value = data.requests ?? []
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
        <strong>{{ requests.length }}</strong>
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
        <span class="count-pill">{{ t('adminAssignments.table.count', { count: requests.length }) }}</span>
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
              <td>
                <div>{{ intakeFinanceType(item.intake_details_json) }}</div>
                <div class="muted-small">{{ intakeRequestedAmount(item.intake_details_json) }}</div>
              </td>
              <td>{{ item.current_contract?.client_signed_at ? new Date(item.current_contract.client_signed_at).toLocaleString() : t('adminAssignments.states.pending') }}</td>
              <td>
                <span class="status-badge">{{ item.workflow_stage }}</span>
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
    </article>
  </section>
</template>
