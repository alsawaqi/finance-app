<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppFilePreviewModal from '@/components/AppFilePreviewModal.vue'
import {
  getClientRequest,
  submitClientUpdateFile,
  uploadClientAdditionalDocument,
  uploadClientRequiredDocument,
} from '@/services/clientPortal'
import { getClientWorkflowStageMeta } from '@/utils/clientRequestStage'

const route = useRoute()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const requestItem = ref<any | null>(null)
const { t, locale } = useI18n()
const isArabic = computed(() => locale.value === 'ar')
const emptyValueLabel = computed(() => t('clientDocuments.states.emptyValue'))

const uploadingRequired = ref<Record<number, boolean>>({})
const uploadingAdditional = ref<Record<number, boolean>>({})
const uploadingUpdateFiles = ref<Record<number, boolean>>({})

const requiredFileInputs = ref<Record<number, HTMLInputElement | null>>({})
const additionalFileInputs = ref<Record<number, HTMLInputElement | null>>({})
const updateFileInputs = ref<Record<number, HTMLInputElement | null>>({})

type PendingUpload = {
  kind: 'required' | 'additional' | 'update'
  id: number
  file: File
  title: string
}

const pendingUpload = ref<PendingUpload | null>(null)
const uploadPreviewOpen = ref(false)

const requiredPendingCount = computed(() =>
  (requestItem.value?.required_documents ?? []).filter((item: any) => !item.is_uploaded).length,
)

const additionalPendingCount = computed(() =>
  (requestItem.value?.additional_document_requests ?? []).filter((item: any) => item.status !== 'uploaded').length,
)

const activeUpdateBatch = computed(() => requestItem.value?.active_update_batch ?? null)
const fileUpdateItems = computed(() => (activeUpdateBatch.value?.items ?? []).filter((item: any) => item.item_type === 'attachment'))
const pendingFileUpdateCount = computed(() => fileUpdateItems.value.filter((item: any) => item.status !== 'approved').length)

function uiText(en: string, ar: string) {
  return isArabic.value ? ar : en
}

function localizedText(en?: string | null, ar?: string | null, fallback?: string) {
  const resolvedFallback = fallback ?? emptyValueLabel.value
  if (isArabic.value) return ar || en || resolvedFallback
  return en || ar || resolvedFallback
}

function stageMeta(stage: string | null | undefined) {
  return getClientWorkflowStageMeta(stage)
}

function updateStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'updated') return uiText('Submitted for review', 'تم الإرسال للمراجعة')
  if (key === 'approved') return uiText('Approved', 'معتمد')
  if (key === 'rejected') return uiText('Needs another upload', 'يتطلب إعادة الرفع')
  if (key === 'pending') return uiText('Waiting for your file', 'بانتظار ملفك')
  return key || uiText('Unknown', 'غير معروف')
}

function updateStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return 'client-badge client-badge--green'
  if (key === 'updated') return 'client-badge client-badge--blue'
  if (key === 'rejected') return 'client-badge client-badge--red'
  return 'client-badge client-badge--amber'
}

function updateUploadButtonLabel(item: any) {
  if (uploadingUpdateFiles.value[item.id]) return t('clientDocuments.actions.uploading')
  if (item.status === 'rejected') return uiText('Upload replacement again', 'إعادة رفع الملف البديل')
  if (item.status === 'updated') return uiText('Upload revised file', 'رفع ملف معدل')
  return t('clientDocuments.actions.uploadFile')
}

function additionalStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'uploaded') return t('clientDocuments.states.uploaded')
  if (key === 'pending') return t('clientDocuments.states.pending')
  if (key === 'rejected') return uiText('Rejected', 'مرفوض')
  return key || emptyValueLabel.value
}

function additionalStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'uploaded') return 'client-badge--green'
  if (key === 'rejected') return 'client-badge--red'
  return 'client-badge--amber'
}

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

function setRequiredFileRef(stepId: number, el: Element | null) {
  requiredFileInputs.value[stepId] = el as HTMLInputElement | null
}

function setAdditionalFileRef(documentId: number, el: Element | null) {
  additionalFileInputs.value[documentId] = el as HTMLInputElement | null
}

function setUpdateFileRef(itemId: number, el: Element | null) {
  updateFileInputs.value[itemId] = el as HTMLInputElement | null
}

function openRequiredPicker(stepId: number) {
  requiredFileInputs.value[stepId]?.click()
}

function openAdditionalPicker(documentId: number) {
  additionalFileInputs.value[documentId]?.click()
}

function openUpdatePicker(itemId: number) {
  updateFileInputs.value[itemId]?.click()
}

