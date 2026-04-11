<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../../stores/auth'
import AppNotificationBell from '@/components/AppNotificationBell.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { t, locale } = useI18n()

const navItems = computed(() => [
  {
    label: t('clientHeader.dashboard'),
    to: { name: 'client-dashboard' },
    matches: ['client-dashboard'],
  },
  {
    label: t('clientHeader.newRequest'),
    to: { name: 'client-new-request' },
    matches: ['client-new-request'],
  },
  {
    label: t('clientHeader.myRequests'),
    to: { name: 'client-requests' },
    matches: ['client-requests', 'client-request-details', 'client-request-sign', 'client-request-documents'],
  },
])

const userName = computed(() => auth.user?.name || t('clientHeader.defaultUserName'))
const userInitials = computed(() => {
  return userName.value
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
})

const userDropdownOpen = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

function toggleUserDropdown() {
  userDropdownOpen.value = !userDropdownOpen.value
}

function closeUserDropdown() {
  userDropdownOpen.value = false
}

function handleOutsideClick(e: MouseEvent) {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
    closeUserDropdown()
  }
}

onMounted(() => document.addEventListener('click', handleOutsideClick, true))
onBeforeUnmount(() => document.removeEventListener('click', handleOutsideClick, true))

function isNavActive(names: string[]) {
  return names.includes(String(route.name ?? ''))
}

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

async function handleLogout() {
  closeUserDropdown()
  await auth.logout()
  await router.replace({ name: 'login' })
}

