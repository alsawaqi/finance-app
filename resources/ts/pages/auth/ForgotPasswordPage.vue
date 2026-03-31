<template>
    <div style="padding: 24px;">
      <h1>{{ t('authPlaceholder.forgot.title') }}</h1>
      <p>{{ t('authPlaceholder.forgot.subtitle') }}</p>
  
      <nav style="display: flex; gap: 12px; margin-top: 16px;">
        <RouterLink to="/">{{ t('authPlaceholder.links.home') }}</RouterLink>
        <RouterLink to="/dashboard">{{ t('authPlaceholder.links.clientDashboard') }}</RouterLink>
        <RouterLink to="/admin">{{ t('authPlaceholder.links.adminDashboard') }}</RouterLink>
        <RouterLink to="/staff">{{ t('authPlaceholder.links.staffDashboard') }}</RouterLink>
      </nav>
    </div>
  </template>
  
  <script setup lang="ts">
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  </script>