function canUploadAdditional(item: any) {
  if (typeof item?.can_client_upload === 'boolean') return item.can_client_upload
  return ['pending', 'rejected'].includes(String(item?.status || '').toLowerCase())
}

function additionalUploadButtonLabel(item: any) {
  if (uploadingAdditional.value[item.id]) return t('clientDocuments.actions.uploading')
  if (String(item?.status || '').toLowerCase() === 'rejected') return t('clientDocuments.actions.uploadCorrected')
  if (!canUploadAdditional(item)) return t('clientDocuments.states.uploaded')
  return t('clientDocuments.actions.uploadFile')
}

function requiredUploadButtonLabel(item: any) {
  if (uploadingRequired.value[item.document_upload_step_id]) return t('clientDocuments.actions.uploading')
  if (item.is_change_requested) return t('clientDocuments.actions.uploadCorrected')
  if (!item.can_client_upload && item.is_uploaded) return t('clientDocuments.states.uploaded')
  return t('clientDocuments.actions.uploadFile')
}

function applyRequestFromResponse(data: any) {
  if (data?.request) {
    requestItem.value = data.request
  }
}

function clearUploadPreview() {
  uploadPreviewOpen.value = false
  pendingUpload.value = null
}

async function onRequiredFileChange(stepId: number, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  pendingUpload.value = { kind: 'required', id: stepId, file, title: t('clientDocuments.sections.requiredTitle') }
  uploadPreviewOpen.value = true

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

    applyRequestFromResponse(data)
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

  pendingUpload.value = { kind: 'additional', id: documentId, file, title: t('clientDocuments.sections.additionalTitle') }
  uploadPreviewOpen.value = true

  uploadingAdditional.value = {
    ...uploadingAdditional.value,
    [documentId]: true,
  }

  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await uploadClientAdditionalDocument(requestId.value, documentId, { file })

    applyRequestFromResponse(data)
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

async function onUpdateFileChange(itemId: number, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  pendingUpload.value = { kind: 'update', id: itemId, file, title: uiText('Requested file replacement', 'استبدال ملف مطلوب') }
  uploadPreviewOpen.value = true

  uploadingUpdateFiles.value = {
    ...uploadingUpdateFiles.value,
    [itemId]: true,
  }

  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await submitClientUpdateFile(requestId.value, itemId, { file })
    applyRequestFromResponse(data)
    successMessage.value = data.message || uiText('Requested file update submitted successfully.', 'تم إرسال تحديث الملف المطلوب بنجاح.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Unable to submit the requested file update.', 'تعذر إرسال تحديث الملف المطلوب.')
  } finally {
    uploadingUpdateFiles.value = {
      ...uploadingUpdateFiles.value,
      [itemId]: false,
    }
    input.value = ''
  }
}

onMounted(load)
</script>

