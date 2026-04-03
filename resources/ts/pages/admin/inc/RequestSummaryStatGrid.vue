<script setup lang="ts">
defineProps<{
  items: Array<{
    label: string
    value: string | number | null | undefined
    hint?: string | null
  }>
}>()
</script>

<template>
  <div class="admin-workspace-summary-grid">
    <article v-for="(item, index) in items" :key="`${index}-${item.label}`" class="admin-workspace-stat">
      <span>{{ item.label }}</span>
      <strong>{{ item.value ?? '—' }}</strong>
      <small v-if="item.hint">{{ item.hint }}</small>
    </article>
  </div>
</template>
