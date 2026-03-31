<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { t } = useI18n()

const form = ref({
  email: '',
  password: '',
  remember: true,
})

const showPassword = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isSubmitting = computed(() => auth.loading)

function resetErrors() {
  formError.value = ''
  fieldErrors.value = {}
}

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

async function submitLogin() {
  resetErrors()

  try {
    await auth.login({
      email: form.value.email,
      password: form.value.password,
      remember: form.value.remember,
    })

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null

    if (redirect) {
      await router.push(redirect)
      return
    }

    await router.push({ name: auth.dashboardRouteName })
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? t('authLogin.errors.unableToSignIn')
      fieldErrors.value = error.response?.data?.errors ?? {}
      return
    }

    formError.value = t('authLogin.errors.unableToSignIn')
  }
}
</script>

<template>
  <AuthPageShell>
    <section class="auth-hero-section">
      <div class="container">
        <div class="auth-page-head auth-reveal-up">
          <span class="auth-kicker">{{ t('authLogin.hero.kicker') }}</span>
          <h2>{{ t('authLogin.hero.title') }}</h2>
          <p>
            {{ t('authLogin.hero.subtitle') }}
          </p>
        </div>

        <div class="row g-4 align-items-stretch">
          <div class="col-lg-12">
            <div class="auth-form-card auth-reveal-up">
              <div class="auth-form-card__shine"></div>

              <div class="auth-form-card__content">
                <div class="auth-form-top">
                  <span class="auth-mini-badge">
                    <i class="fas fa-shield-alt"></i>
                    {{ t('authLogin.card.badge') }}
                  </span>
                  <h3>{{ t('authLogin.card.title') }}</h3>
                  <p>
                    {{ t('authLogin.card.subtitle') }}
                  </p>
                </div>

                <div v-if="formError" class="auth-alert auth-alert--error">
                  {{ formError }}
                </div>

                <form class="auth-grid" @submit.prevent="submitLogin">
                  <div class="auth-field">
                    <label for="login-email">{{ t('authLogin.form.emailLabel') }}</label>
                    <div class="auth-input-wrap">
                      <i class="far fa-envelope"></i>
                      <input
                        id="login-email"
                        v-model="form.email"
                        type="email"
                        class="auth-input"
                        :class="{ 'auth-input--error': firstFieldError('email') }"
                        :placeholder="t('authLogin.form.emailPlaceholder')"
                        autocomplete="email"
                      />
                    </div>
                    <small v-if="firstFieldError('email')" class="auth-field-error">{{ firstFieldError('email') }}</small>
                  </div>

                  <div class="auth-field">
                    <label for="login-password">{{ t('authLogin.form.passwordLabel') }}</label>
                    <div class="auth-input-wrap">
                      <i class="fas fa-lock"></i>
                      <input
                        id="login-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="auth-input"
                        :class="{ 'auth-input--error': firstFieldError('password') }"
                        :placeholder="t('authLogin.form.passwordPlaceholder')"
                        autocomplete="current-password"
                      />
                      <button
                        type="button"
                        class="auth-password-toggle"
                        @click="showPassword = !showPassword"
                      >
                        {{ showPassword ? t('authLogin.form.hide') : t('authLogin.form.show') }}
                      </button>
                    </div>
                    <small v-if="firstFieldError('password')" class="auth-field-error">{{ firstFieldError('password') }}</small>
                  </div>

                  <div class="auth-row-between">
                    <label class="auth-check">
                      <input v-model="form.remember" type="checkbox" />
                      <span>{{ t('authLogin.form.keepSignedIn') }}</span>
                    </label>

                    <RouterLink to="/forgot-password" class="auth-link">
                      {{ t('authLogin.form.forgotPassword') }}
                    </RouterLink>
                  </div>

                  <button type="submit" class="btn_style_one auth-submit" :disabled="isSubmitting">
                    <span>{{ isSubmitting ? t('authLogin.form.signingIn') : t('authLogin.form.signIn') }}</span>
                  </button>
                </form>

                <p class="auth-foot-note">
                  {{ t('authLogin.footer.noAccount') }}
                  <RouterLink to="/register" class="auth-text-link">{{ t('authLogin.footer.createNow') }}</RouterLink>
                </p>

                <p class="auth-policy">
                  {{ t('authLogin.footer.policy') }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </AuthPageShell>
</template>