<template>
  <section class="client-shell client-request-detail-shell">
    <div class="hero-card client-reveal-up">
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
    <p v-if="errorMessage && requestItem" class="error-state">{{ errorMessage }}</p>

    <template v-else-if="requestItem">
      <div class="client-status-chip-grid client-status-chip-grid--summary client-reveal-up">
        <div class="client-status-chip-card">
          <strong>
            <span class="client-stage-badge" :class="stageMeta(requestItem.workflow_stage).className">{{ stageMeta(requestItem.workflow_stage).label }}</span>
          </strong>
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
        <div class="client-status-chip-card">
          <strong>{{ pendingFileUpdateCount }}</strong>
          <span>{{ uiText('Requested file updates', 'تحديثات الملفات المطلوبة') }}</span>
        </div>
      </div>

      <div class="client-accordion-stack">
        <details v-if="fileUpdateItems.length" class="client-accordion-card client-reveal-left" open>
          <summary>
            <div>
              <h2>{{ uiText('Requested file replacements', 'استبدال الملفات المطلوبة') }}</h2>
              <p>{{ uiText('Upload only the files the team specifically asked you to replace.', 'قم برفع الملفات التي طلب الفريق استبدالها فقط.') }}</p>
            </div>
          </summary>

          <div class="client-accordion-card__body">
            <div v-if="localizedText(activeUpdateBatch?.reason_en, activeUpdateBatch?.reason_ar, '') !== ''" class="notes-box">
              <span>{{ uiText('Reason from the team', 'ملاحظة الفريق') }}</span>
              <p>{{ localizedText(activeUpdateBatch?.reason_en, activeUpdateBatch?.reason_ar, emptyValueLabel) }}</p>
            </div>

            <div class="client-doc-grid client-doc-grid--detail">
              <article v-for="item in fileUpdateItems" :key="item.id" class="client-doc-card client-update-card">
                <div class="client-card-head">
                  <div>
                    <h3>{{ localizedText(item.label_en, item.label_ar, uiText('Requested file update', 'تحديث ملف مطلوب')) }}</h3>
                    <p class="client-subtext">{{ localizedText(item.instruction_en, item.instruction_ar, uiText('Please upload the replacement file requested by the team.', 'يرجى رفع الملف البديل الذي طلبه الفريق.')) }}</p>
                  </div>

                  <span :class="updateStatusClass(item.status)">{{ updateStatusLabel(item.status) }}</span>
                </div>

                <p class="client-subtext" v-if="item.old_value_json?.file_name">
                  {{ uiText('Current file', 'الملف الحالي') }}: {{ item.old_value_json.file_name }}
                </p>
                <p class="client-subtext" v-if="item.new_value_json?.file_name">
                  {{ uiText('Last submitted', 'آخر ملف مرسل') }}: {{ item.new_value_json.file_name }}
                </p>

                <div class="client-inline-actions client-inline-actions--stackable client-update-actions">
                  <input
                    :id="`update-file-${item.id}`"
                    :ref="(el) => setUpdateFileRef(item.id, el)"
                    type="file"
                    class="sr-only"
                    @change.stop="onUpdateFileChange(item.id, $event)"
                  />

                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingUpdateFiles[item.id] || item.status === 'approved' || !requestItem.can_submit_client_updates"
                    @click.stop.prevent="openUpdatePicker(item.id)"
                  >
                    {{ updateUploadButtonLabel(item) }}
                  </button>
                </div>
              </article>
            </div>
          </div>
        </details>

        <details class="client-accordion-card client-reveal-up" open>
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

            <div v-else-if="requestItem?.required_documents?.length" class="client-doc-grid client-doc-grid--detail">
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

                <div class="client-inline-actions client-inline-actions--stackable client-update-actions">
                  <input
                    :id="`required-file-${item.document_upload_step_id}`"
                    :ref="(el) => setRequiredFileRef(item.document_upload_step_id, el)"
                    type="file"
                    class="sr-only"
                    @change.stop="onRequiredFileChange(item.document_upload_step_id, $event)"
                  />

                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingRequired[item.document_upload_step_id] || !item.can_client_upload"
                    @click.stop.prevent="openRequiredPicker(item.document_upload_step_id)"
                  >
                    {{ requiredUploadButtonLabel(item) }}
                  </button>
                </div>
              </article>
            </div>

            <p v-else class="client-empty-state">{{ t('clientDocuments.states.noRequiredConfigured') }}</p>
          </div>
        </details>

        <details class="client-accordion-card client-reveal-up">
          <summary>
            <div>
              <h2>{{ t('clientDocuments.sections.additionalTitle') }}</h2>
              <p>{{ t('clientDocuments.sections.additionalSubtitle') }}</p>
            </div>
          </summary>

          <div class="client-accordion-card__body">
            <div v-if="requestItem?.additional_document_requests?.length" class="client-doc-grid client-doc-grid--detail">
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

                  <span class="client-badge" :class="additionalStatusClass(item.status)">
                    {{ additionalStatusLabel(item.status) }}
                  </span>
                </div>

                <p v-if="item.file_name" class="client-subtext">
                  {{ t('clientDocuments.states.latestFile') }}: {{ item.file_name }}
                </p>

                <p v-if="item.rejection_reason" class="client-form-error">{{ item.rejection_reason }}</p>

                <p v-if="item.file_name && !canUploadAdditional(item)" class="client-subtext">
                  {{ t('clientDocuments.states.lockedAfterUpload') }}
                </p>

                <div class="client-inline-actions client-inline-actions--stackable client-update-actions">
                  <input
                    :id="`additional-file-${item.id}`"
                    :ref="(el) => setAdditionalFileRef(item.id, el)"
                    type="file"
                    class="sr-only"
                    @change.stop="onAdditionalFileChange(item.id, $event)"
                  />

                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingAdditional[item.id] || !canUploadAdditional(item)"
                    @click.stop.prevent="openAdditionalPicker(item.id)"
                  >
                    {{ additionalUploadButtonLabel(item) }}
                  </button>
                </div>
              </article>
            </div>

            <p v-else class="client-empty-state">{{ t('clientDocuments.states.noAdditionalRequested') }}</p>
          </div>
        </details>
      </div>
    </template>

    <AppFilePreviewModal
      :model-value="uploadPreviewOpen"
      @update:model-value="(value) => { if (!value) clearUploadPreview() }"
      :title="pendingUpload?.title || uiText('Selected file preview', 'معاينة الملف المحدد')"
      :file-name="pendingUpload?.file?.name || ''"
      :mime-type="pendingUpload?.file?.type || ''"
      :local-file="pendingUpload?.file || null"
    />
  </section>
</template>
