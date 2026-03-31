<script setup lang="ts">
import { useI18n } from 'vue-i18n'

type PipelineItem = {
  stage: string
  count: number
  helper: string
  tone: string
}

defineProps<{
  items: PipelineItem[]
}>()

const { t } = useI18n()
</script>

<template>
  <section class="admin-panel admin-panel--wide admin-reveal-up admin-reveal-delay-1">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ t('adminSharedWidgets.requestPipeline.eyebrow') }}</span>
        <h2>{{ t('adminSharedWidgets.requestPipeline.title') }}</h2>
      </div>
      <button type="button" class="admin-panel__action">{{ t('adminSharedWidgets.requestPipeline.viewQueue') }}</button>
    </div>

    <div class="admin-pipeline-grid">
      <article
        v-for="item in items"
        :key="item.stage"
        class="admin-pipeline-card"
        :class="`tone-${item.tone}`"
      >
        <span class="admin-pipeline-card__count">{{ item.count }}</span>
        <h3>{{ item.stage }}</h3>
        <p>{{ item.helper }}</p>
      </article>
    </div>
  </section>
</template>
