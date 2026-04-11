<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { changeClientPassword } from '@/services/clientPortal'

const { t } = useI18n()

const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const touched = reactive({
  current_password: false,
  password: false,
  password_confirmation: false,
})

const submitting = ref(false)
const successMessage = ref('')
const serverError = ref('')
const serverFieldErrors = ref<Record<string, string[]>>({})

const liveErrors = computed(() => {
  const errors: Record<string, string> = {}

  if (touched.current_password && !form.current_password) {
    errors.current_password = t('clientChangePassword.validation.currentPasswordRequired')
  }

  if (touched.password) {
    if (!form.password) {
      errors.password = t('clientChangePassword.validation.newPasswordRequired')
    } else if (form.password.length < 8) {
      errors.password = t('clientChangePassword.validation.newPasswordMinLength')
    } else if (!/[a-zA-Z]/.test(form.password)) {
      errors.password = t('clientChangePassword.validation.newPasswordLetters')
    } else if (!/\d/.test(form.password)) {
      errors.password = t('clientChangePassword.validation.newPasswordNumbers')
    } else if (form.password === form.current_password) {
      errors.password = t('clientChangePassword.validation.sameAsCurrent')
    }
  }

  if (touched.password_confirmation) {
    if (!form.password_confirmation) {
      errors.password_confirmation = t('clientChangePassword.validation.confirmPasswordRequired')
    } else if (form.password_confirmation !== form.password) {
      errors.password_confirmation = t('clientChangePassword.validation.confirmPasswordMismatch')
    }
  }

  return errors
})

const canSubmit = computed(() => {
  return (
    form.current_password.length > 0 &&
    form.password.length >= 8 &&
    /[a-zA-Z]/.test(form.password) &&
    /\d/.test(form.password) &&
    form.password !== form.current_password &&
    form.password_confirmation === form.password &&
    !submitting.value
  )
})

function markTouched(field: keyof typeof touched) {
  touched[field] = true
}

function fieldError(field: string): string {
  return liveErrors.value[field] || (serverFieldErrors.value[field]?.[0] ?? '')
}

async function handleSubmit() {
  touched.current_password = true
  touched.password = true
  touched.password_confirmation = true

  if (!canSubmit.value) return

  submitting.value = true
  successMessage.value = ''
  serverError.value = ''
  serverFieldErrors.value = {}

  try {
    await changeClientPassword({
      current_password: form.current_password,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    successMessage.value = t('clientChangePassword.success')
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    touched.current_password = false
    touched.password = false
    touched.password_confirmation = false
  } catch (error: any) {
    const resp = error?.response
    if (resp?.status === 422 && resp?.data?.errors) {
      serverFieldErrors.value = resp.data.errors
    }
    serverError.value = resp?.data?.message || 'Something went wrong.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">{{ t('clientChangePassword.hero.eyebrow') }}</span>
      <h1 class="client-hero-title">{{ t('clientChangePassword.hero.title') }}</h1>
      <p class="client-hero-text">{{ t('clientChangePassword.hero.subtitle') }}</p>
    </section>

    <section class="client-card-grid client-reveal-left">
      <article class="client-content-card client-content-card--full">
        <form class="client-change-pw-form" @submit.prevent="handleSubmit">
          <div v-if="successMessage" class="client-alert client-alert--success">{{ successMessage }}</div>
          <div v-if="serverError && !successMessage" class="client-alert client-alert--error">{{ serverError }}</div>

          <div class="client-form-group">
            <label class="client-form-label" for="cp-current">{{ t('clientChangePassword.form.currentPassword') }}</label>
            <input
              id="cp-current"
              v-model="form.current_password"
              type="password"
              class="client-form-input"
              :class="{ 'client-form-input--error': fieldError('current_password') }"
              :placeholder="t('clientChangePassword.form.currentPasswordPlaceholder')"
              autocomplete="current-password"
              @blur="markTouched('current_password')"
            />
            <p v-if="fieldError('current_password')" class="client-form-error">{{ fieldError('current_password') }}</p>
          </div>

          <div class="client-form-group">
            <label class="client-form-label" for="cp-new">{{ t('clientChangePassword.form.newPassword') }}</label>
            <input
              id="cp-new"
              v-model="form.password"
              type="password"
              class="client-form-input"
              :class="{ 'client-form-input--error': fieldError('password') }"
              :placeholder="t('clientChangePassword.form.newPasswordPlaceholder')"
              autocomplete="new-password"
              @blur="markTouched('password')"
            />
            <p v-if="fieldError('password')" class="client-form-error">{{ fieldError('password') }}</p>
          </div>

          <div class="client-form-group">
            <label class="client-form-label" for="cp-confirm">{{ t('clientChangePassword.form.confirmPassword') }}</label>
            <input
              id="cp-confirm"
              v-model="form.password_confirmation"
              type="password"
              class="client-form-input"
              :class="{ 'client-form-input--error': fieldError('password_confirmation') }"
              :placeholder="t('clientChangePassword.form.confirmPasswordPlaceholder')"
              autocomplete="new-password"
              @blur="markTouched('password_confirmation')"
            />
            <p v-if="fieldError('password_confirmation')" class="client-form-error">{{ fieldError('password_confirmation') }}</p>
          </div>

          <div class="client-inline-actions">
            <button
              type="submit"
              class="client-btn-primary"
              :disabled="!canSubmit"
            >
              {{ submitting ? t('clientChangePassword.form.submitting') : t('clientChangePassword.form.submit') }}
            </button>
            <RouterLink :to="{ name: 'client-dashboard' }" class="client-btn-secondary">
              {{ t('clientChangePassword.backToDashboard') }}
            </RouterLink>
          </div>
        </form>
      </article>
    </section>
  </div>
</template>
