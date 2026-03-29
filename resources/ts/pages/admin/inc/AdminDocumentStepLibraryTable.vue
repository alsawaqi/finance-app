<script setup lang="ts">
import { ref } from 'vue'
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
        <span class="document-step-panel__eyebrow">Document step library</span>
        <h2>Saved upload steps</h2>
      </div>
      <span class="document-step-sort-note">Drag rows to reorder by <code>sort_order</code>.</span>
    </div>

    <div v-if="loading" class="document-step-empty-state">Loading document upload steps...</div>

    <div v-else-if="!rows.length" class="document-step-empty-state">
      No document upload steps yet. Create the first one from the form.
    </div>

    <div v-else class="document-step-table-wrap">
      <table class="document-step-table">
        <thead>
          <tr>
            <th></th>
            <th>Order</th>
            <th>Name</th>
            <th>Types</th>
            <th>Size</th>
            <th>Required</th>
            <th>Status</th>
            <th>Used</th>
            <th>Actions</th>
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
              <div class="document-step-row-sub">{{ row.code || 'Auto code' }}</div>
            </td>
            <td>
              <div class="document-step-chip-row compact">
                <span v-for="item in row.allowed_file_types_json.slice(0, 3)" :key="item" class="document-step-chip">{{ item }}</span>
                <span v-if="!row.allowed_file_types_json.length" class="document-step-chip is-muted">Any</span>
                <span v-if="row.allowed_file_types_json.length > 3" class="document-step-chip is-muted">+{{ row.allowed_file_types_json.length - 3 }}</span>
              </div>
            </td>
            <td>{{ row.max_file_size_mb ? `${row.max_file_size_mb} MB` : 'Default' }}</td>
            <td>
              <span class="document-step-pill" :class="row.is_required ? 'is-required' : 'is-optional'">
                {{ row.is_required ? 'Required' : 'Optional' }}
              </span>
            </td>
            <td>
              <span class="document-step-pill" :class="row.is_active ? 'is-active' : 'is-inactive'">
                {{ row.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td>{{ row.request_document_uploads_count }}</td>
            <td>
              <div class="document-step-row-actions">
                <button type="button" class="document-step-table-btn" @click="emit('edit', row)">Edit</button>
                <button type="button" class="document-step-table-btn" @click="emit('toggle', row)">
                  {{ row.is_active ? 'Disable' : 'Enable' }}
                </button>
                <button
                  type="button"
                  class="document-step-table-btn danger"
                  :disabled="deletingId === row.id"
                  @click="emit('delete', row)"
                >
                  {{ deletingId === row.id ? 'Deleting...' : 'Delete' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="busyReordering" class="document-step-reorder-note">Saving new order...</p>
  </section>
</template>
