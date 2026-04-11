<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">{{ t('clientNewRequest.hero.eyebrow') }}</span>
      <h1 class="client-hero-title">{{ t('clientNewRequest.hero.title') }}</h1>
      <p class="client-hero-text">
        {{ t('clientNewRequest.hero.subtitle') }}
      </p>
    </section>

    <section v-if="!isVerified" class="client-verification-banner client-reveal-up">
      <h3 class="client-verification-banner__title">{{ t('clientNewRequest.verification.title') }}</h3>
      <p class="client-verification-banner__text">{{ t('clientNewRequest.verification.text') }}</p>
      <div class="client-verification-banner__actions">
        <button
          type="button"
          class="client-verification-banner__btn"
          :disabled="resendingVerification"
          @click="resendVerification"
        >
          {{ resendingVerification ? t('clientNewRequest.verification.resending') : t('clientNewRequest.verification.resendButton') }}
        </button>
        <RouterLink :to="{ name: 'client-dashboard' }" class="client-btn-secondary">
          {{ t('clientNewRequest.verification.backToDashboard') }}
        </RouterLink>
      </div>
      <p
        v-if="verificationFeedback"
        class="client-verification-banner__feedback"
        :class="verificationFeedback.type === 'success' ? 'client-verification-banner__feedback--success' : 'client-verification-banner__feedback--error'"
      >
        {{ verificationFeedback.text }}
      </p>
    </section>

    <section v-else class="client-card-grid client-reveal-left">
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
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { clearClientRequestDraft, hasClientRequestDraft } from '@/utils/clientRequestDraft'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const isVerified = computed(() => !!auth.user?.email_verified_at)
const resendingVerification = ref(false)
const verificationFeedback = ref<{ type: 'success' | 'error'; text: string } | null>(null)

async function resendVerification() {
  resendingVerification.value = true
  verificationFeedback.value = null
  try {
    await auth.resendVerification()
    verificationFeedback.value = { type: 'success', text: t('clientNewRequest.verification.resent') }
  } catch {
    verificationFeedback.value = { type: 'error', text: t('clientNewRequest.verification.resendError') }
  } finally {
    resendingVerification.value = false
  }
}

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