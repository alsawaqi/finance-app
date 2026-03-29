<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { listNewRequests, type AdminRequestListItem } from '@/services/adminRequests'
import { countryNameFromCode } from '@/utils/countries'
import { intakeCountryCode, intakeFinanceType, intakeFullName, intakeRequestedAmount } from '@/utils/requestIntake'

const loading = ref(true)
const errorMessage = ref('')
const requests = ref<AdminRequestListItem[]>([])

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await listNewRequests()
    requests.value = data.requests ?? []
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load new requests.'
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
        <p class="eyebrow">Admin queue</p>
        <h1>New Requests</h1>
        <p class="subtext">Review newly submitted client requests before approving them into contract preparation.</p>
      </div>
      <button class="ghost-btn" type="button" @click="load">Refresh</button>
    </div>

    <div class="panel-card">
      <div class="panel-head">
        <h2>Submitted requests</h2>
        <span class="count-pill">{{ requests.length }} total</span>
      </div>

      <p v-if="loading" class="empty-state">Loading submitted requests…</p>
      <p v-else-if="errorMessage" class="error-state">{{ errorMessage }}</p>
      <p v-else-if="!requests.length" class="empty-state">No submitted requests are waiting for review.</p>

      <div v-else class="table-wrap">
        <table class="request-table">
          <thead>
            <tr>
              <th>Request</th>
              <th>Client</th>
              <th>Country</th>
              <th>Requested Amount</th>
              <th>Finance Type</th>
              <th>Submitted</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests" :key="item.id">
              <td>
                <strong>{{ item.reference_number }}</strong>
                <div class="muted-small">{{ item.approval_reference_number || 'Awaiting approval' }}</div>
              </td>
              <td>
                <strong>{{ intakeFullName(item.intake_details_json, item.client?.name || 'Client') }}</strong>
                <div class="muted-small">{{ item.client?.email || '—' }}</div>
              </td>
              <td>{{ countryNameFromCode(intakeCountryCode(item.intake_details_json)) }}</td>
              <td>{{ intakeRequestedAmount(item.intake_details_json) }}</td>
              <td>{{ intakeFinanceType(item.intake_details_json) }}</td>
              <td>{{ item.submitted_at ? new Date(item.submitted_at).toLocaleString() : '—' }}</td>
              <td><span class="status-badge">{{ item.status }}</span></td>
              <td>
                <RouterLink :to="{ name: 'admin-request-details', params: { id: item.id } }" class="primary-btn small-btn">
                  Review
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
