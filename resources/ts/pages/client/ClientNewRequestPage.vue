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
          {{ hasDraft ? t('clientNewRequest.card.resumeNote') : t('clientNewRequest.card.note') }}
        </div>

        <div v-if="hasDraft" class="client-alert client-alert--success">
          {{ t('clientNewRequest.card.draftDetected') }}
        </div>

        <div class="client-inline-actions">
          <button
            v-if="hasDraft"
            type="button"
            class="client-btn-primary"
            @click="resumeDraft"
          >
            {{ t('clientNewRequest.card.resume') }}
          </button>

          <button
            v-if="hasDraft"
            type="button"
            class="client-btn-secondary"
            @click="discardDraftAndStart"
          >
            {{ t('clientNewRequest.card.discardAndStart') }}
          </button>

          <RouterLink
            v-else
            :to="{ name: 'client-request-wizard' }"
            class="client-btn-primary"
          >
            {{ t('clientNewRequest.card.start') }}
          </RouterLink>
        </div>
      </article>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { clearClientRequestDraft, hasClientRequestDraft } from '@/utils/clientRequestDraft'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const draftUserId = computed(() => auth.user?.id ?? 'guest')
const hasDraft = computed(() => hasClientRequestDraft(draftUserId.value))

function resumeDraft() {
  router.push({ name: 'client-request-wizard' })
}

function discardDraftAndStart() {
  clearClientRequestDraft(draftUserId.value)
  router.push({ name: 'client-request-wizard' })
}
</script>