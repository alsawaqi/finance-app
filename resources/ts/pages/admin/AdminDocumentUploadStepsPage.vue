<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import AdminDocumentStepBuilderForm from './inc/AdminDocumentStepBuilderForm.vue'
import AdminDocumentStepLibraryTable from './inc/AdminDocumentStepLibraryTable.vue'
import AdminDocumentStepPreviewCard from './inc/AdminDocumentStepPreviewCard.vue'
import type { DocumentUploadStepItem, DocumentUploadStepPayload } from '@/services/documentUploadSteps'
import {
  createDocumentUploadStep,
  deleteDocumentUploadStep,
  listDocumentUploadSteps,
  reorderDocumentUploadSteps,
  toggleDocumentUploadStepActive,
  updateDocumentUploadStep,
} from '@/services/documentUploadSteps'

type StepForm = {
  id: number | null
  code: string
  name: string
  description: string
  allowed_file_types_text: string
  max_file_size_mb: number | null
  sort_order: number
  is_required: boolean
  is_active: boolean
}

const steps = ref<DocumentUploadStepItem[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const isReordering = ref(false)
const deletingId = ref<number | null>(null)
const formError = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<StepForm>(createDefaultForm())

const isEditing = computed(() => form.value.id !== null)
const parsedAllowedFileTypes = computed(() =>
  form.value.allowed_file_types_text
    .split('\n')
    .map((item) => item.trim())
    .filter(Boolean),
)

const stats = computed(() => {
  const total = steps.value.length
  const active = steps.value.filter((item) => item.is_active).length
  const required = steps.value.filter((item) => item.is_required).length
  const inUse = steps.value.filter((item) => item.request_document_uploads_count > 0).length

  return [
    { label: 'Total steps', value: String(total) },
    { label: 'Active steps', value: String(active) },
    { label: 'Required steps', value: String(required) },
    { label: 'Steps in use', value: String(inUse) },
  ]
})

onMounted(async () => {
  await fetchSteps()
})

function createDefaultForm(): StepForm {
  return {
    id: null,
    code: '',
    name: '',
    description: '',
    allowed_file_types_text: 'pdf\njpg\npng',
    max_file_size_mb: 10,
    sort_order: steps.value.length + 1,
    is_required: true,
    is_active: true,
  }
}

function clearMessages() {
  formError.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
}

function resetForm() {
  clearMessages()
  form.value = createDefaultForm()
}

function buildPayload(): DocumentUploadStepPayload {
  return {
    code: form.value.code.trim() || null,
    name: form.value.name.trim(),
    description: form.value.description.trim() || null,
    allowed_file_types_json: parsedAllowedFileTypes.value.length ? parsedAllowedFileTypes.value : null,
    max_file_size_mb: form.value.max_file_size_mb,
    sort_order: form.value.sort_order,
    is_required: form.value.is_required,
    is_active: form.value.is_active,
  }
}

async function fetchSteps() {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listDocumentUploadSteps()
    steps.value = data.data
    if (!isEditing.value) {
      form.value.sort_order = steps.value.length + 1
    }
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to load document upload steps right now.')
  } finally {
    isLoading.value = false
  }
}

async function saveStep() {
  clearMessages()
  isSaving.value = true

  try {
    if (isEditing.value && form.value.id) {
      const { data } = await updateDocumentUploadStep(form.value.id, buildPayload())
      successMessage.value = data.message
    } else {
      const { data } = await createDocumentUploadStep(buildPayload())
      successMessage.value = data.message
    }

    await fetchSteps()
    resetForm()
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? 'Unable to save the document upload step.'
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = 'Unable to save the document upload step.'
    }
  } finally {
    isSaving.value = false
  }
}

