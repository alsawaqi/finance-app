<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import axios from 'axios'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  password: '',
  confirmPassword: '',
  agree: true,
})

const showPassword = ref(false)
const showConfirmPassword = ref(false)
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

async function submitRegister() {
  resetErrors()

  const fullName = [form.value.firstName.trim(), form.value.lastName.trim()].filter(Boolean).join(' ')

  if (!fullName) {
    formError.value = 'Please enter your first name and last name.'
    return
  }

  if (!form.value.agree) {
    formError.value = 'Please accept the terms before creating your account.'
    return
  }

  try {
    await auth.register({
      name: fullName,
      email: form.value.email,
      phone: form.value.phone || undefined,
      password: form.value.password,
      password_confirmation: form.value.confirmPassword,
    })

    await router.push({ name: auth.dashboardRouteName })
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? 'Unable to create your account right now.'
      fieldErrors.value = error.response?.data?.errors ?? {}
      return
    }

    formError.value = 'Unable to create your account right now.'
  }
}
</script>

<template>
  <AuthPageShell>
    <section class="auth-hero-section">
      <div class="container">
        <div class="auth-page-head auth-reveal-up">
          <span class="auth-kicker">Create Your Account</span>
          <h2>A premium registration page</h2>
        </div>

        <div class="row g-4 align-items-stretch">
          <div class="col-lg-12 order-lg-2">
            <div class="auth-form-card auth-reveal-up">
              <div class="auth-form-card__shine"></div>

              <div class="auth-form-card__content">
                <div class="auth-form-top">
                  <span class="auth-mini-badge">
                    <i class="fas fa-user-plus"></i>
                    New Client Registration
                  </span>
                  <h3>Create your account</h3>
                  <p>
                    Start with a professional onboarding page that feels trustworthy and high-end.
                    New registrations create a client account and redirect to the client dashboard.
                  </p>
                </div>

                <div v-if="formError" class="auth-alert auth-alert--error">
                  {{ formError }}
                </div>

                <form class="auth-grid" @submit.prevent="submitRegister">
                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="register-first-name">First name</label>
                      <div class="auth-input-wrap">
                        <i class="far fa-user"></i>
                        <input
                          id="register-first-name"
                          v-model="form.firstName"
                          type="text"
                          class="auth-input"
                          placeholder="First name"
                          autocomplete="given-name"
                        />
                      </div>
                    </div>

                    <div class="auth-field">
                      <label for="register-last-name">Last name</label>
                      <div class="auth-input-wrap">
                        <i class="far fa-user"></i>
                        <input
                          id="register-last-name"
                          v-model="form.lastName"
                          type="text"
                          class="auth-input"
                          placeholder="Last name"
                          autocomplete="family-name"
                        />
                      </div>
                    </div>
                  </div>

                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="register-email">Email address</label>
                      <div class="auth-input-wrap">
                        <i class="far fa-envelope"></i>
                        <input
                          id="register-email"
                          v-model="form.email"
                          type="email"
                          class="auth-input"
                          :class="{ 'auth-input--error': firstFieldError('email') }"
                          placeholder="Email address"
                          autocomplete="email"
                        />
                      </div>
                      <small v-if="firstFieldError('email')" class="auth-field-error">{{ firstFieldError('email') }}</small>
                    </div>

                    <div class="auth-field">
                      <label for="register-phone">Phone number</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-phone-alt"></i>
                        <input
                          id="register-phone"
                          v-model="form.phone"
                          type="tel"
                          class="auth-input"
                          :class="{ 'auth-input--error': firstFieldError('phone') }"
                          placeholder="Phone number"
                          autocomplete="tel"
                        />
                      </div>
                      <small v-if="firstFieldError('phone')" class="auth-field-error">{{ firstFieldError('phone') }}</small>
                    </div>
                  </div>

                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="register-password">Password</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="register-password"
                          v-model="form.password"
                          :type="showPassword ? 'text' : 'password'"
                          class="auth-input"
                          :class="{ 'auth-input--error': firstFieldError('password') }"
                          placeholder="Create password"
                          autocomplete="new-password"
                        />
                        <button
                          type="button"
                          class="auth-password-toggle"
                          @click="showPassword = !showPassword"
                        >
                          {{ showPassword ? 'Hide' : 'Show' }}
                        </button>
                      </div>
                      <small v-if="firstFieldError('password')" class="auth-field-error">{{ firstFieldError('password') }}</small>
                    </div>

                    <div class="auth-field">
                      <label for="register-confirm-password">Confirm password</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="register-confirm-password"
                          v-model="form.confirmPassword"
                          :type="showConfirmPassword ? 'text' : 'password'"
                          class="auth-input"
                          placeholder="Confirm password"
                          autocomplete="new-password"
                        />
                        <button
                          type="button"
                          class="auth-password-toggle"
                          @click="showConfirmPassword = !showConfirmPassword"
                        >
                          {{ showConfirmPassword ? 'Hide' : 'Show' }}
                        </button>
                      </div>
                    </div>
                  </div>

                  <label class="auth-check">
                    <input v-model="form.agree" type="checkbox" />
                    <span>
                      I agree to the
                      <a href="#" class="auth-link" @click.prevent>Terms of Service</a>
                      and
                      <a href="#" class="auth-link" @click.prevent>Privacy Policy</a>
                    </span>
                  </label>

                  <button type="submit" class="btn_style_one auth-submit" :disabled="isSubmitting">
                    <span>{{ isSubmitting ? 'Creating Account...' : 'Create Account' }}</span>
                  </button>
                </form>

                <p class="auth-foot-note">
                  Already have an account?
                  <RouterLink to="/login" class="auth-text-link">Sign in here</RouterLink>
                </p>

                <p class="auth-policy">
                  This registration layout is intentionally prepared as a real onboarding page so you can move
                  straight from account creation into the client workflow.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </AuthPageShell>
</template>
