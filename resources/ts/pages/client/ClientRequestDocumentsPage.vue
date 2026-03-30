<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  getClientRequest,
  uploadClientAdditionalDocument,
  uploadClientRequiredDocument,
} from '@/services/clientPortal'

const route = useRoute()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const requestItem = ref<any | null>(null)

const requiredFiles = reactive<Record<number, File | null>>({})
const additionalFiles = reactive<Record<number, File | null>>({})
const uploadingRequired = reactive<Record<number, boolean>>({})
const uploadingAdditional = reactive<Record<number, boolean>>({})

const requiredPendingCount = computed(() => (requestItem.value?.required_documents ?? []).filter((item: any) => !item.is_uploaded).length)
const additionalPendingCount = computed(() => (requestItem.value?.additional_document_requests ?? []).filter((item: any) => item.status !== 'uploaded').length)

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getClientRequest(requestId.value)
    requestItem.value = data.request ?? null
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load request documents.'
  } finally {
    loading.value = false
  }
}

function onRequiredFileChange(stepId: number, event: Event) {
  const input = event.target as HTMLInputElement
  requiredFiles[stepId] = input.files?.[0] || null
}

function onAdditionalFileChange(documentId: number, event: Event) {
  const input = event.target as HTMLInputElement
  additionalFiles[documentId] = input.files?.[0] || null
}

async function uploadRequired(stepId: number) {
  const file = requiredFiles[stepId]
  if (!file) return

  uploadingRequired[stepId] = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await uploadClientRequiredDocument(requestId.value, {
      document_upload_step_id: stepId,
      file,
    })
    requestItem.value = data.request
    requiredFiles[stepId] = null
    successMessage.value = data.message || 'Required document uploaded successfully.'
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to upload the required document.'
  } finally {
    uploadingRequired[stepId] = false
  }
}

async function uploadAdditional(documentId: number) {
  const file = additionalFiles[documentId]
  if (!file) return

  uploadingAdditional[documentId] = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await uploadClientAdditionalDocument(requestId.value, documentId, { file })
    requestItem.value = data.request
    additionalFiles[documentId] = null
    successMessage.value = data.message || 'Additional document uploaded successfully.'
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to upload the additional document.'
  } finally {
    uploadingAdditional[documentId] = false
  }
}

onMounted(load)
</script>

<template>
  <section class="client-shell client-request-detail-shell">
    <div class="hero-card">
      <div>
        <p class="eyebrow">Request documents</p>
        <h1>Document Uploads</h1>
        <p>Required checklist items and extra requested files are grouped separately so the page stays clear and easy to follow.</p>
      </div>
      <div class="action-stack">
        <RouterLink :to="{ name: 'client-request-details', params: { id: requestId } }" class="ghost-btn">Back to request</RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">Loading request documents…</p>
    <p v-else-if="errorMessage && !requestItem" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>

    <template v-else-if="requestItem">
      <div class="client-status-chip-grid client-status-chip-grid--summary">
        <div class="client-status-chip-card">
          <strong>{{ requestItem.workflow_stage || '—' }}</strong>
          <span>Current stage</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ requiredPendingCount }}</strong>
          <span>Required items pending</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ additionalPendingCount }}</strong>
          <span>Additional items pending</span>
        </div>
      </div>

      <div class="client-accordion-stack">
        <details class="client-accordion-card" open>
          <summary>
            <div>
              <h2>Required documents</h2>
              <p>All required checklist items must be uploaded before the request can move forward.</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div v-if="!requestItem?.can_upload_documents" class="client-empty-state client-empty-state--inner">
              <h3>Document upload not available yet</h3>
              <p class="client-muted">This request has not reached the client document upload stage yet.</p>
            </div>

            <div v-else-if="requestItem?.required_documents?.length" class="client-doc-grid">
              <article v-for="item in requestItem.required_documents" :key="item.document_upload_step_id" class="client-doc-card">
                <div class="client-card-head">
                  <div>
                    <h3>{{ item.name }}</h3>
                    <p class="client-subtext">{{ item.code || 'Required checklist item' }}</p>
                  </div>
                  <span class="client-badge" :class="item.is_uploaded ? 'client-badge--green' : 'client-badge--amber'">
                    {{ item.is_uploaded ? 'Uploaded' : 'Pending' }}
                  </span>
                </div>

                <p v-if="item.upload?.file_name" class="client-subtext">Latest file: {{ item.upload.file_name }}</p>

                <div class="client-inline-actions client-inline-actions--stackable">
                  <input type="file" class="client-form-control" @change="onRequiredFileChange(item.document_upload_step_id, $event)" />
                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingRequired[item.document_upload_step_id] || !requiredFiles[item.document_upload_step_id]"
                    @click="uploadRequired(item.document_upload_step_id)"
                  >
                    {{ uploadingRequired[item.document_upload_step_id] ? 'Uploading…' : 'Upload File' }}
                  </button>
                </div>
              </article>
            </div>

            <p v-else class="client-empty-state">No required documents are configured for this request yet.</p>
          </div>
        </details>

        <details class="client-accordion-card" open>
          <summary>
            <div>
              <h2>Additional requested documents</h2>
              <p>These appear only when staff specifically requests more documents during follow-up.</p>
            </div>
          </summary>
          <div class="client-accordion-card__body">
            <div v-if="requestItem?.additional_document_requests?.length" class="client-doc-grid">
              <article v-for="item in requestItem.additional_document_requests" :key="item.id" class="client-doc-card">
                <div class="client-card-head">
                  <div>
                    <h3>{{ item.title }}</h3>
                    <p class="client-subtext">{{ item.reason || 'No reason added.' }}</p>
                  </div>
                  <span class="client-badge" :class="item.status === 'uploaded' ? 'client-badge--green' : 'client-badge--amber'">
                    {{ item.status }}
                  </span>
                </div>

                <p v-if="item.file_name" class="client-subtext">Latest file: {{ item.file_name }}</p>
                <p v-if="item.rejection_reason" class="client-form-error">{{ item.rejection_reason }}</p>

                <div class="client-inline-actions client-inline-actions--stackable">
                  <input type="file" class="client-form-control" @change="onAdditionalFileChange(item.id, $event)" />
                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingAdditional[item.id] || !additionalFiles[item.id]"
                    @click="uploadAdditional(item.id)"
                  >
                    {{ uploadingAdditional[item.id] ? 'Uploading…' : 'Upload File' }}
                  </button>
                </div>
              </article>
            </div>
            <p v-else class="client-empty-state">No additional documents have been requested yet.</p>
          </div>
        </details>
      </div>
    </template>
  </section>
</template>
