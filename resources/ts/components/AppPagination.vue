<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PaginationMeta } from '@/types/pagination'

const props = defineProps<{
  pagination: PaginationMeta
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'change', page: number): void
}>()

const { t } = useI18n()

const pages = computed(() => {
  const total = props.pagination.last_page || 1
  const current = props.pagination.current_page || 1
  const start = Math.max(1, current - 2)
  const end = Math.min(total, start + 4)
  const normalizedStart = Math.max(1, end - 4)

  return Array.from({ length: end - normalizedStart + 1 }, (_, index) => normalizedStart + index)
})

const canPrev = computed(() => props.pagination.current_page > 1)
const canNext = computed(() => props.pagination.current_page < props.pagination.last_page)

function go(page: number) {
  if (props.disabled || page < 1 || page > props.pagination.last_page || page === props.pagination.current_page) {
    return
  }

  emit('change', page)
}
</script>

<template>
  <div v-if="pagination.total > 0" class="app-pagination">
    <p class="app-pagination__summary">
      {{ t('common.pagination.showing', { from: pagination.from || 0, to: pagination.to || 0, total: pagination.total }) }}
    </p>

    <div class="app-pagination__actions">
      <button type="button" class="app-pagination__btn" :disabled="disabled || !canPrev" @click="go(pagination.current_page - 1)">
        {{ t('common.pagination.prev') }}
      </button>

      <button
        v-for="page in pages"
        :key="page"
        type="button"
        class="app-pagination__btn"
        :class="{ 'is-active': page === pagination.current_page }"
        :disabled="disabled"
        @click="go(page)"
      >
        {{ page }}
      </button>

      <button type="button" class="app-pagination__btn" :disabled="disabled || !canNext" @click="go(pagination.current_page + 1)">
        {{ t('common.pagination.next') }}
      </button>
    </div>
  </div>
</template>
