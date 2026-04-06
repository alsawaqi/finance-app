<script setup lang="ts">
import AppToastStack from '@/components/AppToastStack.vue'
import AppTopProgressBar from '@/components/AppTopProgressBar.vue'
</script>

<template>
  <AppTopProgressBar />
  <RouterView />
  <AppToastStack />
</template>
