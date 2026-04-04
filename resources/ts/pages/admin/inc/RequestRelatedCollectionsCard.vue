<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(defineProps<{
  request: any
  requiredDocuments?: any[]
}>(), {
  requiredDocuments: () => [],
})

const { t, locale } = useI18n()

const collectionStats = computed(() => [
  { label: t('adminRequestDetails.related.stats.answers'), value: props.request?.answers?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.attachments'), value: props.request?.attachments?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.requiredDocuments'), value: props.requiredDocuments?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.additionalDocuments'), value: props.request?.additional_documents?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.shareholders'), value: props.request?.shareholders?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.assignments'), value: props.request?.assignments?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.staffQuestions'), value: props.request?.staff_questions?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.updateBatches'), value: props.request?.update_batches?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.allowedAgents'), value: (props.request?.agent_assignments ?? []).filter((item: any) => item?.is_active !== false).length },
  { label: t('adminRequestDetails.related.stats.comments'), value: props.request?.comments?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.emails'), value: props.request?.emails?.length ?? 0 },
  { label: t('adminRequestDetails.related.stats.timelineEvents'), value: props.request?.timeline?.length ?? 0 },
])

const activeAssignments = computed(() => (props.request?.assignments ?? []).slice(0, 5))
const additionalDocuments = computed(() => (props.request?.additional_documents ?? []).slice(0, 5))
const shareholders = computed(() => (props.request?.shareholders ?? []).slice(0, 5))
const requiredDocuments = computed(() => (props.requiredDocuments ?? []).slice(0, 5))
const staffQuestions = computed(() => (props.request?.staff_questions ?? []).slice(0, 5))
const agentAssignments = computed(() => (props.request?.agent_assignments ?? []).filter((item: any) => item?.is_active !== false).slice(0, 5))

function statusText(value: unknown) {
  return value === null || value === undefined || value === '' ? t('adminRequestDetails.states.emptyValue') : String(value)
}

function localizedQuestionTitle(item: any) {
  const ar = item?.question_text_ar || item?.template?.question_text_ar
  const en = item?.question_text_en || item?.template?.question_text_en
  return locale.value === 'ar'
    ? (ar || en || t('adminRequestDetails.states.studyQuestion'))
    : (en || ar || t('adminRequestDetails.states.studyQuestion'))
}
</script>

<template>
  <article class="panel-card">
    <div class="panel-head">
      <div>
        <h2>{{ t('adminRequestDetails.related.title') }}</h2>
        <p class="subtext">{{ t('adminRequestDetails.related.subtitle') }}</p>
      </div>
    </div>

    <div class="catalog-mini-stats" style="margin-bottom: 1rem;">
      <div v-for="item in collectionStats" :key="item.label">
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
      </div>
    </div>

    <div class="request-related-grid">
      <section class="request-related-section">
        <h3>{{ t('adminRequestDetails.related.assignments') }}</h3>
        <div v-if="activeAssignments.length" class="assignment-chip-list assignment-chip-list--stacked">
          <div v-for="assignment in activeAssignments" :key="assignment.id" class="assignment-chip">
            <strong>{{ assignment.staff?.name || t('adminRequestDetails.related.unknownStaff') }}</strong>
            <span>{{ assignment.is_primary ? t('adminRequestDetails.related.primary') : assignment.assignment_role || t('adminRequestDetails.related.assigned') }}</span>
          </div>
        </div>
        <p v-else class="empty-state">{{ t('adminRequestDetails.related.noStaffAssignments') }}</p>
      </section>

      <section class="request-related-section">
        <h3>{{ t('adminRequestDetails.related.requiredDocuments') }}</h3>
        <div v-if="requiredDocuments.length" class="timeline-list">
          <div v-for="item in requiredDocuments" :key="item.document_upload_step_id" class="timeline-item">
            <strong>{{ item.name }}</strong>
            <p>{{ item.upload?.file_name || t('adminRequestDetails.related.noUploadYet') }}</p>
            <span>{{ item.is_change_requested ? t('adminRequestDetails.requiredDocuments.status.changeRequested') : item.is_uploaded ? t('adminRequestDetails.requiredDocuments.status.uploaded') : t('adminRequestDetails.requiredDocuments.status.pending') }}</span>
          </div>
        </div>
        <p v-else class="empty-state">{{ t('adminRequestDetails.related.noRequiredDocuments') }}</p>
      </section>

      <section class="request-related-section">
        <h3>{{ t('adminRequestDetails.related.additionalDocuments') }}</h3>
        <div v-if="additionalDocuments.length" class="timeline-list">
          <div v-for="item in additionalDocuments" :key="item.id" class="timeline-item">
            <strong>{{ item.title || t('adminRequestDetails.additionalDocuments.fallbackTitle') }}</strong>
            <p>{{ item.reason || t('adminRequestDetails.additionalDocuments.noReason') }}</p>
            <span>{{ statusText(item.status) }}</span>
          </div>
        </div>
        <p v-else class="empty-state">{{ t('adminRequestDetails.related.noAdditionalDocuments') }}</p>
      </section>

      <section class="request-related-section">
        <h3>{{ t('adminRequestDetails.related.shareholders') }}</h3>
        <div v-if="shareholders.length" class="timeline-list">
          <div v-for="item in shareholders" :key="item.id" class="timeline-item">
            <strong>{{ item.shareholder_name }}</strong>
            <p>{{ item.id_number || item.phone_number || t('adminRequestDetails.related.noShareholderIdentifiers') }}</p>
            <span>{{ item.id_file_name || t('adminRequestDetails.related.noIdFileAttached') }}</span>
          </div>
        </div>
        <p v-else class="empty-state">{{ t('adminRequestDetails.related.noShareholders') }}</p>
      </section>

      <section class="request-related-section">
        <h3>{{ t('adminRequestDetails.related.staffQuestions') }}</h3>
        <div v-if="staffQuestions.length" class="timeline-list">
          <div v-for="item in staffQuestions" :key="item.id" class="timeline-item">
            <strong>{{ localizedQuestionTitle(item) }}</strong>
            <p>{{ item.answer_text || item.review_note || t('adminRequestDetails.related.noAnswerYet') }}</p>
            <span>{{ statusText(item.status) }}</span>
          </div>
        </div>
        <p v-else class="empty-state">{{ t('adminRequestDetails.related.noStaffQuestions') }}</p>
      </section>

      <section class="request-related-section">
        <h3>{{ t('adminRequestDetails.related.allowedBankAgents') }}</h3>
        <div v-if="agentAssignments.length" class="timeline-list">
          <div v-for="item in agentAssignments" :key="item.id" class="timeline-item">
            <strong>{{ item.agent?.name || t('adminRequestDetails.related.agent') }}</strong>
            <p>{{ item.bank?.name || item.agent?.company_name || t('adminRequestDetails.agentAssignments.noBankLinked') }}</p>
            <span>{{ item.is_active ? t('adminRequestDetails.related.active') : t('adminRequestDetails.related.inactive') }}</span>
          </div>
        </div>
        <p v-else class="empty-state">{{ t('adminRequestDetails.related.noAllowedAgents') }}</p>
      </section>
    </div>
  </article>
</template>

<style scoped>
.request-related-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1rem;
}

.request-related-section h3 {
  margin: 0 0 0.75rem;
  font-size: 0.95rem;
  font-weight: 700;
}
</style>