function goToChangePassword() {
  closeUserDropdown()
  router.push({ name: 'client-change-password' })
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
  <header class="header client-header" :class="{ fixed_header: isSticky }">
    <div class="main_header">
      <div class="container">
        <div class="main_header_inner">
          <div class="main_header_logo">
            <figure>
              <RouterLink to="/">
                <img src="/financer/assets/images/logo.png" :alt="t('clientHeader.logoAlt')" />
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
                  <li
                    v-for="item in navItems"
                    :key="item.label"
                    :class="{ active: isNavActive(item.matches) }"
                  >
                    <RouterLink :to="item.to">{{ item.label }}</RouterLink>
                  </li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content client-header-right">
            <button class="search-toggler" @click="$emit('open-search')">
              <i class="icon-50"></i>
            </button>

            <AppNotificationBell theme="client" />

            <RouterLink :to="{ name: 'client-new-request' }" class="btn_style_one client-header-cta">
              <span>{{ t('clientHeader.createRequest') }}</span>
            </RouterLink>

            <div ref="dropdownRef" class="client-user-dropdown-wrap">
              <button type="button" class="client-user-chip client-user-chip--clickable" @click="toggleUserDropdown">
                <div class="client-user-chip__avatar">{{ userInitials }}</div>
                <div class="client-user-chip__text">
                  <strong>{{ userName }}</strong>
                </div>
                <i class="fas fa-chevron-down client-user-chip__caret" :class="{ 'client-user-chip__caret--open': userDropdownOpen }"></i>
              </button>

              <Transition name="client-dropdown-fade">
                <div v-if="userDropdownOpen" class="client-user-dropdown">
                  <button type="button" class="client-user-dropdown__item" @click="goToChangePassword">
                    <i class="fas fa-key"></i>
                    <span>{{ t('clientHeader.changePassword') }}</span>
                  </button>
                  <button type="button" class="client-user-dropdown__item client-user-dropdown__item--danger" @click="handleLogout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>{{ t('clientHeader.logout') }}</span>
                  </button>
                </div>
              </Transition>
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
              <RouterLink :to="{ name: 'client-dashboard' }">
                <img src="/financer/assets/images/logo.png" :alt="t('clientHeader.logoAlt')" />
              </RouterLink>
            </figure>
          </div>

          <div class="main_header_menu menu_area">
            <nav class="main-menu">
              <div class="nav-outer">
                <ul class="navigation">
                  <li
                    v-for="item in navItems"
                    :key="`${item.label}-sticky`"
                    :class="{ active: isNavActive(item.matches) }"
                  >
                    <RouterLink :to="item.to">{{ item.label }}</RouterLink>
                  </li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content client-header-right">
            <button class="search-toggler" @click="$emit('open-search')">
              <i class="icon-50"></i>
            </button>

            <AppNotificationBell theme="client" />

            <RouterLink :to="{ name: 'client-new-request' }" class="btn_style_one client-header-cta">
              <span>{{ t('clientHeader.createRequest') }}</span>
            </RouterLink>

            <div class="client-user-dropdown-wrap">
              <button type="button" class="client-user-chip client-user-chip--clickable client-user-chip--compact" @click="toggleUserDropdown">
                <div class="client-user-chip__avatar">{{ userInitials }}</div>
                <div class="client-user-chip__text">
                  <strong>{{ userName }}</strong>
                </div>
                <i class="fas fa-chevron-down client-user-chip__caret" :class="{ 'client-user-chip__caret--open': userDropdownOpen }"></i>
              </button>

              <Transition name="client-dropdown-fade">
                <div v-if="userDropdownOpen" class="client-user-dropdown">
                  <button type="button" class="client-user-dropdown__item" @click="goToChangePassword">
                    <i class="fas fa-key"></i>
                    <span>{{ t('clientHeader.changePassword') }}</span>
                  </button>
                  <button type="button" class="client-user-dropdown__item client-user-dropdown__item--danger" @click="handleLogout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>{{ t('clientHeader.logout') }}</span>
                  </button>
                </div>
              </Transition>
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
          <RouterLink :to="{ name: 'client-dashboard' }" @click="$emit('close-mobile-menu')">
            <img src="/financer/assets/images/mobile-logo.png" alt="" title="" />
          </RouterLink>
        </div>

        <div class="mobile-client-profile-card">
          <div class="mobile-client-profile-card__avatar">{{ userInitials }}</div>
          <div class="mobile-client-profile-card__info">
            <strong>{{ userName }}</strong>
            <span>{{ t('clientHeader.clientPortal') }}</span>
          </div>
          <div class="mobile-client-profile-card__bell">
            <AppNotificationBell theme="client" />
          </div>
        </div>

        <div class="mobile-client-nav-group">
          <RouterLink
            v-for="item in navItems"
            :key="`${item.label}-mobile`"
            :to="item.to"
            class="mobile-client-nav-item"
            :class="{ 'mobile-client-nav-item--active': isNavActive(item.matches) }"
            @click="$emit('close-mobile-menu')"
          >
            {{ item.label }}
          </RouterLink>
        </div>

        <div class="mobile-client-actions-group">
          <RouterLink
            :to="{ name: 'client-new-request' }"
            class="mobile-client-action-btn mobile-client-action-btn--primary"
            @click="$emit('close-mobile-menu')"
          >
            <i class="fas fa-plus"></i>
            <span>{{ t('clientHeader.createRequest') }}</span>
          </RouterLink>

          <RouterLink
            :to="{ name: 'client-change-password' }"
            class="mobile-client-action-btn"
            @click="$emit('close-mobile-menu')"
          >
            <i class="fas fa-key"></i>
            <span>{{ t('clientHeader.changePassword') }}</span>
          </RouterLink>

          <button
            type="button"
            class="mobile-client-action-btn mobile-client-action-btn--danger"
            @click="handleLogout"
          >
            <i class="fas fa-sign-out-alt"></i>
            <span>{{ t('clientHeader.logout') }}</span>
          </button>
        </div>

        <div class="mobile-client-footer">
          <h4>{{ t('clientHeader.helpDesk') }}</h4>
          <p>{{ t('clientHeader.helpDeskText') }}</p>
          <div class="mobile-client-footer__links">
            <a href="tel:+96600000000"><i class="fas fa-phone"></i> +966 0000 0000</a>
            <a href="mailto:support@example.com"><i class="fas fa-envelope"></i> support@example.com</a>
          </div>
        </div>
      </nav>
    </div>
  </header>
</template>
