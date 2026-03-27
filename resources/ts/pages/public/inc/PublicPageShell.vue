<template>
    <div class="boxed_wrapper financer-home">
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
  
      <PublicHeader
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
  import './public-theme.css'
  
  import PublicFooter from './PublicFooter.vue'
  import PublicHeader from './PublicHeader.vue'
  import PublicPreloader from './PublicPreloader.vue'
  import PublicScrollTop from './PublicScrollTop.vue'
  import PublicSearchPopup from './PublicSearchPopup.vue'
  import { usePublicUi } from './usePublicUi'
  
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