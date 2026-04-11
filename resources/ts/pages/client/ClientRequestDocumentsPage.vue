<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import type { ComponentPublicInstance } from 'vue'
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
import { formatDateTime } from '@/utils/dateTime'

const route = useRoute()
const requestId = computed(() => route.params.id as string)

const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const requestItem = ref<any | null>(null)
const { t, locale } = useI18n()
const isArabic = computed(() => locale.value === 'ar')
const emptyValueLabel = computed(() => t('clientDocuments.states.emptyValue'))
const DEFAULT_ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']
const DEFAULT_MAX_FILE_SIZE_MB = 10

const uploadingRequired = ref<Record<number, boolean>>({})
const uploadingAdditional = ref<Record<number, boolean>>({})
const uploadingUpdateFiles = ref<Record<number, boolean>>({})
const uploadingQueued = ref(false)

const requiredFileInputs = ref<Record<number, HTMLInputElement | null>>({})
const additionalFileInputs = ref<Record<number, HTMLInputElement | null>>({})
const updateFileInputs = ref<Record<number, HTMLInputElement | null>>({})

type PendingUpload = {
  kind: 'required' | 'additional' | 'update'
  id: number
  file: File
  title: string
}

const pendingUploads = ref<PendingUpload[]>([])
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

function normalizeAllowedExtensions(raw: unknown): string[] {
  if (!Array.isArray(raw) || raw.length === 0) return DEFAULT_ALLOWED_EXTENSIONS

  const result = raw
    .map((value) => String(value || '').trim().toLowerCase())
    .map((value) => value.replace(/^\./, ''))
    .map((value) => {
      if (value.includes('/')) {
        return value.split('/').pop() || ''
      }
      return value
    })
    .filter((value) => value !== '')

  return result.length ? Array.from(new Set(result)) : DEFAULT_ALLOWED_EXTENSIONS
}

function fileExtension(file: File): string {
  const name = String(file.name || '')
  const idx = name.lastIndexOf('.')
  if (idx < 0) return ''
  return name.slice(idx + 1).toLowerCase()
}

function formatExtensionList(extensions: string[]) {
  return extensions.map((ext) => ext.toUpperCase()).join(', ')
}

function acceptFromExtensions(extensions: string[]) {
  return extensions.map((ext) => `.${ext}`).join(',')
}

