<script setup lang="ts">
const props = defineProps<{
  answers: any[]
  emptyText: string
  questionFallback: string
  formatAnswer: (answer: any) => string
  compact?: boolean
}>()
</script>

<template>
  <div v-if="props.answers?.length" class="qa-list" :class="{ 'compact-list': compact }">
    <div v-for="answer in props.answers" :key="answer.id" class="qa-item">
      <h3>{{ answer.question?.question_text || questionFallback }}</h3>
      <p>{{ formatAnswer(answer) }}</p>
    </div>
  </div>
  <p v-else class="empty-state">{{ emptyText }}</p>
</template>
