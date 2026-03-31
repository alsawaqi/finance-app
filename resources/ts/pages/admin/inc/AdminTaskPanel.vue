<script setup lang="ts">
import { useI18n } from 'vue-i18n'

type TaskItem = {
  title: string
  helper: string
  value: string
}

defineProps<{
  tasks: TaskItem[]
}>()

const { t } = useI18n()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-3">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ t('adminSharedWidgets.taskPanel.eyebrow') }}</span>
        <h2>{{ t('adminSharedWidgets.taskPanel.title') }}</h2>
      </div>
    </div>

    <div class="admin-task-list">
      <article v-for="task in tasks" :key="task.title" class="admin-task-item">
        <div>
          <h3>{{ task.title }}</h3>
          <p>{{ task.helper }}</p>
        </div>
        <span class="admin-task-item__value">{{ task.value }}</span>
      </article>
    </div>
  </section>
</template>
