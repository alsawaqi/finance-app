<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import AppLocaleSelect from './AppLocaleSelect.vue'

const route = useRoute()
const auth = useAuthStore()
const { t, locale } = useI18n()

if (!auth.initialized) {
  auth.init()
}

const isAuthenticated = computed(() => auth.isAuthenticated)
const dashboardRoute = computed(() => ({ name: auth.dashboardRouteName }))
const isDashboardActive = computed(() => route.path.startsWith('/dashboard') || route.path.startsWith('/admin') || route.path.startsWith('/staff'))

function isActive(path: string) {
  return route.path === path
}

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
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
                <img src="/financer/assets/images/logo.png" :alt="uiText('Company Logo', 'شعار الشركة')" />
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

                  <li :class="{ active: isActive('/services') }">
                    <RouterLink to="/services">{{ t('publicHeader.services') }}</RouterLink>
                  </li>

                  <li :class="{ active: isActive('/contact') }">
                    <RouterLink to="/contact">{{ t('publicHeader.contact') }}</RouterLink>
                  </li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content auth-header-actions-wrap">
            <div class="header-language-switcher">
              <AppLocaleSelect id="public-language-switcher" mode="public" />
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
                <img src="/financer/assets/images/logo.png" :alt="uiText('Company Logo', 'شعار الشركة')" />
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

                  <li :class="{ active: isActive('/services') }">
                    <RouterLink to="/services">{{ t('publicHeader.services') }}</RouterLink>
                  </li>

                  <li :class="{ active: isActive('/contact') }">
                    <RouterLink to="/contact">{{ t('publicHeader.contact') }}</RouterLink>
                  </li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content auth-header-actions-wrap">
            <div class="header-language-switcher">
              <AppLocaleSelect id="public-language-switcher-sticky" mode="public" />
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
      <button type="button" class="close-btn" :aria-label="uiText('Close menu', 'إغلاق القائمة')" @click="$emit('close-mobile-menu')">
        ×
      </button>

      <nav class="menu-box">
        <div class="nav-logo">
          <RouterLink to="/" @click="$emit('close-mobile-menu')">
            <img src="/financer/assets/images/mobile-logo.png" alt="" title="" />
          </RouterLink>
        </div>

        <div class="mobile-language-switcher">
          <AppLocaleSelect id="mobile-language-switcher" mode="mobile" />
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

            <li :class="{ current: isActive('/services') }">
              <RouterLink to="/services" @click="$emit('close-mobile-menu')">{{ t('publicHeader.services') }}</RouterLink>
            </li>

            <li :class="{ current: isActive('/contact') }">
              <RouterLink to="/contact" @click="$emit('close-mobile-menu')">{{ t('publicHeader.contact') }}</RouterLink>
            </li>
          </ul>
        </div>

        <div class="contact-info">
          <h4>{{ t('publicHeader.contactInfo') }}</h4>
          <ul>
            <li>{{ t('publicHeader.officeAddress') }}</li>
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


.mobile-language-switcher {
  padding: 16px 0 6px;
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

 .auth-header-actions-wrap {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  flex-wrap: nowrap;
  min-width: 0;
}

.header-language-switcher {
  display: flex;
  align-items: center;
  flex: 0 0 auto;
}

.mobile-language-switcher {
  padding: 16px 0 6px;
}

.auth-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: nowrap;
  margin-left: 0;
  flex: 0 0 auto;
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

@media (max-width: 1199px) {
  .header-language-switcher {
    display: none;
  }
}

@media (max-width: 991px) {
  .auth-header-actions-wrap {
    display: none;
  }
}

@media (min-width: 992px) {
  .auth-header-actions-wrap {
    white-space: nowrap;
  }
}

@media (max-width: 767px) {
  .auth-header-actions-wrap {
    gap: 10px;
  }

  .auth-header-actions {
    margin-right: 0;
  }
}

 
</style>
