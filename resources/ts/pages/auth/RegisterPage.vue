<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import axios from 'axios'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import { allCountryPhoneCodeOptions } from '@/utils/countries'
import { validateNationalPhoneForCountry } from '@/utils/phoneValidation'

const router = useRouter()
const auth = useAuthStore()
const { t, locale } = useI18n()

const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  phoneCountryIso: 'SA',
  phoneNumber: '',
  password: '',
  confirmPassword: '',
  agree: true,
})

const touched = ref<Record<string, boolean>>({})

const countryCodeOptions = computed(() => allCountryPhoneCodeOptions(locale.value))
const selectedDialCode = computed(() => {
  const found = countryCodeOptions.value.find((item) => item.isoCode === form.value.phoneCountryIso)
  return found?.dialCode || '+966'
})

const showPassword = ref(false)
const showConfirmPassword = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isSubmitting = computed(() => auth.loading)

function markTouched(field: string) {
  touched.value[field] = true
}

const liveErrors = computed(() => {
  const errors: Record<string, string> = {}

  if (touched.value.email && form.value.email) {
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
      errors.email = t('authRegister.errors.invalidEmail')
    }
  }

  if (touched.value.phone && form.value.phoneNumber) {
    if (!validateNationalPhoneForCountry(form.value.phoneNumber, form.value.phoneCountryIso)) {
      errors.phone = t('authRegister.errors.invalidPhone')
    }
  }

  if (touched.value.password && form.value.password) {
    if (form.value.password.length < 8) {
      errors.password = t('authRegister.errors.passwordTooShort')
    }
  }

  if (touched.value.confirmPassword && form.value.confirmPassword) {
    if (form.value.password !== form.value.confirmPassword) {
      errors.confirmPassword = t('authRegister.errors.passwordMismatch')
    }
  }

  return errors
})

watch(
  () => form.value.phoneCountryIso,
  () => {
    if (touched.value.phone && form.value.phoneNumber) {
      // Force re-evaluation by touching the field again (computed reacts automatically)
    }
  },
)

function resetErrors() {
  formError.value = ''
  fieldErrors.value = {}
}

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

function displayError(field: string) {
  return firstFieldError(field) || liveErrors.value[field] || ''
}

function blockNonDigits(event: KeyboardEvent) {
  if (event.metaKey || event.ctrlKey || event.altKey) return
  if (['Backspace', 'Tab', 'ArrowLeft', 'ArrowRight', 'Delete', 'Home', 'End'].includes(event.key)) return
  if (!/^\d$/.test(event.key)) event.preventDefault()
}

function digitsOnlyPhone(value: string) {
  return value.replace(/\D/g, '')
}

async function submitRegister() {
  resetErrors()
  Object.keys(form.value).forEach((key) => { touched.value[key] = true })

  if (Object.keys(liveErrors.value).length > 0) return

  const fullName = [form.value.firstName.trim(), form.value.lastName.trim()].filter(Boolean).join(' ')

  if (!fullName) {
    formError.value = t('authRegister.errors.fullNameRequired')
    return
  }

  if (!form.value.agree) {
    formError.value = t('authRegister.errors.acceptTermsRequired')
    return
  }

  if (form.value.phoneNumber && !validateNationalPhoneForCountry(form.value.phoneNumber, form.value.phoneCountryIso)) {
    fieldErrors.value = {
      ...fieldErrors.value,
      phone: [t('authRegister.errors.invalidPhone')],
    }
    return
  }

  try {
    await auth.register({
      name: fullName,
      email: form.value.email,
      phone_country_code: form.value.phoneNumber ? selectedDialCode.value : undefined,
      phone: form.value.phoneNumber || undefined,
      password: form.value.password,
      password_confirmation: form.value.confirmPassword,
    })

    if (!auth.isVerified) {
  await router.push({ name: 'verify-email' })
  return
}

await router.push({ name: auth.dashboardRouteName })
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? t('authRegister.errors.unableToCreate')
      fieldErrors.value = error.response?.data?.errors ?? {}
      return
    }

    formError.value = t('authRegister.errors.unableToCreate')
  }
}
</script>