function validateSelectedFile(file: File, options?: { allowedExtensions?: unknown; maxMb?: number | null }): string | null {
  const allowedExtensions = normalizeAllowedExtensions(options?.allowedExtensions)
  const ext = fileExtension(file)
  if (!ext || !allowedExtensions.includes(ext)) {
    return uiText(
      `Invalid file format. Allowed: ${formatExtensionList(allowedExtensions)}.`,
      `صيغة الملف غير مسموحة. الصيغ المسموحة: ${formatExtensionList(allowedExtensions)}.`,
    )
  }

  const maxMb = Number(options?.maxMb || DEFAULT_MAX_FILE_SIZE_MB)
  const maxBytes = maxMb * 1024 * 1024
  if (Number.isFinite(maxBytes) && maxBytes > 0 && file.size > maxBytes) {
    return uiText(
      `File is too large. Maximum size is ${maxMb} MB.`,
      `حجم الملف كبير جداً. الحد الأقصى هو ${maxMb} ميجابايت.`,
    )
  }

  return null
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
  if (hasPending('update', item.id)) return uiText('Ready to submit', 'جاهز للإرسال')
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

function requiredUploads(item: any) {
  const uploads = Array.isArray(item?.uploads) ? [...item.uploads] : []
  if (!uploads.length && item?.upload?.id) {
    uploads.push(item.upload)
  }

  return uploads
}

function requiredUploadStatusLabel(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved') return uiText('Approved', 'معتمد')
  if (key === 'rejected') return uiText('Needs another upload', 'يتطلب إعادة الرفع')
  if (key === 'uploaded') return uiText('Uploaded', 'تم الرفع')
  if (key === 'pending') return uiText('Pending', 'قيد الانتظار')
  return key || emptyValueLabel.value
}

function requiredUploadStatusClass(status: string | null | undefined) {
  const key = String(status || '').toLowerCase()
  if (key === 'approved' || key === 'uploaded') return 'client-badge--green'
  if (key === 'rejected') return 'client-badge--red'
  return 'client-badge--amber'
}

function readableDateTime(value: unknown) {
  return formatDateTime(value, locale, emptyValueLabel.value)
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

/** Vue template ref callbacks pass Element or (for components) ComponentPublicInstance. */
function refCallbackToInput(
  el: Element | ComponentPublicInstance | null,
): HTMLInputElement | null {
  if (el == null) return null
  const node = el instanceof Element ? el : el.$el
  return node instanceof HTMLInputElement ? node : null
}

function setRequiredFileRef(stepId: number, el: Element | ComponentPublicInstance | null) {
  requiredFileInputs.value[stepId] = refCallbackToInput(el)
}

function setAdditionalFileRef(documentId: number, el: Element | ComponentPublicInstance | null) {
  additionalFileInputs.value[documentId] = refCallbackToInput(el)
}

function setUpdateFileRef(itemId: number, el: Element | ComponentPublicInstance | null) {
  updateFileInputs.value[itemId] = refCallbackToInput(el)
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
  if (hasPending('additional', item.id)) return uiText('Ready to submit', 'جاهز للإرسال')
  if (String(item?.status || '').toLowerCase() === 'rejected') return t('clientDocuments.actions.uploadCorrected')
  if (!canUploadAdditional(item)) return t('clientDocuments.states.uploaded')
  return t('clientDocuments.actions.uploadFile')
}

function requiredUploadButtonLabel(item: any) {
  if (uploadingRequired.value[item.document_upload_step_id]) return t('clientDocuments.actions.uploading')
  if (hasPending('required', item.document_upload_step_id)) return uiText('Ready to submit', 'جاهز للإرسال')
  if (item.is_multiple && item.is_uploaded) return uiText('Upload another file', 'رفع ملف إضافي')
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

function sameFile(a: File, b: File) {
  return a.name === b.name && a.size === b.size && a.lastModified === b.lastModified
}

function hasPending(kind: PendingUpload['kind'], id: number) {
  return pendingUploads.value.some((item) => item.kind === kind && item.id === id)
}

function enqueueUploads(base: Omit<PendingUpload, 'file'>, files: File[], options?: { replaceForKey?: boolean }) {
  const replaceForKey = options?.replaceForKey ?? false

  const incoming = files.map((file) => ({ ...base, file }))

  pendingUploads.value = pendingUploads.value.filter((item) => {
    if (!replaceForKey) return true
    return !(item.kind === base.kind && item.id === base.id)
  })

  for (const item of incoming) {
    const exists = pendingUploads.value.some((existing) => existing.kind === item.kind
      && existing.id === item.id
      && sameFile(existing.file, item.file))
    if (!exists) {
      pendingUploads.value.push(item)
    }
  }
}

function removePendingUpload(upload: PendingUpload) {
  pendingUploads.value = pendingUploads.value.filter((item) => !(item.kind === upload.kind && item.id === upload.id && sameFile(item.file, upload.file)))
}

function openPendingPreview(upload: PendingUpload) {
  pendingUpload.value = upload
  uploadPreviewOpen.value = true
}

const pendingUploadCount = computed(() => pendingUploads.value.length)

async function submitQueuedUploads() {
  if (!pendingUploads.value.length || uploadingQueued.value) return

  uploadingQueued.value = true
  errorMessage.value = ''
  successMessage.value = ''

  const queue = [...pendingUploads.value]
  let uploadedCount = 0

  try {
    for (const item of queue) {
      if (item.kind === 'required') {
        uploadingRequired.value = { ...uploadingRequired.value, [item.id]: true }
        const data = await uploadClientRequiredDocument(requestId.value, {
          document_upload_step_id: item.id,
          file: item.file,
        })
        applyRequestFromResponse(data)
        uploadingRequired.value = { ...uploadingRequired.value, [item.id]: false }
      } else if (item.kind === 'additional') {
        uploadingAdditional.value = { ...uploadingAdditional.value, [item.id]: true }
        const data = await uploadClientAdditionalDocument(requestId.value, item.id, { file: item.file })
        applyRequestFromResponse(data)
        uploadingAdditional.value = { ...uploadingAdditional.value, [item.id]: false }
      } else {
        uploadingUpdateFiles.value = { ...uploadingUpdateFiles.value, [item.id]: true }
        const data = await submitClientUpdateFile(requestId.value, item.id, { file: item.file })
        applyRequestFromResponse(data)
        uploadingUpdateFiles.value = { ...uploadingUpdateFiles.value, [item.id]: false }
      }

      uploadedCount += 1
      removePendingUpload(item)
    }

    successMessage.value = uploadedCount > 1
      ? uiText(`${uploadedCount} files uploaded successfully.`, `تم رفع ${uploadedCount} ملفات بنجاح.`)
      : uiText('File uploaded successfully.', 'تم رفع الملف بنجاح.')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || uiText('Unable to upload one of the files. Please try again.', 'تعذر رفع أحد الملفات. حاول مرة أخرى.')
  } finally {
    uploadingQueued.value = false
  }
}

async function onRequiredFileChange(item: any, event: Event) {
  const stepId = Number(item?.document_upload_step_id || 0)
  const input = event.target as HTMLInputElement
  const selectedFiles = Array.from(input.files ?? [])

  if (!selectedFiles.length || !stepId) return
  const filesToUpload = item?.is_multiple ? selectedFiles : selectedFiles.slice(0, 1)
  const validFiles: File[] = []
  const validationErrors: string[] = []

  for (const file of filesToUpload) {
    const fileError = validateSelectedFile(file, {
      allowedExtensions: item?.allowed_file_types,
      maxMb: item?.max_file_size_mb,
    })
    if (fileError) {
      validationErrors.push(`${file.name}: ${fileError}`)
      continue
    }
    validFiles.push(file)
  }

  errorMessage.value = ''
  successMessage.value = ''

  if (!validFiles.length) {
    errorMessage.value = validationErrors[0] || uiText('No valid files selected.', 'لم يتم اختيار ملفات صالحة.')
    input.value = ''
    return
  }

  if (validationErrors.length > 0) {
    errorMessage.value = uiText(
      `${validationErrors.length} file(s) were skipped because they do not match the required format or size.`,
      `تم تجاهل ${validationErrors.length} ملف(ات) لأنها لا تطابق الصيغة أو الحجم المطلوب.`,
    )
  }

  enqueueUploads(
    { kind: 'required', id: stepId, title: t('clientDocuments.sections.requiredTitle') },
    validFiles,
    { replaceForKey: !item?.is_multiple },
  )

  pendingUpload.value = { kind: 'required', id: stepId, file: validFiles[0], title: t('clientDocuments.sections.requiredTitle') }
  uploadPreviewOpen.value = true
  input.value = ''
}

async function onAdditionalFileChange(documentId: number, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  errorMessage.value = ''
  successMessage.value = ''
  const fileError = validateSelectedFile(file)
  if (fileError) {
    errorMessage.value = `${file.name}: ${fileError}`
    input.value = ''
    return
  }
  enqueueUploads(
    { kind: 'additional', id: documentId, title: t('clientDocuments.sections.additionalTitle') },
    [file],
    { replaceForKey: true },
  )

  pendingUpload.value = { kind: 'additional', id: documentId, file, title: t('clientDocuments.sections.additionalTitle') }
  uploadPreviewOpen.value = true
  input.value = ''
}

async function onUpdateFileChange(itemId: number, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  errorMessage.value = ''
  successMessage.value = ''
  const fileError = validateSelectedFile(file)
  if (fileError) {
    errorMessage.value = `${file.name}: ${fileError}`
    input.value = ''
    return
  }
  enqueueUploads(
    { kind: 'update', id: itemId, title: uiText('Requested file replacement', 'استبدال ملف مطلوب') },
    [file],
    { replaceForKey: true },
  )

  pendingUpload.value = { kind: 'update', id: itemId, file, title: uiText('Requested file replacement', 'استبدال ملف مطلوب') }
  uploadPreviewOpen.value = true
  input.value = ''
}

onMounted(load)
</script>

<template>
  <section class="client-shell client-request-detail-shell">
    <div class="hero-card client-documents-hero client-reveal-up">
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
      <div class="client-status-chip-grid client-status-chip-grid--summary client-documents-summary-grid client-reveal-up">
        <div class="client-status-chip-card client-documents-summary-card">
          <strong>
            <span class="client-stage-badge" :class="stageMeta(requestItem.workflow_stage).className">{{ stageMeta(requestItem.workflow_stage).label }}</span>
          </strong>
          <span>{{ t('clientDocuments.summary.currentStage') }}</span>
        </div>
        <div class="client-status-chip-card client-documents-summary-card">
          <strong>{{ requiredPendingCount }}</strong>
          <span>{{ t('clientDocuments.summary.requiredPending') }}</span>
        </div>
        <div class="client-status-chip-card client-documents-summary-card">
          <strong>{{ additionalPendingCount }}</strong>
          <span>{{ t('clientDocuments.summary.additionalPending') }}</span>
        </div>
        <div class="client-status-chip-card client-documents-summary-card">
          <strong>{{ pendingFileUpdateCount }}</strong>
          <span>{{ uiText('Requested file updates', 'تحديثات الملفات المطلوبة') }}</span>
        </div>
      </div>

      <div class="client-accordion-stack client-documents-accordion">
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
                    :accept="acceptFromExtensions(DEFAULT_ALLOWED_EXTENSIONS)"
                    class="sr-only"
                    @change.stop="onUpdateFileChange(item.id, $event)"
                  />

                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingQueued || uploadingUpdateFiles[item.id] || item.status === 'approved' || !requestItem.can_submit_client_updates"
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
                <p v-if="item.is_multiple" class="client-subtext">
                  {{ uiText('Multiple files allowed', 'يسمح بعدة ملفات') }}
                  <span v-if="Number(item.uploads_count || 0) > 0"> - {{ uiText('Uploaded files', 'الملفات المرفوعة') }}: {{ item.uploads_count }}</span>
                </p>

                <div v-if="requiredUploads(item).length" class="client-required-upload-list">
                  <div
                    v-for="upload in requiredUploads(item)"
                    :key="`client-required-upload-${item.document_upload_step_id}-${upload.id}`"
                    class="client-required-upload-row"
                  >
                    <div>
                      <strong>{{ upload.file_name || t('clientDocuments.states.latestFile') }}</strong>
                      <p class="client-subtext">
                        {{ uiText('Uploaded at', 'تاريخ الرفع') }}: {{ readableDateTime(upload.uploaded_at) }}
                      </p>
                    </div>
                    <span class="client-badge" :class="requiredUploadStatusClass(upload.status)">
                      {{ requiredUploadStatusLabel(upload.status) }}
                    </span>
                  </div>
                </div>

                <p v-if="item.rejection_reason" class="client-form-error">{{ item.rejection_reason }}</p>

                <p v-if="item.is_uploaded && !item.can_client_upload" class="client-subtext">
                  {{ t('clientDocuments.states.lockedAfterUpload') }}
                </p>

                <div class="client-inline-actions client-inline-actions--stackable client-update-actions">
                  <input
                    :id="`required-file-${item.document_upload_step_id}`"
                    :ref="(el) => setRequiredFileRef(item.document_upload_step_id, el)"
                    type="file"
                    :accept="acceptFromExtensions(normalizeAllowedExtensions(item.allowed_file_types))"
                    :multiple="item.is_multiple"
                    class="sr-only"
                    @change.stop="onRequiredFileChange(item, $event)"
                  />

                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingQueued || uploadingRequired[item.document_upload_step_id] || !item.can_client_upload"
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
                    :accept="acceptFromExtensions(DEFAULT_ALLOWED_EXTENSIONS)"
                    class="sr-only"
                    @change.stop="onAdditionalFileChange(item.id, $event)"
                  />

                  <button
                    type="button"
                    class="client-btn-primary"
                    :disabled="uploadingQueued || uploadingAdditional[item.id] || !canUploadAdditional(item)"
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

    <div v-if="pendingUploadCount" class="client-doc-card client-documents-pending-card client-reveal-up" style="margin-top: 1.15rem;">
      <div class="client-card-head">
        <div>
          <h2>{{ uiText('Files ready to upload', 'ملفات جاهزة للرفع') }}</h2>
          <p class="client-subtext">
            {{ uiText('Review your selected files, then submit once.', 'راجع ملفاتك المحددة ثم أرسل مرة واحدة.') }}
          </p>
        </div>
        <span class="client-badge client-badge--amber">{{ pendingUploadCount }}</span>
      </div>

      <div class="client-required-upload-list" style="margin-top: 0.75rem;">
        <div v-for="(item, idx) in pendingUploads" :key="`${item.kind}-${item.id}-${idx}`" class="client-required-upload-row">
          <div style="min-width: 0;">
            <strong style="word-break: break-word;">{{ item.file.name }}</strong>
            <p class="client-subtext" style="margin-top: 0.15rem;">
              {{ item.kind === 'required' ? t('clientDocuments.sections.requiredTitle')
                : item.kind === 'additional' ? t('clientDocuments.sections.additionalTitle')
                  : uiText('Requested file replacement', 'استبدال ملف مطلوب') }}
            </p>
          </div>
          <div class="client-inline-actions" style="gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end;">
            <button type="button" class="ghost-btn" @click="openPendingPreview(item)">
              {{ uiText('Preview', 'معاينة') }}
            </button>
            <button type="button" class="ghost-btn" :disabled="uploadingQueued" @click="removePendingUpload(item)">
              {{ uiText('Remove', 'إزالة') }}
            </button>
          </div>
        </div>
      </div>

      <div class="client-inline-actions client-inline-actions--stackable" style="margin-top: 0.9rem;">
        <button type="button" class="client-btn-primary" :disabled="uploadingQueued || !pendingUploadCount" @click="submitQueuedUploads">
          {{ uploadingQueued ? uiText('Uploading...', 'جارٍ الرفع...') : uiText('Submit uploads', 'إرسال الملفات') }}
        </button>
        <button type="button" class="ghost-btn" :disabled="uploadingQueued" @click="pendingUploads = []">
          {{ uiText('Clear list', 'مسح القائمة') }}
        </button>
      </div>
    </div>

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

<style scoped>
.client-request-detail-shell {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
}

.client-documents-hero,
.client-documents-summary-grid,
.client-documents-accordion,
.client-documents-pending-card {
  grid-column: 1 / -1;
  width: 100%;
}

.client-documents-accordion {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 18px;
}

.client-documents-accordion > .client-accordion-card {
  width: 100%;
}

.client-documents-summary-grid {
  align-items: stretch;
}

.client-documents-summary-card {
  min-height: 118px;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 10px;
}

.client-documents-summary-card strong {
  margin: 0;
  min-height: 34px;
  display: flex;
  align-items: center;
}

.client-documents-summary-card > span {
  margin-top: 0;
  line-height: 1.35;
  min-height: 2.7em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.client-documents-summary-card :deep(.client-stage-badge) {
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.client-required-upload-list {
  display: grid;
  gap: 0.55rem;
  margin-block: 0.45rem 0.2rem;
}

.client-required-upload-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.65rem 0.75rem;
  border: 1px solid rgba(148, 163, 184, 0.26);
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.65);
}

.client-required-upload-row strong {
  display: block;
  font-size: 0.86rem;
}

.client-required-upload-row .client-subtext {
  margin: 0.2rem 0 0;
}

@media (max-width: 640px) {
  .client-documents-summary-card {
    min-height: 100px;
  }

  .client-documents-summary-card > span {
    min-height: 0;
  }

  .client-required-upload-row {
    flex-direction: column;
  }
}
</style>
