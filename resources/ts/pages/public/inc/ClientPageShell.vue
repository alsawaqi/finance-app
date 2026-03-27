<template>
  <div class="boxed_wrapper financer-home financer-client">
    <PublicPreloader
      :show="showPreloader"
      :letters="preloaderLetters"
      @close="closePreloader"
    />

    <PublicSearchPopup
      :open="searchOpen"
      :model-value="searchText"
      @close="closeSearch"
      @update:model-value="searchText = $event"
    />

    <ClientHeader
      :is-sticky="isSticky"
      :mobile-menu-open="mobileMenuOpen"
      :mobile-dropdowns="mobileDropdowns"
      @open-search="openSearch"
      @open-mobile-menu="openMobileMenu"
      @close-mobile-menu="closeMobileMenu"
      @toggle-mobile-dropdown="toggleMobileDropdown"
    />

    <slot />

    <PublicFooter />

    <PublicScrollTop
      :show="showScrollTop"
      @click="scrollToTop"
    />
  </div>
</template>

<script setup lang="ts">
import '@/pages/public/inc/public-theme.css'
import './client-theme.css'

import PublicFooter from '@/pages/public/inc/PublicFooter.vue'
import PublicPreloader from '@/pages/public/inc/PublicPreloader.vue'
import PublicScrollTop from '@/pages/public/inc/PublicScrollTop.vue'
import PublicSearchPopup from '@/pages/public/inc/PublicSearchPopup.vue'
import { usePublicUi } from '@/pages/public/inc/usePublicUi'
import ClientHeader from './ClientHeader.vue'

const {
  preloaderLetters,
  showPreloader,
  searchOpen,
  mobileMenuOpen,
  isSticky,
  showScrollTop,
  searchText,
  mobileDropdowns,
  closePreloader,
  openSearch,
  closeSearch,
  openMobileMenu,
  closeMobileMenu,
  toggleMobileDropdown,
  scrollToTop,
} = usePublicUi()
</script>
