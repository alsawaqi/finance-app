<script setup lang="ts">
import { useI18n } from 'vue-i18n'

type Stat = {
  value: string
  label: string
}

defineProps<{
  eyebrow: string
  title: string
  description: string
  bullets: string[]
  stats: Stat[]
  image: string
}>()

const { t } = useI18n()
</script>

<template>
  <div class="auth-showcase auth-reveal-left">
    <div class="auth-showcase__glow auth-showcase__glow--one"></div>
    <div class="auth-showcase__glow auth-showcase__glow--two"></div>

    <div class="auth-showcase__content">
      <span class="auth-eyebrow">{{ eyebrow }}</span>
      <h1>{{ title }}</h1>
      <p>{{ description }}</p>

      <ul class="auth-bullets list-unstyled">
        <li v-for="item in bullets" :key="item">
          <span class="auth-bullets__icon"><i class="fas fa-check"></i></span>
          <span>{{ item }}</span>
        </li>
      </ul>

      <div class="auth-stats-grid">
        <div v-for="stat in stats" :key="stat.label" class="auth-stat-card">
          <h3>{{ stat.value }}</h3>
          <span>{{ stat.label }}</span>
        </div>
      </div>
    </div>

    <div class="auth-showcase__media">
      <div class="auth-media-card">
        <img :src="image" alt="Finance platform preview" />
      </div>

      <div class="auth-floating-card auth-floating-card--top auth-reveal-float">
        <span class="auth-floating-card__label">{{ t('authShowcase.smartOnboarding') }}</span>
        <strong>{{ t('authShowcase.fastSecureProfessional') }}</strong>
      </div>

      <div class="auth-floating-card auth-floating-card--bottom auth-reveal-float auth-reveal-float-delay">
        <span class="auth-floating-card__label">{{ t('authShowcase.trustedWorkflow') }}</span>
        <strong>{{ t('authShowcase.builtForAllRoles') }}</strong>
      </div>
    </div>
  </div>
</template>