function editStep(row: DocumentUploadStepItem) {
  clearMessages()
  form.value = {
    id: row.id,
    code: row.code ?? '',
    name: row.name,
    description: row.description ?? '',
    allowed_file_types_text: row.allowed_file_types_json.join('\n'),
    max_file_size_mb: row.max_file_size_mb,
    sort_order: row.sort_order,
    is_required: row.is_required,
    is_active: row.is_active,
  }

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function toggleStep(row: DocumentUploadStepItem) {
  clearMessages()

  try {
    const { data } = await toggleDocumentUploadStepActive(row.id)
    successMessage.value = data.message
    steps.value = steps.value.map((step) => (step.id === row.id ? data.data : step))
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to update document upload step status right now.')
  }
}

async function destroyStep(row: DocumentUploadStepItem) {
  const confirmed = window.confirm(`Delete "${row.name}"? This cannot be undone.`)
  if (!confirmed) return

  clearMessages()
  deletingId.value = row.id

  try {
    const { data } = await deleteDocumentUploadStep(row.id)
    successMessage.value = data.message
    steps.value = steps.value.filter((step) => step.id !== row.id)

    if (form.value.id === row.id) {
      resetForm()
    }
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to delete document upload step right now.')
  } finally {
    deletingId.value = null
  }
}

async function reorderSteps(orderedIds: number[]) {
  clearMessages()
  isReordering.value = true

  const previous = [...steps.value]
  const orderedMap = new Map(previous.map((item) => [item.id, item]))
  steps.value = orderedIds
    .map((id, index) => {
      const existing = orderedMap.get(id)
      if (!existing) return null
      return {
        ...existing,
        sort_order: index + 1,
      }
    })
    .filter((item): item is DocumentUploadStepItem => item !== null)

  try {
    const { data } = await reorderDocumentUploadSteps(orderedIds)
    steps.value = data.data
    successMessage.value = data.message
  } catch (error) {
    steps.value = previous
    formError.value = extractErrorMessage(error, 'Unable to reorder document upload steps right now.')
  } finally {
    isReordering.value = false
  }
}

function extractErrorMessage(error: unknown, fallback: string) {
  if (axios.isAxiosError(error)) {
    return error.response?.data?.message ?? fallback
  }

  return fallback
}
</script>

<template>
  <div class="document-step-page">
    <section class="document-step-hero">
      <div>
        <span class="document-step-hero__eyebrow">Admin Setup · Documents</span>
        <h1>Manage request document upload steps</h1>
        <p>
          Create the required upload checklist clients will see only after their request reaches the
          document stage. This page is fully wired to Laravel CRUD including list, create, update,
          delete, active toggle, and drag-and-drop reorder.
        </p>
      </div>

      <div class="document-step-hero__actions">
        <button type="button" class="document-step-primary-btn" :disabled="isSaving" @click="saveStep">
          {{ isSaving ? (isEditing ? 'Updating...' : 'Saving...') : isEditing ? 'Update step' : 'Save step' }}
        </button>
        <button type="button" class="document-step-secondary-btn" @click="resetForm">
          {{ isEditing ? 'Cancel edit' : 'Reset form' }}
        </button>
      </div>
    </section>

    <section class="document-step-stats">
      <article v-for="item in stats" :key="item.label" class="document-step-stat-card">
        <strong>{{ item.value }}</strong>
        <span>{{ item.label }}</span>
      </article>
    </section>

    <div v-if="formError" class="document-step-alert document-step-alert--error">{{ formError }}</div>
    <div v-if="successMessage" class="document-step-alert document-step-alert--success">{{ successMessage }}</div>

    <div class="document-step-grid">
      <AdminDocumentStepBuilderForm
        v-model="form"
        :is-editing="isEditing"
        :is-saving="isSaving"
        :errors="fieldErrors"
        @save="saveStep"
        @reset="resetForm"
      />

      <AdminDocumentStepPreviewCard
        :name="form.name"
        :description="form.description"
        :allowed-file-types="parsedAllowedFileTypes"
        :max-file-size-mb="form.max_file_size_mb"
        :is-required="form.is_required"
        :is-active="form.is_active"
      />
    </div>

    <AdminDocumentStepLibraryTable
      :rows="steps"
      :loading="isLoading"
      :busy-reordering="isReordering"
      :deleting-id="deletingId"
      @edit="editStep"
      @toggle="toggleStep"
      @delete="destroyStep"
      @reorder="reorderSteps"
    />
  </div>
</template>

<style>
.document-step-page {
  padding: 24px;
  display: grid;
  gap: 24px;
  background: #f8fafc;
  min-height: 100vh;
}

.document-step-hero,
.document-step-panel,
.document-step-stat-card,
.document-step-alert {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 24px;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
}

.document-step-hero {
  padding: 28px;
  display: flex;
  justify-content: space-between;
  gap: 20px;
  align-items: flex-start;
}

.document-step-hero__eyebrow,
.document-step-panel__eyebrow {
  display: inline-flex;
  padding: 8px 14px;
  border-radius: 999px;
  background: #eef2ff;
  color: #4f46e5;
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .08em;
}

.document-step-hero h1,
.document-step-panel__head h2 {
  margin: 14px 0 10px;
  font-size: 30px;
  line-height: 1.1;
  color: #0f172a;
}

.document-step-hero p {
  margin: 0;
  max-width: 760px;
  color: #475569;
  line-height: 1.8;
}

.document-step-hero__actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.document-step-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.document-step-stat-card {
  padding: 22px;
  display: grid;
  gap: 8px;
}

.document-step-stat-card strong {
  font-size: 34px;
  line-height: 1;
  color: #0f172a;
}

.document-step-stat-card span {
  color: #64748b;
  font-size: 14px;
}

.document-step-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(340px, .8fr);
  gap: 24px;
  align-items: start;
}

.document-step-panel {
  padding: 24px;
}

.document-step-panel__head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: start;
  margin-bottom: 20px;
}

.document-step-form-grid {
  display: grid;
  gap: 18px;
}

.document-step-form-grid--2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.document-step-field {
  display: grid;
  gap: 8px;
}

.document-step-field--full {
  grid-column: 1 / -1;
}

