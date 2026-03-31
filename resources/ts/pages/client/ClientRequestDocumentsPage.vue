<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
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
const { t } = useI18n()

const uploadingRequired = ref<Record<number, boolean>>({})
const uploadingAdditional = ref<Record<number, boolean>>({})

const requiredPendingCount = computed(() =>
  (requestItem.value?.required_documents ?? []).filter((item: any) => !item.is_uploaded).length,
)

const additionalPendingCount = computed(() =>
  (requestItem.value?.additional_document_requests ?? []).filter((item: any) => item.status !== 'uploaded').length,
)

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getClientRequest(requestId.value)
    requestItem.value = data.request ?? null
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('clientDocuments.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

function openRequiredPicker(stepId: number) {
  const input = document.getElementById(`required-file-${stepId}`) as HTMLInputElement | null
  input?.click()
}

function openAdditionalPicker(documentId: number) {
  const input = document.getElementById(`additional-file-${documentId}`) as HTMLInputElement | null
  input?.click()
}

 

async function onRequiredFileChange(stepId: number, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  uploadingRequired.value = {
    ...uploadingRequired.value,
    [stepId]: true,
  }

  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await uploadClientRequiredDocument(requestId.value, {
      document_upload_step_id: stepId,
      file,
    })

    successMessage.value = data.message || t('clientDocuments.success.requiredUploaded')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('clientDocuments.errors.requiredUploadFailed')
  } finally {
    uploadingRequired.value = {
      ...uploadingRequired.value,
      [stepId]: false,
    }

    input.value = ''
  }
}

 
 async function onAdditionalFileChange(documentId: number, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  uploadingAdditional.value = {
    ...uploadingAdditional.value,
    [documentId]: true,
  }

  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await uploadClientAdditionalDocument(requestId.value, documentId, { file })

    successMessage.value = data.message || t('clientDocuments.success.additionalUploaded')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || t('clientDocuments.errors.additionalUploadFailed')
  } finally {
    uploadingAdditional.value = {
      ...uploadingAdditional.value,
      [documentId]: false,
    }

    input.value = ''
  }
}

onMounted(load)
</script>

