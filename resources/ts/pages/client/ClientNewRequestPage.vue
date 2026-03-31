<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">{{ t('clientNewRequest.hero.eyebrow') }}</span>
      <h1 class="client-hero-title">{{ t('clientNewRequest.hero.title') }}</h1>
      <p class="client-hero-text">
        {{ t('clientNewRequest.hero.subtitle') }}
      </p>
    </section>

    <section class="client-card-grid client-reveal-left">
      <article class="client-content-card client-content-card--full client-start-card">
        <div class="client-card-head">
          <div>
            <h3>{{ t('clientNewRequest.card.title') }}</h3>
            <p class="client-subtext">
              {{ t('clientNewRequest.card.subtitle') }}
            </p>
          </div>
          <span class="client-badge client-badge--purple">{{ t('clientNewRequest.card.stepsBadge') }}</span>
        </div>

        <div class="client-empty-note">
          {{ t('clientNewRequest.card.note') }}
        </div>

        <div class="client-inline-actions">
          <RouterLink :to="{ name: 'client-request-wizard' }" class="client-btn-primary">{{ t('clientNewRequest.card.start') }}</RouterLink>
        </div>
      </article>
    </section>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