.document-step-field span,
.document-step-field strong {
  color: #0f172a;
  font-size: 14px;
  font-weight: 700;
}

.document-step-input,
.document-step-textarea {
  width: 100%;
  border: 1px solid #dbe4f0;
  border-radius: 16px;
  background: #fff;
  color: #0f172a;
  padding: 14px 16px;
  font-size: 14px;
  transition: all .2s ease;
}

.document-step-input { min-height: 52px; }
.document-step-textarea { resize: vertical; }

.document-step-input:focus,
.document-step-textarea:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
}

.document-step-input.has-error,
.document-step-textarea.has-error {
  border-color: #ef4444;
}

.document-step-help,
.document-step-row-sub,
.document-step-sort-note,
.document-step-reorder-note,
.document-step-meta-text {
  color: #64748b;
  font-size: 12px;
}

.document-step-error {
  color: #dc2626;
  font-size: 12px;
}

.document-step-switches {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.document-step-switch-card {
  display: flex;
  align-items: start;
  gap: 12px;
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  background: #f8fafc;
}

.document-step-switch-card input { margin-top: 3px; }
.document-step-switch-card strong { display: block; margin-bottom: 4px; }
.document-step-switch-card span { color: #64748b; font-size: 13px; }

.document-step-actions {
  display: flex;
  gap: 12px;
  margin-top: 22px;
  flex-wrap: wrap;
}

.document-step-primary-btn,
.document-step-secondary-btn,
.document-step-ghost-btn,
.document-step-table-btn {
  border: 0;
  border-radius: 14px;
  min-height: 46px;
  padding: 0 18px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s ease;
}

.document-step-primary-btn {
  background: linear-gradient(135deg, #4f46e5, #2563eb);
  color: #fff;
  box-shadow: 0 16px 32px rgba(37, 99, 235, 0.18);
}

.document-step-secondary-btn,
.document-step-ghost-btn,
.document-step-table-btn {
  background: #fff;
  color: #0f172a;
  border: 1px solid #dbe4f0;
}

.document-step-table-btn.danger {
  color: #dc2626;
  border-color: rgba(220, 38, 38, 0.2);
}

.document-step-primary-btn:hover,
.document-step-secondary-btn:hover,
.document-step-ghost-btn:hover,
.document-step-table-btn:hover {
  transform: translateY(-1px);
}

.document-step-primary-btn:disabled,
.document-step-table-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

.document-step-alert {
  padding: 16px 18px;
  font-size: 14px;
  font-weight: 600;
}

.document-step-alert--error { color: #991b1b; border-color: rgba(239, 68, 68, 0.28); background: #fff5f5; }
.document-step-alert--success { color: #166534; border-color: rgba(34, 197, 94, 0.24); background: #f0fdf4; }

.document-step-preview-card {
  display: grid;
  gap: 18px;
}

.document-step-preview-card h3 {
  margin: 0;
  font-size: 24px;
  color: #0f172a;
}

.document-step-preview-card p {
  margin: 0;
  color: #475569;
  line-height: 1.8;
}

.document-step-preview-card__badges,
.document-step-chip-row,
.document-step-row-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.document-step-preview-meta {
  display: grid;
  gap: 18px;
}

.document-step-pill,
.document-step-chip {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.document-step-pill.is-required { background: #fef3c7; color: #92400e; }
.document-step-pill.is-optional { background: #e2e8f0; color: #334155; }
.document-step-pill.is-active { background: #dcfce7; color: #166534; }
.document-step-pill.is-inactive { background: #fee2e2; color: #991b1b; }

.document-step-chip { background: #eef2ff; color: #4338ca; }
.document-step-chip.is-muted { background: #f1f5f9; color: #475569; }
.document-step-chip-row.compact .document-step-chip { min-height: 28px; }

.document-step-upload-box {
  border: 2px dashed #cbd5e1;
  border-radius: 20px;
  padding: 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #f8fafc;
}

.document-step-table-wrap { overflow-x: auto; }

.document-step-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 920px;
}

.document-step-table th,
.document-step-table td {
  padding: 14px 12px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: top;
  font-size: 14px;
}

.document-step-table th {
  color: #64748b;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .06em;
}

.document-step-row { background: #fff; }
.document-step-drag { cursor: grab; font-size: 18px; color: #94a3b8; width: 40px; }

.document-step-empty-state {
  padding: 28px;
  border: 1px dashed #cbd5e1;
  border-radius: 18px;
  color: #64748b;
  text-align: center;
  background: #f8fafc;
}

@media (max-width: 1100px) {
  .document-step-grid,
  .document-step-stats,
  .document-step-form-grid--2,
  .document-step-switches {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 768px) {
  .document-step-page { padding: 16px; }
  .document-step-hero,
  .document-step-panel { padding: 20px; }
  .document-step-hero { flex-direction: column; }
  .document-step-hero h1,
  .document-step-panel__head h2 { font-size: 24px; }
  .document-step-upload-box { flex-direction: column; align-items: flex-start; }
}
</style>
