<script setup lang="ts">
type PipelineItem = {
  stage: string
  count: number
  helper: string
  tone: string
}

defineProps<{
  items: PipelineItem[]
}>()
</script>

<template>
  <section class="admin-panel admin-panel--wide admin-reveal-up admin-reveal-delay-1">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">Workflow overview</span>
        <h2>Request pipeline</h2>
      </div>
      <button type="button" class="admin-panel__action">View queue</button>
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