<template>
  <AuthPageShell>
    <section class="auth-hero-section">
      <div class="container">
        <div class="auth-page-head auth-reveal-up">
          <span class="auth-kicker">{{ t('authRegister.hero.kicker') }}</span>
          <h2>{{ t('authRegister.hero.title') }}</h2>
        </div>

        <div class="row g-4 align-items-stretch">
          <div class="col-lg-12 order-lg-2">
            <div class="auth-form-card auth-reveal-up">
              <div class="auth-form-card__shine"></div>

              <div class="auth-form-card__content">
                <div class="auth-form-top">
                  <span class="auth-mini-badge">
                    <i class="fas fa-user-plus"></i>
                    {{ t('authRegister.card.badge') }}
                  </span>
                  <h3>{{ t('authRegister.card.title') }}</h3>
                  <p>
                    {{ t('authRegister.card.subtitle') }}
                  </p>
                </div>

                <div v-if="formError" class="auth-alert auth-alert--error">
                  {{ formError }}
                </div>

                <form class="auth-grid" @submit.prevent="submitRegister">
                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="register-first-name">{{ t('authRegister.form.firstNameLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="far fa-user"></i>
                        <input
                          id="register-first-name"
                          v-model="form.firstName"
                          type="text"
                          class="auth-input"
                          :placeholder="t('authRegister.form.firstNamePlaceholder')"
                          autocomplete="given-name"
                        />
                      </div>
                    </div>

                    <div class="auth-field">
                      <label for="register-last-name">{{ t('authRegister.form.lastNameLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="far fa-user"></i>
                        <input
                          id="register-last-name"
                          v-model="form.lastName"
                          type="text"
                          class="auth-input"
                          :placeholder="t('authRegister.form.lastNamePlaceholder')"
                          autocomplete="family-name"
                        />
                      </div>
                    </div>
                  </div>

                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="register-email">{{ t('authRegister.form.emailLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="far fa-envelope"></i>
                        <input
                          id="register-email"
                          v-model="form.email"
                          type="email"
                          class="auth-input"
                          :class="{ 'auth-input--error': displayError('email') }"
                          :placeholder="t('authRegister.form.emailPlaceholder')"
                          autocomplete="email"
                          @blur="markTouched('email')"
                        />
                      </div>
                      <small v-if="displayError('email')" class="auth-field-error">{{ displayError('email') }}</small>
                    </div>

                    <div class="auth-field auth-field--phone">
                      <label for="register-phone-number">{{ t('authRegister.form.phoneNumberLabel') }}</label>
                      <div class="auth-grid auth-grid--phone">
                        <div class="auth-input-wrap">
                          <i class="fas fa-globe"></i>
                          <select
                            id="register-phone-country"
                            v-model="form.phoneCountryIso"
                            class="auth-select"
                            :class="{ 'auth-input--error': firstFieldError('phone_country_code') }"
                          >
                            <option v-for="country in countryCodeOptions" :key="country.isoCode" :value="country.isoCode">
                              {{ country.label }}
                            </option>
                          </select>
                        </div>

                        <div class="auth-input-wrap">
                          <i class="fas fa-phone-alt"></i>
                          <input
                            id="register-phone-number"
                            :value="form.phoneNumber"
                            type="tel"
                            inputmode="numeric"
                            autocomplete="tel-national"
                            pattern="[0-9]*"
                            class="auth-input"
                            :class="{ 'auth-input--error': displayError('phone') }"
                            :placeholder="t('authRegister.form.phoneNumberPlaceholder')"
                            @keypress="blockNonDigits"
                            @input="form.phoneNumber = digitsOnlyPhone(($event.target as HTMLInputElement).value); markTouched('phone')"
                            @blur="markTouched('phone')"
                          />
                        </div>
                      </div>
                      <small v-if="firstFieldError('phone_country_code')" class="auth-field-error">
                        {{ firstFieldError('phone_country_code') }}
                      </small>
                      <small v-if="displayError('phone')" class="auth-field-error">{{ displayError('phone') }}</small>
                    </div>
                  </div>

                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="register-password">{{ t('authRegister.form.passwordLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="register-password"
                          v-model="form.password"
                          :type="showPassword ? 'text' : 'password'"
                          class="auth-input"
                          :class="{ 'auth-input--error': displayError('password') }"
                          :placeholder="t('authRegister.form.passwordPlaceholder')"
                          autocomplete="new-password"
                          @blur="markTouched('password')"
                        />
                        <button
                          type="button"
                          class="auth-password-toggle"
                          @click="showPassword = !showPassword"
                        >
                          {{ showPassword ? t('authRegister.form.hide') : t('authRegister.form.show') }}
                        </button>
                      </div>
                      <small v-if="displayError('password')" class="auth-field-error">{{ displayError('password') }}</small>
                    </div>

                    <div class="auth-field">
                      <label for="register-confirm-password">{{ t('authRegister.form.confirmPasswordLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="register-confirm-password"
                          v-model="form.confirmPassword"
                          :type="showConfirmPassword ? 'text' : 'password'"
                          class="auth-input"
                          :class="{ 'auth-input--error': displayError('confirmPassword') }"
                          :placeholder="t('authRegister.form.confirmPasswordPlaceholder')"
                          autocomplete="new-password"
                          @blur="markTouched('confirmPassword')"
                        />
                        <button
                          type="button"
                          class="auth-password-toggle"
                          @click="showConfirmPassword = !showConfirmPassword"
                        >
                          {{ showConfirmPassword ? t('authRegister.form.hide') : t('authRegister.form.show') }}
                        </button>
                      </div>
                      <small v-if="displayError('confirmPassword')" class="auth-field-error">{{ displayError('confirmPassword') }}</small>
                    </div>
                  </div>

                  <label class="auth-check">
                    <input v-model="form.agree" type="checkbox" />
                    <span>
                      {{ t('authRegister.form.agreePrefix') }}
                      <a href="#" class="auth-link" @click.prevent>{{ t('authRegister.form.termsOfService') }}</a>
                      {{ t('authRegister.form.agreeAnd') }}
                      <a href="#" class="auth-link" @click.prevent>{{ t('authRegister.form.privacyPolicy') }}</a>
                    </span>
                  </label>

                  <button type="submit" class="btn_style_one auth-submit" :disabled="isSubmitting">
                    <span>{{ isSubmitting ? t('authRegister.form.creatingAccount') : t('authRegister.form.createAccount') }}</span>
                  </button>
                </form>

                <p class="auth-foot-note">
                  {{ t('authRegister.footer.haveAccount') }}
                  <RouterLink to="/login" class="auth-text-link">{{ t('authRegister.footer.signInHere') }}</RouterLink>
                </p>

                <p class="auth-policy">
                  {{ t('authRegister.footer.policy') }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </AuthPageShell>
</template>

<style scoped>
.auth-grid--phone {
  grid-template-columns: minmax(112px, 0.85fr) minmax(0, 1.15fr);
}

@media (max-width: 767px) {
  .auth-grid--phone {
    grid-template-columns: 1fr;
  }
}
</style>
