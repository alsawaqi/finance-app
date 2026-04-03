<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  request: any
  requiredDocuments?: any[]
}>(), {
  requiredDocuments: () => [],
})

const collectionStats = computed(() => [
  { label: 'Answers', value: props.request?.answers?.length ?? 0 },
  { label: 'Attachments', value: props.request?.attachments?.length ?? 0 },
  { label: 'Required documents', value: props.requiredDocuments?.length ?? 0 },
  { label: 'Additional documents', value: props.request?.additional_documents?.length ?? 0 },
  { label: 'Shareholders', value: props.request?.shareholders?.length ?? 0 },
  { label: 'Assignments', value: props.request?.assignments?.length ?? 0 },
  { label: 'Staff questions', value: props.request?.staff_questions?.length ?? 0 },
  { label: 'Update batches', value: props.request?.update_batches?.length ?? 0 },
  { label: 'Allowed agents', value: (props.request?.agent_assignments ?? []).filter((item: any) => item?.is_active !== false).length },
  { label: 'Comments', value: props.request?.comments?.length ?? 0 },
  { label: 'Emails', value: props.request?.emails?.length ?? 0 },
  { label: 'Timeline events', value: props.request?.timeline?.length ?? 0 },
])

const activeAssignments = computed(() => (props.request?.assignments ?? []).slice(0, 5))
const additionalDocuments = computed(() => (props.request?.additional_documents ?? []).slice(0, 5))
const shareholders = computed(() => (props.request?.shareholders ?? []).slice(0, 5))
const requiredDocuments = computed(() => (props.requiredDocuments ?? []).slice(0, 5))
const staffQuestions = computed(() => (props.request?.staff_questions ?? []).slice(0, 5))
const agentAssignments = computed(() => (props.request?.agent_assignments ?? []).filter((item: any) => item?.is_active !== false).slice(0, 5))

function statusText(value: unknown) {
  return value === null || value === undefined || value === '' ? '—' : String(value)
}
</script>

<template>
  <article class="panel-card">
    <div class="panel-head">
      <div>
        <h2>Related records overview</h2>
        <p class="subtext">Shared snapshot of the collections linked to this finance request.</p>
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
        <h3>Assignments</h3>
        <div v-if="activeAssignments.length" class="assignment-chip-list assignment-chip-list--stacked">
          <div v-for="assignment in activeAssignments" :key="assignment.id" class="assignment-chip">
            <strong>{{ assignment.staff?.name || 'Unknown staff' }}</strong>
            <span>{{ assignment.is_primary ? 'Primary' : assignment.assignment_role || 'Assigned' }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No staff assignments recorded.</p>
      </section>

      <section class="request-related-section">
        <h3>Required documents</h3>
        <div v-if="requiredDocuments.length" class="timeline-list">
          <div v-for="item in requiredDocuments" :key="item.document_upload_step_id" class="timeline-item">
            <strong>{{ item.name }}</strong>
            <p>{{ item.upload?.file_name || 'No upload yet' }}</p>
            <span>{{ item.is_change_requested ? 'Change requested' : item.is_uploaded ? 'Uploaded' : 'Pending' }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No required documents configured.</p>
      </section>

      <section class="request-related-section">
        <h3>Additional documents</h3>
        <div v-if="additionalDocuments.length" class="timeline-list">
          <div v-for="item in additionalDocuments" :key="item.id" class="timeline-item">
            <strong>{{ item.title || 'Additional document' }}</strong>
            <p>{{ item.reason || 'No reason added.' }}</p>
            <span>{{ statusText(item.status) }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No additional documents requested.</p>
      </section>

      <section class="request-related-section">
        <h3>Shareholders</h3>
        <div v-if="shareholders.length" class="timeline-list">
          <div v-for="item in shareholders" :key="item.id" class="timeline-item">
            <strong>{{ item.shareholder_name }}</strong>
            <p>{{ item.id_number || item.phone_number || 'No extra shareholder identifiers.' }}</p>
            <span>{{ item.id_file_name || 'No ID file attached' }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No shareholders recorded.</p>
      </section>

      <section class="request-related-section">
        <h3>Staff questions</h3>
        <div v-if="staffQuestions.length" class="timeline-list">
          <div v-for="item in staffQuestions" :key="item.id" class="timeline-item">
            <strong>{{ item.question_text_en || item.question_text_ar || item.template?.question_text_en || 'Staff question' }}</strong>
            <p>{{ item.answer_text || item.review_note || 'No answer yet.' }}</p>
            <span>{{ statusText(item.status) }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No staff questions created.</p>
      </section>

      <section class="request-related-section">
        <h3>Allowed bank agents</h3>
        <div v-if="agentAssignments.length" class="timeline-list">
          <div v-for="item in agentAssignments" :key="item.id" class="timeline-item">
            <strong>{{ item.agent?.name || 'Agent' }}</strong>
            <p>{{ item.bank?.name || item.agent?.company_name || 'No bank linked.' }}</p>
            <span>{{ item.is_active ? 'Active' : 'Inactive' }}</span>
          </div>
        </div>
        <p v-else class="empty-state">No allowed bank agents assigned.</p>
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
