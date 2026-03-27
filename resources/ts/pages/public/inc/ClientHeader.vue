<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()

function isPath(path: string) {
  return route.path === path
}

function isHash(hash: string) {
  return route.path === '/dashboard' && route.hash === hash
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
              <RouterLink to="/dashboard">
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
                  <li :class="{ active: isPath('/dashboard') && !route.hash }">
                    <RouterLink to="/dashboard">Dashboard</RouterLink>
                  </li>
                  <li :class="{ active: isHash('#requests') }">
                    <RouterLink to="/dashboard#requests">My Requests</RouterLink>
                  </li>
                  <li :class="{ active: isHash('#new-request') }">
                    <RouterLink to="/dashboard#new-request">New Request</RouterLink>
                  </li>
                  <li :class="{ active: isHash('#documents') }">
                    <RouterLink to="/dashboard#documents">Documents</RouterLink>
                  </li>
                  <li :class="{ active: isHash('#support') }">
                    <RouterLink to="/dashboard#support">Support</RouterLink>
                  </li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content client-header-right">
            <button class="search-toggler" @click="$emit('open-search')">
              <i class="icon-50"></i>
            </button>

            <RouterLink to="/dashboard#new-request" class="btn_style_one client-header-cta">
              <span>Submit Request</span>
            </RouterLink>

            <div class="client-user-chip">
              <div class="client-user-chip__avatar">AK</div>
              <div class="client-user-chip__content">
                <strong>Abdallah</strong>
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
              <RouterLink to="/dashboard">
                <img src="/financer/assets/images/logo.png" alt="Company Logo" />
              </RouterLink>
            </figure>
          </div>

          <div class="main_header_menu menu_area">
            <nav class="main-menu">
              <div class="nav-outer">
                <ul class="navigation">
                  <li :class="{ active: isPath('/dashboard') && !route.hash }">
                    <RouterLink to="/dashboard">Dashboard</RouterLink>
                  </li>
                  <li :class="{ active: isHash('#requests') }">
                    <RouterLink to="/dashboard#requests">My Requests</RouterLink>
                  </li>
                  <li :class="{ active: isHash('#new-request') }">
                    <RouterLink to="/dashboard#new-request">New Request</RouterLink>
                  </li>
                  <li :class="{ active: isHash('#documents') }">
                    <RouterLink to="/dashboard#documents">Documents</RouterLink>
                  </li>
                  <li :class="{ active: isHash('#support') }">
                    <RouterLink to="/dashboard#support">Support</RouterLink>
                  </li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content client-header-right">
            <button class="search-toggler" @click="$emit('open-search')">
              <i class="icon-50"></i>
            </button>

            <RouterLink to="/dashboard#new-request" class="btn_style_one client-header-cta">
              <span>Submit Request</span>
            </RouterLink>

            <div class="client-user-chip client-user-chip--compact">
              <div class="client-user-chip__avatar">AK</div>
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
          <RouterLink to="/dashboard" @click="$emit('close-mobile-menu')">
            <img src="/financer/assets/images/mobile-logo.png" alt="" title="" />
          </RouterLink>
        </div>

        <div class="mobile-client-cta-group">
          <RouterLink
            to="/dashboard#new-request"
            class="mobile-client-cta"
            @click="$emit('close-mobile-menu')"
          >
            Submit Request
          </RouterLink>

          <div class="mobile-client-user">
            <div class="client-user-chip__avatar">AK</div>
            <div>
              <strong>Abdallah</strong>
              <span>Client Portal</span>
            </div>
          </div>
        </div>

        <div class="menu-outer">
          <ul class="navigation clearfix">
            <li :class="{ current: isPath('/dashboard') && !route.hash }">
              <RouterLink to="/dashboard" @click="$emit('close-mobile-menu')">Dashboard</RouterLink>
            </li>
            <li :class="{ current: isHash('#requests') }">
              <RouterLink to="/dashboard#requests" @click="$emit('close-mobile-menu')">My Requests</RouterLink>
            </li>
            <li :class="{ current: isHash('#new-request') }">
              <RouterLink to="/dashboard#new-request" @click="$emit('close-mobile-menu')">New Request</RouterLink>
            </li>
            <li :class="{ current: isHash('#documents') }">
              <RouterLink to="/dashboard#documents" @click="$emit('close-mobile-menu')">Documents</RouterLink>
            </li>
            <li :class="{ current: isHash('#support') }">
              <RouterLink to="/dashboard#support" @click="$emit('close-mobile-menu')">Support</RouterLink>
            </li>
          </ul>
        </div>

        <div class="contact-info">
          <h4>Client Help Desk</h4>
          <ul>
            <li>Track requests, upload files, and follow your finance workflow.</li>
            <li><a href="tel:+96800000000">+968 0000 0000</a></li>
            <li><a href="mailto:support@example.com">support@example.com</a></li>
          </ul>
        </div>
      </nav>
    </div>
  </header>
</template>
