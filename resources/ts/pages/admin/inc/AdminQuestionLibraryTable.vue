<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { RequestQuestionItem } from '@/services/requestQuestions'

const props = defineProps<{
  rows: RequestQuestionItem[]
  loading?: boolean
  reordering?: boolean
}>()

const emit = defineEmits<{
  (e: 'edit', row: RequestQuestionItem): void
  (e: 'toggle', row: RequestQuestionItem): void
  (e: 'reorder', orderedIds: number[]): void
}>()

const localRows = ref<RequestQuestionItem[]>([])
const draggingId = ref<number | null>(null)
const dragOverId = ref<number | null>(null)
const { t } = useI18n()

watch(
  () => props.rows,
  (rows) => {
    localRows.value = [...rows]
  },
  { immediate: true },
)

function handleDragStart(id: number) {
  draggingId.value = id
}

function handleDrop(targetId: number) {
  if (draggingId.value === null || draggingId.value === targetId) {
    dragOverId.value = null
    return
  }

  const items = [...localRows.value]
  const fromIndex = items.findIndex((item) => item.id === draggingId.value)
  const toIndex = items.findIndex((item) => item.id === targetId)

  if (fromIndex === -1 || toIndex === -1) {
    dragOverId.value = null
    draggingId.value = null
    return
  }

  const [moved] = items.splice(fromIndex, 1)
  items.splice(toIndex, 0, moved)

  localRows.value = items.map((item, index) => ({
    ...item,
    sort_order: index + 1,
  }))

  emit('reorder', localRows.value.map((item) => item.id))

  dragOverId.value = null
  draggingId.value = null
}
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-3">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ t('adminQuestionLibrary.eyebrow') }}</span>
        <h2>{{ t('adminQuestionLibrary.title') }}</h2>
      </div>
      <span class="admin-panel__action is-static">{{ t('adminQuestionLibrary.dragToReorder') }}</span>
    </div>

    <div v-if="loading" class="admin-table-empty">{{ t('adminQuestionLibrary.states.loading') }}</div>
    <div v-else-if="!localRows.length" class="admin-table-empty">{{ t('adminQuestionLibrary.states.empty') }}</div>
    <div v-else class="admin-table-wrap" :class="{ 'is-reordering': reordering }">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width: 54px">{{ t('adminQuestionLibrary.columns.sort') }}</th>
            <th>{{ t('adminQuestionLibrary.columns.code') }}</th>
            <th>{{ t('adminQuestionLibrary.columns.question') }}</th>
            <th>{{ t('adminQuestionLibrary.columns.type') }}</th>
            <th>{{ t('adminQuestionLibrary.columns.required') }}</th>
            <th>{{ t('adminQuestionLibrary.columns.options') }}</th>
            <th>{{ t('adminQuestionLibrary.columns.status') }}</th>
            <th>{{ t('adminQuestionLibrary.columns.action') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in localRows"
            :key="row.id"
            draggable="true"
            :class="{ 'is-drag-over': dragOverId === row.id }"
            @dragstart="handleDragStart(row.id)"
            @dragover.prevent="dragOverId = row.id"
            @dragleave="dragOverId = null"
            @drop.prevent="handleDrop(row.id)"
            @dragend="draggingId = null; dragOverId = null"
          >
            <td>
              <button type="button" class="admin-drag-handle" :aria-label="t('adminQuestionLibrary.dragToReorder')">
                <i class="fas fa-grip-vertical"></i>
                <span>{{ row.sort_order }}</span>
              </button>
            </td>
            <td>{{ row.code || t('adminQuestionLibrary.states.emptyValue') }}</td>
            <td>
              <div class="admin-question-table__text">
                <strong>{{ row.question_text }}</strong>
                <small v-if="row.help_text">{{ row.help_text }}</small>
              </div>
            </td>
            <td><span class="admin-status-pill">{{ row.question_type }}</span></td>
            <td>{{ row.is_required ? t('adminQuestionLibrary.states.yes') : t('adminQuestionLibrary.states.no') }}</td>
            <td>{{ row.options_count }}</td>
            <td>
              <span class="admin-status-pill" :class="row.is_active ? 'is-success' : 'is-muted'">
                {{ row.is_active ? t('adminQuestionLibrary.states.active') : t('adminQuestionLibrary.states.inactive') }}
              </span>
            </td>
            <td>
              <div class="admin-table-actions">
                <button type="button" class="admin-inline-link" @click="$emit('edit', row)">{{ t('adminQuestionLibrary.actions.edit') }}</button>
                <button type="button" class="admin-inline-link" @click="$emit('toggle', row)">
                  {{ row.is_active ? t('adminQuestionLibrary.actions.deactivate') : t('adminQuestionLibrary.actions.activate') }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