<template>
  <section class="client-shell client-request-detail-shell">
    <div class="hero-card">
      <div>
        <p class="eyebrow">{{ t('clientDocuments.hero.eyebrow') }}</p>
        <h1>{{ t('clientDocuments.hero.title') }}</h1>
        <p>{{ t('clientDocuments.hero.subtitle') }}</p>
      </div>
      <div class="action-stack">
        <RouterLink :to="{ name: 'client-request-details', params: { id: requestId } }" class="ghost-btn">
          {{ t('clientDocuments.hero.backToRequest') }}
        </RouterLink>
      </div>
    </div>

    <p v-if="loading" class="empty-state">{{ t('clientDocuments.states.loading') }}</p>
    <p v-else-if="errorMessage && !requestItem" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>

    <template v-else-if="requestItem">
      <div class="client-status-chip-grid client-status-chip-grid--summary">
        <div class="client-status-chip-card">
          <strong>{{ requestItem.workflow_stage || t('clientDocuments.states.emptyValue') }}</strong>
          <span>{{ t('clientDocuments.summary.currentStage') }}</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ requiredPendingCount }}</strong>
          <span>{{ t('clientDocuments.summary.requiredPending') }}</span>
        </div>
        <div class="client-status-chip-card">
          <strong>{{ additionalPendingCount }}</strong>
          <span>{{ t('clientDocuments.summary.additionalPending') }}</span>
        </div>
      </div>

      <div class="client-accordion-stack">
        <details class="client-accordion-card" open>
          <summary>
            <div>
              <h2>{{ t('clientDocuments.sections.requiredTitle') }}</h2>
              <p>{{ t('clientDocuments.sections.requiredSubtitle') }}</p>
            </div>
          </summary>

          <div class="client-accordion-card__body">
            <div v-if="!requestItem?.can_upload_documents" class="client-empty-state client-empty-state--inner">
              <h3>{{ t('clientDocuments.states.uploadUnavailableTitle') }}</h3>
              <p class="client-muted">{{ t('clientDocuments.states.uploadUnavailableBody') }}</p>
            </div>

            <div v-else-if="requestItem?.required_documents?.length" class="client-doc-grid">
              <article
                v-for="item in requestItem.required_documents"
                :key="item.document_upload_step_id"
                class="client-doc-card"
              >
                <div class="client-card-head">
                  <div>
                    <h3>{{ item.name }}</h3>
                    <p class="client-subtext">{{ item.code || t('clientDocuments.states.requiredChecklistItem') }}</p>
                  </div>

                  <span
                    class="client-badge"
                    :class="item.is_change_requested ? 'client-badge--amber' : item.is_uploaded ? 'client-badge--green' : 'client-badge--amber'"
                  >
                    {{
                      item.is_change_requested
                        ? t('clientDocuments.states.changeRequested')
                        : item.is_uploaded
                          ? t('clientDocuments.states.uploaded')
                          : t('clientDocuments.states.pending')
                    }}
                  </span>
                </div>

                <p v-if="item.upload?.file_name" class="client-subtext">
                  {{ t('clientDocuments.states.latestFile') }}: {{ item.upload.file_name }}
                </p>

                <p v-if="item.rejection_reason" class="client-form-error">{{ item.rejection_reason }}</p>

                <p v-if="item.is_uploaded && !item.can_client_upload" class="client-subtext">
                  {{ t('clientDocuments.states.lockedAfterUpload') }}
                </p>

                <div v-if="item.can_client_upload" class="client-inline-actions client-inline-actions--stackable">
                  <input
                    :id="`required-file-${item.document_upload_step_id}`"
                    type="file"
                    class="sr-only"
                    @change="onRequiredFileChange(item.document_upload_step_id, $event)"
                  />

                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingRequired[item.document_upload_step_id]"
                    @click="openRequiredPicker(item.document_upload_step_id)"
                  >
                    {{
                      uploadingRequired[item.document_upload_step_id]
                        ? t('clientDocuments.actions.uploading')
                        : item.is_change_requested
                          ? t('clientDocuments.actions.uploadCorrected')
                          : t('clientDocuments.actions.uploadFile')
                    }}
                  </button>
                </div>
              </article>
            </div>

            <p v-else class="client-empty-state">{{ t('clientDocuments.states.noRequiredConfigured') }}</p>
          </div>
        </details>

        <details class="client-accordion-card" open>
          <summary>
            <div>
              <h2>{{ t('clientDocuments.sections.additionalTitle') }}</h2>
              <p>{{ t('clientDocuments.sections.additionalSubtitle') }}</p>
            </div>
          </summary>

          <div class="client-accordion-card__body">
            <div v-if="requestItem?.additional_document_requests?.length" class="client-doc-grid">
              <article
                v-for="item in requestItem.additional_document_requests"
                :key="item.id"
                class="client-doc-card"
              >
                <div class="client-card-head">
                  <div>
                    <h3>{{ item.title }}</h3>
                    <p class="client-subtext">{{ item.reason || t('clientDocuments.states.noReasonAdded') }}</p>
                  </div>

                  <span
                    class="client-badge"
                    :class="item.status === 'uploaded' ? 'client-badge--green' : 'client-badge--amber'"
                  >
                    {{ item.status }}
                  </span>
                </div>

                <p v-if="item.file_name" class="client-subtext">
                  {{ t('clientDocuments.states.latestFile') }}: {{ item.file_name }}
                </p>

                <p v-if="item.rejection_reason" class="client-form-error">{{ item.rejection_reason }}</p>

                <div class="client-inline-actions client-inline-actions--stackable">
                  <input
                    :id="`additional-file-${item.id}`"
                    type="file"
                    class="sr-only"
                    @change="onAdditionalFileChange(item.id, $event)"
                  />

                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingAdditional[item.id]"
                    @click="openAdditionalPicker(item.id)"
                  >
                    {{
                      uploadingAdditional[item.id]
                        ? t('clientDocuments.actions.uploading')
                        : t('clientDocuments.actions.uploadFile')
                    }}
                  </button>
                </div>
              </article>
            </div>

            <p v-else class="client-empty-state">{{ t('clientDocuments.states.noAdditionalRequested') }}</p>
          </div>
        </details>
      </div>
    </template>
  </section>
</template>