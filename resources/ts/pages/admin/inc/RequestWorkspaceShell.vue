<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  eyebrow: string
  title: string
  subtitle?: string
  loading?: boolean
  errorMessage?: string
  successMessage?: string
  hasRecord?: boolean
  layoutClass?: string
}>(), {
  subtitle: '',
  loading: false,
  errorMessage: '',
  successMessage: '',
  hasRecord: false,
  layoutClass: 'admin-workspace-layout--compact-side',
})

const showBlockingError = computed(() => !props.loading && !props.hasRecord && Boolean(props.errorMessage))
const showInlineError = computed(() => Boolean(props.errorMessage) && props.hasRecord)
</script>

<template>
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ eyebrow }}</p>
        <h1>{{ title }}</h1>
        <p v-if="subtitle" class="subtext">{{ subtitle }}</p>
      </div>
      <div class="actions-row">
        <slot name="topbar-actions" />
      </div>
    </div>

    <p v-if="loading" class="empty-state"><slot name="loading">Loading...</slot></p>
    <p v-else-if="showBlockingError" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>
    <p v-if="showInlineError" class="error-state">{{ errorMessage }}</p>

    <template v-if="!loading && hasRecord">
      <slot name="summary" />

      <div class="admin-workspace-layout" :class="layoutClass">
        <div class="admin-workspace-main">
          <slot name="main" />
        </div>

        <aside class="admin-workspace-side">
          <slot name="side" />
        </aside>
      </div>

      <slot name="after" />
    </template>
  </section>
</template>
