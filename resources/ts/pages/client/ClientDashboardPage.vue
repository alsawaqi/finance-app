<template>
  <section class="shell">
    <div class="card hero">
      <div>
        <p class="eyebrow">{{ t('clientDashboardLegacy.eyebrow') }}</p>
        <h1>{{ t('clientDashboardLegacy.title') }}</h1>
        <p>{{ t('clientDashboardLegacy.subtitle') }}</p>
      </div>
      <div class="actions">
        <RouterLink to="/dashboard/requests" class="primary-btn">{{ t('clientDashboardLegacy.myRequests') }}</RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<style scoped>
.shell { padding:28px; max-width:1200px; margin:0 auto; }
.card { background:#fff; border:1px solid #e2e8f0; border-radius:22px; box-shadow:0 18px 45px rgba(15,23,42,.06); padding:24px; }
.hero { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
.eyebrow { margin:0 0 6px; color:#7c3aed; text-transform:uppercase; font-size:12px; font-weight:700; letter-spacing:.08em; }
h1 { margin:0; }
p { margin:8px 0 0; color:#64748b; max-width:640px; }
.actions { display:flex; gap:12px; }
.primary-btn { border-radius:12px; padding:10px 14px; text-decoration:none; font-weight:700; font-size:14px; background:#2563eb; border:1px solid #2563eb; color:#fff; }
@media (max-width:768px){ .shell{padding:16px;} .hero{flex-direction:column; align-items:flex-start;} }
</style>
