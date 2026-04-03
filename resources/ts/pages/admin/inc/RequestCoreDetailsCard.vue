<script setup lang="ts">
import { computed } from 'vue'
import {
  intakeAddress,
  intakeCompanyCrNumber,
  intakeEmail,
  intakeFullName,
  intakeNationalAddressNumber,
  intakePhoneDisplay,
  intakeRequestedAmount,
  intakeUnifiedNumber,
} from '@/utils/requestIntake'

const props = withDefaults(defineProps<{
  request: any
  requiredDocuments?: any[]
}>(), {
  requiredDocuments: () => [],
})

const details = computed(() => props.request?.intake_details_json ?? {})

const overviewRows = computed(() => [
  { label: 'Reference number', value: props.request?.reference_number },
  { label: 'Approval reference', value: props.request?.approval_reference_number },
  { label: 'Applicant type', value: props.request?.applicant_type },
  { label: 'Company name', value: props.request?.company_name || details.value?.company_name },
  { label: 'Priority', value: props.request?.priority },
  { label: 'Status', value: props.request?.status },
  { label: 'Workflow stage', value: props.request?.workflow_stage },
  { label: 'Finance request type', value: props.request?.finance_request_type?.name_en || props.request?.finance_request_type?.name_ar },
  { label: 'Requested amount', value: intakeRequestedAmount(details.value) },
  { label: 'Primary staff', value: props.request?.primary_staff?.name },
  { label: 'Submitted at', value: formatDate(props.request?.submitted_at) },
  { label: 'Latest activity', value: formatDate(props.request?.latest_activity_at) },
])

const clientRows = computed(() => [
  { label: 'Client name', value: intakeFullName(details.value, props.request?.client?.name || '—') },
  { label: 'Client email', value: props.request?.client?.email || intakeEmail(details.value) },
  { label: 'Client phone', value: props.request?.client?.phone || intakePhoneDisplay(details.value) },
  { label: 'Request email', value: intakeEmail(details.value) },
  { label: 'Request phone', value: intakePhoneDisplay(details.value) },
  { label: 'Unified number', value: intakeUnifiedNumber(details.value) },
  { label: 'National address number', value: intakeNationalAddressNumber(details.value) },
  { label: 'Address', value: intakeAddress(details.value) },
  { label: 'Company CR number', value: intakeCompanyCrNumber(details.value) },
])

const contractRows = computed(() => [
  { label: 'Contract version', value: props.request?.current_contract?.version_no },
  { label: 'Contract status', value: props.request?.current_contract?.status },
  { label: 'Admin signed at', value: formatDate(props.request?.current_contract?.admin_signed_at) },
  { label: 'Client signed at', value: formatDate(props.request?.current_contract?.client_signed_at) },
  { label: 'Required documents', value: `${props.requiredDocuments?.filter((item: any) => item?.is_uploaded).length ?? 0}/${props.requiredDocuments?.length ?? 0}` },
])

function formatValue(value: unknown) {
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

function formatDate(value: unknown) {
  if (!value) return '—'

  const date = new Date(String(value))
  if (Number.isNaN(date.getTime())) return '—'

  return date.toLocaleString()
}
</script>

<template>
  <article class="panel-card">
    <div class="panel-head">
      <div>
        <h2>Request overview</h2>
        <p class="subtext">Shared request information from the finance request record and its main related entities.</p>
      </div>
    </div>

    <div class="request-core-layout">
      <section>
        <h3 class="request-core-title">Request record</h3>
        <div class="summary-grid summary-grid--tight">
          <div v-for="row in overviewRows" :key="row.label">
            <span>{{ row.label }}</span>
            <strong>{{ formatValue(row.value) }}</strong>
          </div>
        </div>
      </section>

      <section>
        <h3 class="request-core-title">Client and intake details</h3>
        <div class="summary-grid summary-grid--tight">
          <div v-for="row in clientRows" :key="row.label">
            <span>{{ row.label }}</span>
            <strong>{{ formatValue(row.value) }}</strong>
          </div>
        </div>
      </section>

      <section>
        <h3 class="request-core-title">Contract and checklist state</h3>
        <div class="summary-grid summary-grid--tight">
          <div v-for="row in contractRows" :key="row.label">
            <span>{{ row.label }}</span>
            <strong>{{ formatValue(row.value) }}</strong>
          </div>
        </div>
      </section>
    </div>
  </article>
</template>

<style scoped>
.request-core-layout {
  display: grid;
  gap: 1rem;
}

.request-core-title {
  margin: 0 0 0.75rem;
  font-size: 0.95rem;
  font-weight: 700;
}
</style>
