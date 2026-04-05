<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  applicantTypeLabel,
  intakeAddress,
  intakeCompanyCrNumber,
  intakeEmail,
  intakeFullName,
  intakeNationalAddressNumber,
  intakePhoneDisplay,
  intakeRequestedAmount,
  intakeUnifiedNumber,
} from '@/utils/requestIntake'
import { formatDateTime } from '@/utils/dateTime'
import { formatContractStatus, formatRequestStatus } from '@/utils/requestStatus'
import { getRequestWorkflowStageMeta } from '@/utils/requestWorkflowStage'

const props = withDefaults(defineProps<{
  request: any
  requiredDocuments?: any[]
}>(), {
  requiredDocuments: () => [],
})

const { t, locale } = useI18n()
const details = computed(() => props.request?.intake_details_json ?? {})

function localizedModelValue(entity: any, base: string, fallback = '-') {
  const ar = entity?.[`${base}_ar`]
  const en = entity?.[`${base}_en`]
  return locale.value === 'ar' ? (ar || en || fallback) : (en || ar || fallback)
}

function stageMeta(stage: string | null | undefined) {
  return getRequestWorkflowStageMeta(stage)
}

const overviewRows = computed(() => [
  { label: t('adminRequestDetails.core.referenceNumber'), value: props.request?.reference_number },
  { label: t('adminRequestDetails.core.approvalReference'), value: props.request?.approval_reference_number },
  { label: t('adminRequestDetails.core.applicantType'), value: applicantTypeLabel(props.request?.applicant_type, locale, '-') },
  { label: t('adminRequestDetails.core.companyName'), value: props.request?.company_name || details.value?.company_name },
  { label: t('adminRequestDetails.core.priority'), value: props.request?.priority },
  { label: t('adminRequestDetails.core.status'), value: formatRequestStatus(props.request?.status, locale, '-') },
  { label: t('adminRequestDetails.core.workflowStage'), value: stageMeta(props.request?.workflow_stage).label },
  { label: t('adminRequestDetails.core.financeRequestType'), value: localizedModelValue(props.request?.finance_request_type, 'name') },
  { label: t('adminRequestDetails.core.requestedAmount'), value: intakeRequestedAmount(details.value, '-', true) },
  { label: t('adminRequestDetails.core.primaryStaff'), value: props.request?.primary_staff?.name },
  { label: t('adminRequestDetails.core.submittedAt'), value: formatDate(props.request?.submitted_at) },
  { label: t('adminRequestDetails.core.latestActivity'), value: formatDate(props.request?.latest_activity_at) },
])

const clientRows = computed(() => [
  { label: t('adminRequestDetails.core.clientName'), value: intakeFullName(details.value, props.request?.client?.name || '-') },
  { label: t('adminRequestDetails.core.clientEmail'), value: props.request?.client?.email || intakeEmail(details.value) },
  { label: t('adminRequestDetails.core.clientPhone'), value: props.request?.client?.phone || intakePhoneDisplay(details.value) },
  { label: t('adminRequestDetails.core.requestEmail'), value: intakeEmail(details.value) },
  { label: t('adminRequestDetails.core.requestPhone'), value: intakePhoneDisplay(details.value) },
  { label: t('adminRequestDetails.core.unifiedNumber'), value: intakeUnifiedNumber(details.value) },
  { label: t('adminRequestDetails.core.nationalAddressNumber'), value: intakeNationalAddressNumber(details.value) },
  { label: t('adminRequestDetails.core.address'), value: intakeAddress(details.value) },
  { label: t('adminRequestDetails.core.companyCrNumber'), value: intakeCompanyCrNumber(details.value) },
])

const contractRows = computed(() => [
  { label: t('adminRequestDetails.core.contractVersion'), value: props.request?.current_contract?.version_no },
  { label: t('adminRequestDetails.core.contractStatus'), value: formatContractStatus(props.request?.current_contract?.status, locale, '-') },
  { label: t('adminRequestDetails.core.adminSignedAt'), value: formatDate(props.request?.current_contract?.admin_signed_at) },
  { label: t('adminRequestDetails.core.clientSignedAt'), value: formatDate(props.request?.current_contract?.client_signed_at) },
  {
    label: t('adminRequestDetails.core.requiredDocuments'),
    value: `${props.requiredDocuments?.filter((item: any) => item?.is_uploaded).length ?? 0}/${props.requiredDocuments?.length ?? 0}`,
  },
])

function formatValue(value: unknown) {
  if (value === null || value === undefined || value === '') return '-'
  return String(value)
}

function formatDate(value: unknown) {
  return formatDateTime(value, locale, '-')
}
</script>

<template>
  <article class="panel-card">
    <div class="panel-head">
      <div>
        <h2>{{ t('adminRequestDetails.core.title') }}</h2>
        <p class="subtext">{{ t('adminRequestDetails.core.subtitle') }}</p>
      </div>
    </div>

    <div class="request-core-layout">
      <section class="request-core-section">
        <h3 class="request-core-title">{{ t('adminRequestDetails.core.requestRecord') }}</h3>
        <div class="summary-grid summary-grid--tight">
          <div v-for="row in overviewRows" :key="row.label">
            <span>{{ row.label }}</span>
            <strong>{{ formatValue(row.value) }}</strong>
          </div>
        </div>
      </section>

      <section class="request-core-section">
        <h3 class="request-core-title">{{ t('adminRequestDetails.core.clientAndIntake') }}</h3>
        <div class="summary-grid summary-grid--tight">
          <div v-for="row in clientRows" :key="row.label">
            <span>{{ row.label }}</span>
            <strong>{{ formatValue(row.value) }}</strong>
          </div>
        </div>
      </section>

      <section class="request-core-section">
        <h3 class="request-core-title">{{ t('adminRequestDetails.core.contractAndChecklist') }}</h3>
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
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.request-core-section {
  display: grid;
  gap: 0.9rem;
  min-width: 0;
  padding: 1rem;
  border: 1px solid rgba(148, 163, 184, 0.14);
  border-radius: 20px;
  background: rgba(244, 247, 255, 0.72);
}

.request-core-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
}

.request-core-section .summary-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.7rem;
}

.request-core-section .summary-grid > div {
  min-height: 74px;
  align-content: center;
}

@media (max-width: 1280px) {
  .request-core-layout {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 820px) {
  .request-core-layout {
    grid-template-columns: 1fr;
  }

  .request-core-section .summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
