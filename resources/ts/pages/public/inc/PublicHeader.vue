<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useAppLocale } from '@/composables/useAppLocale'
import type { AppLocale } from '@/i18n'

const route = useRoute()
const auth = useAuthStore()
const { t } = useI18n()
const { currentLocale, localeOptions, changeLocale } = useAppLocale()

if (!auth.initialized) {
  auth.init()
}

const isAuthenticated = computed(() => auth.isAuthenticated)
const dashboardRoute = computed(() => ({ name: auth.dashboardRouteName }))
const isDashboardActive = computed(() => route.path.startsWith('/dashboard') || route.path.startsWith('/admin') || route.path.startsWith('/staff'))

function isActive(path: string) {
  return route.path === path
}

function onLocaleChange(event: Event) {
  const target = event.target as HTMLSelectElement | null
  if (!target) return
  changeLocale(target.value as AppLocale)
}

defineProps<{
  isSticky: boolean
  mobileMenuOpen: boolean
  mobileDropdowns: Record<string, boolean>
}>()

defineEmits<{
  (e: 'open-search'): void
  (e: 'open-mobile-menu'): void
  (e: 'close-mobile-menu'): void
  (e: 'toggle-mobile-dropdown', key: string): void
}>()
</script>

<template>
  <header class="header" :class="{ fixed_header: isSticky }">
    <div class="main_header">
      <div class="container">
        <div class="main_header_inner">
          <div class="main_header_logo">
            <figure>
              <RouterLink to="/">
                <img src="/financer/assets/images/logo.png" alt="Companny Logo" />
              </RouterLink>
            </figure>
          </div>

          <div class="main_header_menu menu_area">
            <div class="mobile-nav-toggler" @click="$emit('open-mobile-menu')">
              <div class="menu-bar">
                <i class="fas fa-bars"></i>
              </div>
            </div>

            <nav class="main-menu">
              <div class="nav-outer">
                <ul class="navigation">
                  <li :class="{ active: isActive('/') }">
                    <RouterLink to="/">{{ t('publicHeader.home') }}</RouterLink>
                  </li>

                  <li :class="{ active: isActive('/about') }">
                    <RouterLink to="/about">{{ t('publicHeader.about') }}</RouterLink>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>{{ t('publicHeader.services') }}</a>
                    <ul>
                      <li><a href="#" @click.prevent>{{ t('publicHeader.services') }}</a></li>
                      <li><a href="#" @click.prevent>Services Two</a></li>
                      <li><a href="#" @click.prevent>Service Details</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>{{ t('publicHeader.project') }}</a>
                    <ul>
                      <li><a href="#" @click.prevent>{{ t('publicHeader.project') }}</a></li>
                      <li><a href="#" @click.prevent>Project Details</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>{{ t('publicHeader.pages') }}</a>
                    <ul>
                      <li><a href="#" @click.prevent>Faq's</a></li>
                      <li><a href="#" @click.prevent>Error Page</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>{{ t('publicHeader.news') }}</a>
                    <ul>
                      <li><a href="#" @click.prevent>Blog Grid</a></li>
                      <li><a href="#" @click.prevent>Blog Standard</a></li>
                      <li><a href="#" @click.prevent>Blog Details</a></li>
                    </ul>
                  </li>

                  <li><a href="#" @click.prevent>{{ t('publicHeader.contact') }}</a></li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content auth-header-actions-wrap">
            <div class="header-language-switcher">
              <label class="visually-hidden" for="public-language-switcher">{{ t('common.languageLabel') }}</label>
              <div class="language-select-shell">
                <span class="language-select-icon"><i class="fa-solid fa-earth-asia"></i></span>
                <select
                  id="public-language-switcher"
                  class="language-select"
                  :value="currentLocale"
                  @change="onLocaleChange"
                >
                  <option
                    v-for="option in localeOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </div>
            </div>

            <button class="search-toggler" @click="$emit('open-search')">
              <i class="icon-50"></i>
            </button>

            <div class="auth-header-actions" v-if="!isAuthenticated">
              <RouterLink
                to="/login"
                class="auth-btn auth-btn-login"
                :class="{ active: isActive('/login') }"
              >
                {{ t('publicHeader.login') }}
              </RouterLink>

              <RouterLink
                to="/register"
                class="auth-btn auth-btn-register"
                :class="{ active: isActive('/register') }"
              >
                {{ t('publicHeader.register') }}
              </RouterLink>
            </div>

            <div class="auth-header-actions" v-else>
              <RouterLink
                :to="dashboardRoute"
                class="auth-btn auth-btn-register"
                :class="{ active: isDashboardActive }"
              >
                {{ t('publicHeader.dashboard') }}
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="sticky_header">
      <div class="container">
        <div class="main_header_inner">
          <div class="main_header_logo">
            <figure>
              <RouterLink to="/">
                <img src="/financer/assets/images/logo.png" alt="Companny Logo" />
              </RouterLink>
            </figure>
          </div>

          <div class="main_header_menu menu_area">
            <nav class="main-menu">
              <div class="nav-outer">
                <ul class="navigation">
                  <li :class="{ active: isActive('/') }">
                    <RouterLink to="/">{{ t('publicHeader.home') }}</RouterLink>
                  </li>

                  <li :class="{ active: isActive('/about') }">
                    <RouterLink to="/about">{{ t('publicHeader.about') }}</RouterLink>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>{{ t('publicHeader.services') }}</a>
                    <ul>
                      <li><a href="#" @click.prevent>{{ t('publicHeader.services') }}</a></li>
                      <li><a href="#" @click.prevent>Services Two</a></li>
                      <li><a href="#" @click.prevent>Service Details</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>{{ t('publicHeader.project') }}</a>
                    <ul>
                      <li><a href="#" @click.prevent>{{ t('publicHeader.project') }}</a></li>
                      <li><a href="#" @click.prevent>Project Details</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>{{ t('publicHeader.pages') }}</a>
                    <ul>
                      <li><a href="#" @click.prevent>Faq's</a></li>
                      <li><a href="#" @click.prevent>Error Page</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>{{ t('publicHeader.news') }}</a>
                    <ul>
                      <li><a href="#" @click.prevent>Blog Grid</a></li>
                      <li><a href="#" @click.prevent>Blog Standard</a></li>
                      <li><a href="#" @click.prevent>Blog Details</a></li>
                    </ul>
                  </li>

                  <li><a href="#" @click.prevent>{{ t('publicHeader.contact') }}</a></li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content auth-header-actions-wrap">
            <div class="header-language-switcher">
              <label class="visually-hidden" for="public-language-switcher-sticky">{{ t('common.languageLabel') }}</label>
              <div class="language-select-shell">
                <span class="language-select-icon"><i class="fa-solid fa-earth-asia"></i></span>
                <select
                  id="public-language-switcher-sticky"
                  class="language-select"
                  :value="currentLocale"
                  @change="onLocaleChange"
                >
                  <option
                    v-for="option in localeOptions"
                    :key="`sticky-${option.value}`"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </div>
            </div>

            <button class="search-toggler" @click="$emit('open-search')">
              <i class="icon-50"></i>
            </button>

            <div class="auth-header-actions" v-if="!isAuthenticated">
              <RouterLink
                to="/login"
                class="auth-btn auth-btn-login"
                :class="{ active: isActive('/login') }"
              >
                {{ t('publicHeader.login') }}
              </RouterLink>

              <RouterLink
                to="/register"
                class="auth-btn auth-btn-register"
                :class="{ active: isActive('/register') }"
              >
                {{ t('publicHeader.register') }}
              </RouterLink>
            </div>

            <div class="auth-header-actions" v-else>
              <RouterLink
                :to="dashboardRoute"
                class="auth-btn auth-btn-register"
                :class="{ active: isDashboardActive }"
              >
                {{ t('publicHeader.dashboard') }}
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mobile-menu" :class="{ 'mobile-menu-visible': mobileMenuOpen }">
      <div class="menu-backdrop" @click="$emit('close-mobile-menu')"></div>
      <div class="close-btn" @click="$emit('close-mobile-menu')">X</div>

      <nav class="menu-box">
        <div class="nav-logo">
          <RouterLink to="/" @click="$emit('close-mobile-menu')">
            <img src="/financer/assets/images/mobile-logo.png" alt="" title="" />
          </RouterLink>
        </div>

        <div class="mobile-language-switcher">
          <label class="visually-hidden" for="mobile-language-switcher">{{ t('common.languageLabel') }}</label>
          <div class="language-select-shell mobile-language-select-shell">
            <span class="language-select-icon"><i class="fa-solid fa-earth-asia"></i></span>
            <select
              id="mobile-language-switcher"
              class="language-select"
              :value="currentLocale"
              @change="onLocaleChange"
            >
              <option
                v-for="option in localeOptions"
                :key="`mobile-${option.value}`"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="mobile-auth-actions" v-if="!isAuthenticated">
          <RouterLink
            to="/login"
            class="mobile-auth-btn mobile-auth-btn-login"
            :class="{ active: isActive('/login') }"
            @click="$emit('close-mobile-menu')"
          >
            {{ t('publicHeader.login') }}
          </RouterLink>

          <RouterLink
            to="/register"
            class="mobile-auth-btn mobile-auth-btn-register"
            :class="{ active: isActive('/register') }"
            @click="$emit('close-mobile-menu')"
          >
            {{ t('publicHeader.register') }}
          </RouterLink>
        </div>

        <div class="mobile-auth-actions" v-else>
          <RouterLink
            :to="dashboardRoute"
            class="mobile-auth-btn mobile-auth-btn-register"
            :class="{ active: isDashboardActive }"
            @click="$emit('close-mobile-menu')"
          >
            {{ t('publicHeader.dashboard') }}
          </RouterLink>
        </div>

        <div class="menu-outer">
          <ul class="navigation clearfix">
            <li class="dropdown" :class="{ current: isActive('/') }">
              <RouterLink to="/" @click="$emit('close-mobile-menu')">{{ t('publicHeader.home') }}</RouterLink>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.home }" @click="$emit('toggle-mobile-dropdown', 'home')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.home ? 'block' : 'none' }">
                <li><RouterLink to="/" @click="$emit('close-mobile-menu')">{{ t('publicHeader.home') }}</RouterLink></li>
              </ul>
            </li>

            <li :class="{ current: isActive('/about') }">
              <RouterLink to="/about" @click="$emit('close-mobile-menu')">{{ t('publicHeader.about') }}</RouterLink>
            </li>

            <li class="dropdown">
              <a href="#" @click.prevent>{{ t('publicHeader.services') }}</a>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.services }" @click="$emit('toggle-mobile-dropdown', 'services')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.services ? 'block' : 'none' }">
                <li><a href="#" @click.prevent>{{ t('publicHeader.services') }}</a></li>
                <li><a href="#" @click.prevent>Services Two</a></li>
                <li><a href="#" @click.prevent>Service Details</a></li>
              </ul>
            </li>

            <li class="dropdown">
              <a href="#" @click.prevent>{{ t('publicHeader.project') }}</a>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.project }" @click="$emit('toggle-mobile-dropdown', 'project')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.project ? 'block' : 'none' }">
                <li><a href="#" @click.prevent>{{ t('publicHeader.project') }}</a></li>
                <li><a href="#" @click.prevent>Project Details</a></li>
              </ul>
            </li>

            <li class="dropdown">
              <a href="#" @click.prevent>{{ t('publicHeader.pages') }}</a>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.pages }" @click="$emit('toggle-mobile-dropdown', 'pages')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.pages ? 'block' : 'none' }">
                <li><a href="#" @click.prevent>Faq's</a></li>
                <li><a href="#" @click.prevent>Error Page</a></li>
              </ul>
            </li>

            <li class="dropdown">
              <a href="#" @click.prevent>{{ t('publicHeader.news') }}</a>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.news }" @click="$emit('toggle-mobile-dropdown', 'news')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.news ? 'block' : 'none' }">
                <li><a href="#" @click.prevent>Blog Grid</a></li>
                <li><a href="#" @click.prevent>Blog Standard</a></li>
                <li><a href="#" @click.prevent>Blog Details</a></li>
              </ul>
            </li>

            <li><a href="#" @click.prevent>{{ t('publicHeader.contact') }}</a></li>
          </ul>
        </div>

        <div class="contact-info">
          <h4>{{ t('publicHeader.contactInfo') }}</h4>
          <ul>
            <li>{{ t('publicHeader.officeAddress') }}</li>
            <li><a href="tel:+8801682648101">+88 01682648101</a></li>
            <li><a href="mailto:info@example.com">info@example.com</a></li>
          </ul>
        </div>

        <ul class="social-links centred">
          <li><a href="#" @click.prevent><span class="fab fa-twitter"></span></a></li>
          <li><a href="#" @click.prevent><span class="fab fa-facebook-square"></span></a></li>
          <li><a href="#" @click.prevent><span class="fab fa-pinterest-p"></span></a></li>
          <li><a href="#" @click.prevent><span class="fab fa-instagram"></span></a></li>
          <li><a href="#" @click.prevent><span class="fab fa-youtube"></span></a></li>
        </ul>
      </nav>
    </div>
  </header>
