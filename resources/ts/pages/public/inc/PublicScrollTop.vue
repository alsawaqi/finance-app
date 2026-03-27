<template>
    <button
      class="scroll-top scroll-to-target"
      :class="{ open: show }"
      data-target="html"
      @click="$emit('click')"
    >
      <i class="fas fa-arrow-up"></i>
    </button>
  </template>
  
  <script setup lang="ts">
  defineProps<{
    show: boolean
  }>()
  
  defineEmits<{
    (e: 'click'): void
  }>()
  </script>