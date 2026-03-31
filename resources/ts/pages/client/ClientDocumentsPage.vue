<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const files = [
  { title: 'Bank Statements.pdf', meta: 'REQ-2026-014 · 2.3 MB' },
  { title: 'Signed Contract Draft.pdf', meta: 'REQ-2026-011 · 1.1 MB' },
  { title: 'Financial Profile.xlsx', meta: 'REQ-2026-009 · 850 KB' },
]

const { t } = useI18n()
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">{{ t('clientDocumentsLegacy.hero.eyebrow') }}</span>
      <h1 class="client-hero-title">{{ t('clientDocumentsLegacy.hero.title') }}</h1>
      <p class="client-hero-text">
        {{ t('clientDocumentsLegacy.hero.subtitle') }}
      </p>
    </section>

    <section class="client-upload-grid client-reveal-left">
      <article class="client-upload-card">
        <h3>{{ t('clientDocumentsLegacy.cards.uploadNewFile') }}</h3>
        <p class="client-muted">{{ t('clientDocumentsLegacy.cards.uploadNewFileText') }}</p>
        <div class="client-inline-actions">
          <button type="button" class="client-btn-primary">{{ t('clientDocumentsLegacy.actions.chooseFiles') }}</button>
        </div>
      </article>

      <article class="client-upload-card">
        <h3>{{ t('clientDocumentsLegacy.cards.pendingVerification') }}</h3>
        <p class="client-muted">{{ t('clientDocumentsLegacy.cards.pendingVerificationText') }}</p>
        <span class="client-badge client-badge--amber">{{ t('clientDocumentsLegacy.states.pendingCount') }}</span>
      </article>

      <article class="client-upload-card">
        <h3>{{ t('clientDocumentsLegacy.cards.approvedLibrary') }}</h3>
        <p class="client-muted">{{ t('clientDocumentsLegacy.cards.approvedLibraryText') }}</p>
        <span class="client-badge client-badge--green">{{ t('clientDocumentsLegacy.states.approvedCount') }}</span>
      </article>
    </section>

    <section class="client-card-grid client-reveal-up">
      <article class="client-content-card client-content-card--full">
        <div class="client-card-head">
          <div>
            <h3>{{ t('clientDocumentsLegacy.recentFiles.title') }}</h3>
            <p class="client-subtext">{{ t('clientDocumentsLegacy.recentFiles.subtitle') }}</p>
          </div>
        </div>
        <div class="client-file-list">
          <div v-for="file in files" :key="file.title" class="client-file-item">
            <div>
              <strong>{{ file.title }}</strong>
              <p class="client-meta">{{ t('clientDocumentsLegacy.recentFiles.uploadedFor', { meta: file.meta }) }}</p>
            </div>
            <button type="button" class="client-btn-secondary">{{ t('clientDocumentsLegacy.actions.viewFile') }}</button>
          </div>
        </div>
      </article>
    </section>
  </div>
</template>
