<template>
  <ClientPageShell>
    <main class="client-shell-main">
      <div class="container">
        <router-view />
      </div>
    </main>
  </ClientPageShell>
</template>

<script setup lang="ts">
import ClientPageShell from '../public/inc/ClientPageShell.vue'
</script>