</template>

<style scoped>
.auth-header-actions-wrap {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-language-switcher {
  display: flex;
  align-items: center;
}

.language-select-shell {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 158px;
  height: 48px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.96);
  border: 1px solid rgba(15, 23, 42, 0.12);
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.language-select-icon {
  position: absolute;
  left: 16px;
  color: #475569;
  pointer-events: none;
  font-size: 14px;
}

.language-select {
  width: 100%;
  height: 100%;
  padding: 0 38px 0 42px;
  border: none;
  background: transparent;
  color: #0f172a;
  font-size: 14px;
  font-weight: 700;
  outline: none;
  appearance: none;
  cursor: pointer;
}

.mobile-language-switcher {
  padding: 16px 0 6px;
}

.mobile-language-select-shell {
  min-width: 100%;
  border-radius: 16px;
}

.auth-header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-left: 8px;
}

.auth-btn {
  min-width: 108px;
  height: 48px;
  padding: 0 22px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.25s ease;
  white-space: nowrap;
}

.auth-btn-login {
  color: #0f172a;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.12);
}

.auth-btn-login:hover,
.auth-btn-login.active {
  color: #6d28d9;
  border-color: rgba(109, 40, 217, 0.24);
  box-shadow: 0 10px 24px rgba(109, 40, 217, 0.10);
  transform: translateY(-1px);
}

