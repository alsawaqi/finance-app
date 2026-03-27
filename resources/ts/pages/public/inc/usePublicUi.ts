import { onBeforeUnmount, onMounted, ref } from 'vue'

export function usePublicUi() {
  const preloaderLetters = ['N', 'o', 'f', 'a', 'c', 'a', 's', 't']
  const showPreloader = ref(true)
  const searchOpen = ref(false)
  const mobileMenuOpen = ref(false)
  const isSticky = ref(false)
  const showScrollTop = ref(false)
  const searchText = ref('')

  const mobileDropdowns = ref<Record<string, boolean>>({
    home: false,
    services: false,
    project: false,
    pages: false,
    news: false,
  })

  let preloaderTimer: number | null = null

  function closePreloader() {
    showPreloader.value = false
  }

  function openSearch() {
    searchOpen.value = true
  }

  function closeSearch() {
    searchOpen.value = false
  }

  function openMobileMenu() {
    mobileMenuOpen.value = true
    document.body.classList.add('mobile-menu-visible')
  }

  function closeMobileMenu() {
    mobileMenuOpen.value = false
    document.body.classList.remove('mobile-menu-visible')
    mobileDropdowns.value = {
      home: false,
      services: false,
      project: false,
      pages: false,
      news: false,
    }
  }

  function toggleMobileDropdown(key: string) {
    mobileDropdowns.value[key] = !mobileDropdowns.value[key]
  }

  function updateHeaderState() {
    const scrollTop = window.scrollY || document.documentElement.scrollTop
    isSticky.value = scrollTop >= 150
    showScrollTop.value = scrollTop >= 150
  }

  function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
      closeSearch()
      closeMobileMenu()
    }
  }

  onMounted(() => {
    document.body.classList.add('boxed_wrapper')
    // Ensure page scroll is never left locked when switching layouts/routes.
    document.body.classList.remove('mobile-menu-visible')
    updateHeaderState()

    window.addEventListener('scroll', updateHeaderState)
    window.addEventListener('keydown', onKeydown)

    preloaderTimer = window.setTimeout(() => {
      showPreloader.value = false
    }, 1200)
  })

  onBeforeUnmount(() => {
    document.body.classList.remove('boxed_wrapper')
    document.body.classList.remove('mobile-menu-visible')

    window.removeEventListener('scroll', updateHeaderState)
    window.removeEventListener('keydown', onKeydown)

    if (preloaderTimer) {
      window.clearTimeout(preloaderTimer)
    }
  })

  return {
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
  }
}