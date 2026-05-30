<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { useI18n } from 'vue-i18n'

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
const slots = useSlots()
const hasSummarySlot = computed(() => Boolean(slots.summary))
const hasWorkflowSlot = computed(() => Boolean(slots.workflow))
const hasSupportSlot = computed(() => Boolean(slots.support))
const hasSideSlot = computed(() => Boolean(slots.side))
const { locale } = useI18n()
const loadingFallback = computed(() => (locale.value === 'ar' ? 'جارٍ التحميل...' : 'Loading...'))
</script>

<template>
  <section class="admin-page-shell admin-workspace-page">
    <div class="page-topbar">
      <div>
        <p class="eyebrow">{{ eyebrow }}</p>
        <h4>{{ title }}</h4>
        <p v-if="subtitle" class="subtext">{{ subtitle }}</p>
      </div>
      <div class="actions-row">
        <slot name="topbar-actions" />
      </div>
    </div>

    <p v-if="loading" class="empty-state"><slot name="loading">{{ loadingFallback }}</slot></p>
    <p v-else-if="showBlockingError" class="error-state">{{ errorMessage }}</p>
    <p v-if="successMessage" class="success-state">{{ successMessage }}</p>
    <p v-if="showInlineError" class="error-state">{{ errorMessage }}</p>

    <template v-if="!loading && hasRecord">
      <slot v-if="hasWorkflowSlot" name="workflow" />

      <div
        class="request-command-center-grid"
        :class="[
          layoutClass,
          {
            'request-command-center-grid--single': !hasSideSlot,
            'request-command-center-grid--no-summary': !hasSummarySlot,
          },
        ]"
      >
        <aside v-if="hasSummarySlot" class="request-command-snapshot">
          <slot name="summary" />
        </aside>

        <section v-if="hasSupportSlot" class="request-command-support">
          <slot name="support" />
        </section>

        <div class="admin-workspace-main request-command-main">
          <slot name="main" />
        </div>

        <aside v-if="hasSideSlot" class="admin-workspace-side request-command-rail">
          <slot name="side" />
        </aside>
      </div>

      <slot name="after" />
    </template>
  </section>
</template>
