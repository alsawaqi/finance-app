<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthPageShell from '../public/inc/AuthPageShell.vue'


const form = ref({
  email: '',
  password: '',
  remember: true,
})

const showPassword = ref(false)
</script>

<template>
  <AuthPageShell>

    <section class="auth-hero-section">
      <div class="container">
        <div class="auth-page-head auth-reveal-up">
          <span class="auth-kicker">Access Portal</span>
          <h2>Sign in</h2>
          <p>
            Your login screen should feel like part of the same premium finance platform —
            secure, modern, and visually consistent from the first click.
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
                    Secure Login
                  </span>
                  <h3>Login to your account</h3>
                  <p>
                    Enter your account details to continue to your client portal. This is the design-first version,
                    ready for you to connect with the real authentication logic next.
                  </p>
                </div>

                <form class="auth-grid" @submit.prevent>
                  <div class="auth-field">
                    <label for="login-email">Email address</label>
                    <div class="auth-input-wrap">
                      <i class="far fa-envelope"></i>
                      <input
                        id="login-email"
                        v-model="form.email"
                        type="email"
                        class="auth-input"
                        placeholder="Enter your email"
                        autocomplete="email"
                      />
                    </div>
                  </div>

                  <div class="auth-field">
                    <label for="login-password">Password</label>
                    <div class="auth-input-wrap">
                      <i class="fas fa-lock"></i>
                      <input
                        id="login-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="auth-input"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                      />
                      <button
                        type="button"
                        class="auth-password-toggle"
                        @click="showPassword = !showPassword"
                      >
                        {{ showPassword ? 'Hide' : 'Show' }}
                      </button>
                    </div>
                  </div>

                  <div class="auth-row-between">
                    <label class="auth-check">
                      <input v-model="form.remember" type="checkbox" />
                      <span>Keep me signed in</span>
                    </label>

                    <RouterLink to="/forgot-password" class="auth-link">
                      Forgot password?
                    </RouterLink>
                  </div>

                  <button type="submit" class="btn_style_one auth-submit">
                    Sign In
                  </button>
                </form>

                <div class="auth-divider">
                  <span>or continue with</span>
                </div>

                <div class="auth-social-grid">
                  <button type="button" class="auth-social-btn">
                    <i class="fab fa-google"></i>
                    Google
                  </button>
                  <button type="button" class="auth-social-btn">
                    <i class="fab fa-apple"></i>
                    Apple
                  </button>
                </div>

                <p class="auth-foot-note">
                  Don’t have an account?
                  <RouterLink to="/register" class="auth-text-link">Create one now</RouterLink>
                </p>

                <p class="auth-policy">
                  By signing in, you continue into a secure finance experience built to feel smooth, trustworthy,
                  and aligned with your public-facing brand.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </AuthPageShell>
</template>