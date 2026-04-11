<script setup lang="ts">
import AppToastStack from '@/components/AppToastStack.vue'
import AppTopProgressBar from '@/components/AppTopProgressBar.vue'
import AppTransactionOverlay from '@/components/AppTransactionOverlay.vue'
import AppSessionLoadingOverlay from '@/components/AppSessionLoadingOverlay.vue'
</script>

<template>
  <AppTopProgressBar />
  <RouterView />
  <AppSessionLoadingOverlay />
  <AppTransactionOverlay />
  <AppToastStack />
</template>