.auth-btn-register {
  color: #ffffff;
  background: linear-gradient(135deg, #7c3aed, #2563eb);
  border: 1px solid transparent;
  box-shadow: 0 14px 30px rgba(37, 99, 235, 0.22);
}

.auth-btn-register:hover,
.auth-btn-register.active {
  color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 18px 34px rgba(37, 99, 235, 0.28);
}

.mobile-auth-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  padding: 18px 0 22px;
}

.mobile-auth-btn {
  min-height: 48px;
  border-radius: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.25s ease;
}

.mobile-auth-btn-login {
  color: #0f172a;
  background: rgba(15, 23, 42, 0.06);
}

.mobile-auth-btn-login.active,
.mobile-auth-btn-login:hover {
  background: rgba(109, 40, 217, 0.12);
  color: #6d28d9;
}

.mobile-auth-btn-register {
  color: #ffffff;
  background: linear-gradient(135deg, #7c3aed, #2563eb);
}

.mobile-auth-btn-register.active,
.mobile-auth-btn-register:hover {
  color: #ffffff;
  box-shadow: 0 12px 24px rgba(37, 99, 235, 0.20);
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

:global(html[dir='rtl']) .language-select-icon,
:global(body[dir='rtl']) .language-select-icon {
  left: auto;
  right: 16px;
}

:global(html[dir='rtl']) .language-select,
:global(body[dir='rtl']) .language-select {
  padding: 0 42px 0 38px;
}

@media (max-width: 1199px) {
  .header-language-switcher {
    display: none;
  }
}

@media (max-width: 767px) {
  .auth-header-actions-wrap {
    gap: 10px;
  }

  .auth-header-actions {
    margin-left: 0;
  }
}
</style>
