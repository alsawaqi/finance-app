<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DocumentUploadStepItem } from '@/services/documentUploadSteps'

const props = defineProps<{
  rows: DocumentUploadStepItem[]
  loading?: boolean
  busyReordering?: boolean
  deletingId?: number | null
}>()

const emit = defineEmits<{
  (e: 'edit', row: DocumentUploadStepItem): void
  (e: 'toggle', row: DocumentUploadStepItem): void
  (e: 'delete', row: DocumentUploadStepItem): void
  (e: 'reorder', orderedIds: number[]): void
}>()

const draggedId = ref<number | null>(null)
const { t } = useI18n()

function onDragStart(id: number) {
  draggedId.value = id
}

function onDrop(targetId: number) {
  if (draggedId.value === null || draggedId.value === targetId) return

  const ids = props.rows.map((row) => row.id)
  const fromIndex = ids.indexOf(draggedId.value)
  const toIndex = ids.indexOf(targetId)

  if (fromIndex === -1 || toIndex === -1) return

  const next = [...ids]
  const [moved] = next.splice(fromIndex, 1)
  next.splice(toIndex, 0, moved)

  draggedId.value = null
  emit('reorder', next)
}
</script>

<template>
  <section class="document-step-panel">
    <div class="document-step-panel__head">
      <div>
        <span class="document-step-panel__eyebrow">{{ t('adminDocumentStepLibrary.eyebrow') }}</span>
        <h2>{{ t('adminDocumentStepLibrary.title') }}</h2>
      </div>
      <span class="document-step-sort-note">{{ t('adminDocumentStepLibrary.dragBySortOrder') }}</span>
    </div>

    <div v-if="loading" class="document-step-empty-state">{{ t('adminDocumentStepLibrary.states.loading') }}</div>

    <div v-else-if="!rows.length" class="document-step-empty-state">
      {{ t('adminDocumentStepLibrary.states.empty') }}
    </div>

    <div v-else class="document-step-table-wrap">
      <table class="document-step-table">
        <thead>
          <tr>
            <th></th>
            <th>{{ t('adminDocumentStepLibrary.columns.order') }}</th>
            <th>{{ t('adminDocumentStepLibrary.columns.name') }}</th>
            <th>{{ t('adminDocumentStepLibrary.columns.types') }}</th>
            <th>{{ t('adminDocumentStepLibrary.columns.size') }}</th>
            <th>{{ t('adminDocumentStepLibrary.columns.required') }}</th>
            <th>{{ t('adminDocumentStepLibrary.columns.status') }}</th>
            <th>{{ t('adminDocumentStepLibrary.columns.used') }}</th>
            <th>{{ t('adminDocumentStepLibrary.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="document-step-row"
            draggable="true"
            @dragstart="onDragStart(row.id)"
            @dragover.prevent
            @drop="onDrop(row.id)"
          >
              <td class="document-step-drag">↕</td>
            <td>#{{ row.sort_order }}</td>
            <td>
              <strong>{{ row.name }}</strong>
              <div class="document-step-row-sub">{{ row.code || t('adminDocumentStepLibrary.states.autoCode') }}</div>
            </td>
            <td>
              <div class="document-step-chip-row compact">
                <span v-for="item in row.allowed_file_types_json.slice(0, 3)" :key="item" class="document-step-chip">{{ item }}</span>
                <span v-if="!row.allowed_file_types_json.length" class="document-step-chip is-muted">{{ t('adminDocumentStepLibrary.states.any') }}</span>
                <span v-if="row.allowed_file_types_json.length > 3" class="document-step-chip is-muted">+{{ row.allowed_file_types_json.length - 3 }}</span>
              </div>
            </td>
            <td>{{ row.max_file_size_mb ? `${row.max_file_size_mb} MB` : t('adminDocumentStepLibrary.states.default') }}</td>
            <td>
              <span class="document-step-pill" :class="row.is_required ? 'is-required' : 'is-optional'">
                {{ row.is_required ? t('adminDocumentStepLibrary.states.required') : t('adminDocumentStepLibrary.states.optional') }}
              </span>
            </td>
            <td>
              <span class="document-step-pill" :class="row.is_active ? 'is-active' : 'is-inactive'">
                {{ row.is_active ? t('adminDocumentStepLibrary.states.active') : t('adminDocumentStepLibrary.states.inactive') }}
              </span>
            </td>
            <td>{{ row.request_document_uploads_count }}</td>
            <td>
              <div class="document-step-row-actions">
                <button type="button" class="document-step-table-btn" @click="emit('edit', row)">{{ t('adminDocumentStepLibrary.actions.edit') }}</button>
                <button type="button" class="document-step-table-btn" @click="emit('toggle', row)">
                  {{ row.is_active ? t('adminDocumentStepLibrary.actions.disable') : t('adminDocumentStepLibrary.actions.enable') }}
                </button>
                <button
                  type="button"
                  class="document-step-table-btn danger"
                  :disabled="deletingId === row.id"
                  @click="emit('delete', row)"
                >
                  {{ deletingId === row.id ? t('adminDocumentStepLibrary.actions.deleting') : t('adminDocumentStepLibrary.actions.delete') }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="busyReordering" class="document-step-reorder-note">{{ t('adminDocumentStepLibrary.states.savingOrder') }}</p>
  </section>
</template>
