<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { listNewRequests, type AdminRequestListItem } from '@/services/adminRequests'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<AdminRequestListItem[]>([])
const { t, locale } = useI18n()

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await listNewRequests()
    requests.value = data.requests ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('adminNewRequests.errors.loadFailed')
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
        <p class="eyebrow">{{ t('adminNewRequests.hero.eyebrow') }}</p>
        <h1>{{ t('adminNewRequests.hero.title') }}</h1>
        <p class="subtext">{{ t('adminNewRequests.hero.subtitle') }}</p>
      </div>
      <button class="ghost-btn" type="button" @click="load">{{ t('adminNewRequests.actions.refresh') }}</button>
    </div>

    <div class="panel-card">
      <div class="panel-head">
        <h2>{{ t('adminNewRequests.table.title') }}</h2>
        <span class="count-pill">{{ t('adminNewRequests.table.totalCount', { count: requests.length }) }}</span>
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
              <th>{{ t('adminNewRequests.table.country') }}</th>
              <th>{{ t('adminNewRequests.table.requestedAmount') }}</th>
              <th>{{ t('adminNewRequests.table.financeType') }}</th>
              <th>{{ t('adminNewRequests.table.submitted') }}</th>
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
              <td>{{ countryNameFromCode(intakeCountryCode(item.intake_details_json), locale) }}</td>
              <td>{{ intakeRequestedAmount(item.intake_details_json) }}</td>
              <td>{{ intakeFinanceType(item.intake_details_json) }}</td>
              <td>{{ item.submitted_at ? new Date(item.submitted_at).toLocaleString() : t('adminNewRequests.states.emptyValue') }}</td>
              <td><span class="status-badge">{{ item.status }}</span></td>
              <td>
                <RouterLink :to="{ name: 'admin-request-details', params: { id: item.id } }" class="primary-btn small-btn">
                  {{ t('adminNewRequests.actions.review') }}
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
