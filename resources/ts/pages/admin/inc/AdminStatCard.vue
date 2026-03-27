<script setup lang="ts">
defineProps<{
  label: string
  value: string
  trend: string
  icon: string
}>()
</script>

<template>
  <article class="admin-stat-card admin-reveal-up">
    <div class="admin-stat-card__top">
      <span class="admin-stat-card__icon"><i :class="icon"></i></span>
      <span class="admin-stat-card__trend">{{ trend }}</span>
    </div>
    <h3>{{ value }}</h3>
    <p>{{ label }}</p>
  </article>
</template>
