<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const navItems = [
  {
    label: 'Dashboard',
    to: { name: 'client-dashboard' },
    matches: ['client-dashboard'],
  },
  {
    label: 'New Request',
    to: { name: 'client-new-request' },
    matches: ['client-new-request'],
  },
  {
    label: 'My Requests',
    to: { name: 'client-requests' },
    matches: ['client-requests', 'client-request-details', 'client-request-sign', 'client-request-documents'],
  },
]

const userName = computed(() => auth.user?.name || 'Abdallah')
const userInitials = computed(() => {
  return userName.value
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
})

function isNavActive(names: string[]) {
  return names.includes(String(route.name ?? ''))
}

async function handleLogout() {
  await auth.logout()
  await router.replace({ name: 'login' })
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
                <img src="/financer/assets/images/logo.png" alt="Company Logo" />
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

            <RouterLink :to="{ name: 'client-new-request' }" class="btn_style_one client-header-cta">
              <span>Create Request</span>
            </RouterLink>

            <button type="button" class="client-header-logout" @click="handleLogout">
              <i class="fas fa-sign-out-alt"></i>
              <span>Logout</span>
            </button>

            <div class="client-user-chip">
              <div class="client-user-chip__avatar">{{ userInitials }}</div>
              <div class="client-user-chip__text">
                <strong>{{ userName }}</strong>
                <span>Client Portal</span>
              </div>
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
                <img src="/financer/assets/images/logo.png" alt="Company Logo" />
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

            <RouterLink :to="{ name: 'client-new-request' }" class="btn_style_one client-header-cta">
              <span>Create Request</span>
            </RouterLink>

            <button type="button" class="client-header-logout" @click="handleLogout">
              <i class="fas fa-sign-out-alt"></i>
              <span>Logout</span>
            </button>

            <div class="client-user-chip client-user-chip--compact">
              <div class="client-user-chip__avatar">{{ userInitials }}</div>
              <div class="client-user-chip__text">
                <strong>{{ userName }}</strong>
                <span>Client Portal</span>
              </div>
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
          <RouterLink :to="{ name: 'client-dashboard' }" @click="$emit('close-mobile-menu')">
            <img src="/financer/assets/images/mobile-logo.png" alt="" title="" />
          </RouterLink>
        </div>

        <div class="mobile-client-cta-group">
          <RouterLink
            :to="{ name: 'client-new-request' }"
            class="mobile-client-cta"
            @click="$emit('close-mobile-menu')"
          >
            Create Request
          </RouterLink>

          <button
            type="button"
            class="mobile-client-logout"
            @click="handleLogout"
          >
            Logout
          </button>

          <div class="mobile-client-user">
            <div class="client-user-chip__avatar">{{ userInitials }}</div>
            <div>
              <strong>{{ userName }}</strong>
              <span>Client Portal</span>
            </div>
          </div>
        </div>

        <div class="menu-outer">
          <ul class="navigation clearfix">
            <li
              v-for="item in navItems"
              :key="`${item.label}-mobile`"
              :class="{ current: isNavActive(item.matches) }"
            >
              <RouterLink :to="item.to" @click="$emit('close-mobile-menu')">{{ item.label }}</RouterLink>
            </li>
          </ul>
        </div>

        <div class="contact-info">
          <h4>Client Help Desk</h4>
          <ul>
            <li>Track requests, sign contracts, and upload documents only when requested.</li>
            <li><a href="tel:+96600000000">+966 0000 0000</a></li>
            <li><a href="mailto:support@example.com">support@example.com</a></li>
          </ul>
        </div>
      </nav>
    </div>
  </header>
</template>
