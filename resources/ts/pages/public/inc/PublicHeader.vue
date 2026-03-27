<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

if (!auth.initialized) {
  auth.init()
}

const isAuthenticated = computed(() => auth.isAuthenticated)
const dashboardRoute = computed(() => ({ name: auth.dashboardRouteName }))
const isDashboardActive = computed(() => route.path.startsWith('/dashboard') || route.path.startsWith('/admin') || route.path.startsWith('/staff'))

function isActive(path: string) {
  return route.path === path
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
                    <RouterLink to="/">Home</RouterLink>
                  </li>

                  <li :class="{ active: isActive('/about') }">
                    <RouterLink to="/about">About Us</RouterLink>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>Services</a>
                    <ul>
                      <li><a href="#" @click.prevent>Services</a></li>
                      <li><a href="#" @click.prevent>Services Two</a></li>
                      <li><a href="#" @click.prevent>Service Details</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>Project</a>
                    <ul>
                      <li><a href="#" @click.prevent>Project</a></li>
                      <li><a href="#" @click.prevent>Project Details</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>Pages</a>
                    <ul>
                      <li><a href="#" @click.prevent>Faq's</a></li>
                      <li><a href="#" @click.prevent>Error Page</a></li>
                      <li class="dropdown">
                        <a href="#" @click.prevent>Shop Page</a>
                        <ul>
                          <li><a href="#" @click.prevent>Pricing</a></li>
                          <li><a href="#" @click.prevent>Shop Page</a></li>
                          <li><a href="#" @click.prevent>Shop Details</a></li>
                          <li><a href="#" @click.prevent>Cart Page</a></li>
                          <li><a href="#" @click.prevent>Check Out</a></li>
                        </ul>
                      </li>
                      <li class="dropdown">
                        <a href="#" @click.prevent>Testimonial</a>
                        <ul>
                          <li><a href="#" @click.prevent>Testimonial One</a></li>
                          <li><a href="#" @click.prevent>Testimonial Two</a></li>
                        </ul>
                      </li>
                      <li class="dropdown">
                        <a href="#" @click.prevent>Our Team</a>
                        <ul>
                          <li><a href="#" @click.prevent>Our Team</a></li>
                          <li><a href="#" @click.prevent>Team Details</a></li>
                        </ul>
                      </li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>News</a>
                    <ul>
                      <li><a href="#" @click.prevent>Blog Grid</a></li>
                      <li><a href="#" @click.prevent>Blog Standard</a></li>
                      <li><a href="#" @click.prevent>Blog Details</a></li>
                      <li><a href="#" @click.prevent>Blog Details Two</a></li>
                    </ul>
                  </li>

                  <li><a href="#" @click.prevent>Contact Us</a></li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content auth-header-actions-wrap">
            <button class="search-toggler" @click="$emit('open-search')">
              <i class="icon-50"></i>
            </button>

            <div class="auth-header-actions" v-if="!isAuthenticated">
              <RouterLink
                to="/login"
                class="auth-btn auth-btn-login"
                :class="{ active: isActive('/login') }"
              >
                Login
              </RouterLink>

              <RouterLink
                to="/register"
                class="auth-btn auth-btn-register"
                :class="{ active: isActive('/register') }"
              >
                Register
              </RouterLink>
            </div>

            <div class="auth-header-actions" v-else>
              <RouterLink
                :to="dashboardRoute"
                class="auth-btn auth-btn-register"
                :class="{ active: isDashboardActive }"
              >
                Dashboard
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
                    <RouterLink to="/">Home</RouterLink>
                    <ul>
                      <li><RouterLink to="/">Home One</RouterLink></li>
                      <li><a href="#" @click.prevent>Home Two</a></li>
                      <li><a href="#" @click.prevent>Home Three</a></li>
                      <li><a href="#" @click.prevent>Home Four</a></li>
                      <li><a href="#" @click.prevent>Home Five</a></li>
                    </ul>
                  </li>

                  <li :class="{ active: isActive('/about') }">
                    <RouterLink to="/about">About Us</RouterLink>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>Services</a>
                    <ul>
                      <li><a href="#" @click.prevent>Services</a></li>
                      <li><a href="#" @click.prevent>Services Two</a></li>
                      <li><a href="#" @click.prevent>Service Details</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>Project</a>
                    <ul>
                      <li><a href="#" @click.prevent>Project</a></li>
                      <li><a href="#" @click.prevent>Project Details</a></li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>Pages</a>
                    <ul>
                      <li><a href="#" @click.prevent>Faq's</a></li>
                      <li><a href="#" @click.prevent>Error Page</a></li>
                      <li class="dropdown">
                        <a href="#" @click.prevent>Shop Page</a>
                        <ul>
                          <li><a href="#" @click.prevent>Pricing</a></li>
                          <li><a href="#" @click.prevent>Shop Page</a></li>
                          <li><a href="#" @click.prevent>Shop Details</a></li>
                          <li><a href="#" @click.prevent>Cart Page</a></li>
                          <li><a href="#" @click.prevent>Check Out</a></li>
                        </ul>
                      </li>
                      <li class="dropdown">
                        <a href="#" @click.prevent>Testimonial</a>
                        <ul>
                          <li><a href="#" @click.prevent>Testimonial One</a></li>
                          <li><a href="#" @click.prevent>Testimonial Two</a></li>
                        </ul>
                      </li>
                      <li class="dropdown">
                        <a href="#" @click.prevent>Our Team</a>
                        <ul>
                          <li><a href="#" @click.prevent>Our Team</a></li>
                          <li><a href="#" @click.prevent>Team Details</a></li>
                        </ul>
                      </li>
                    </ul>
                  </li>

                  <li class="dropdown">
                    <a href="#" @click.prevent>News</a>
                    <ul>
                      <li><a href="#" @click.prevent>Blog Grid</a></li>
                      <li><a href="#" @click.prevent>Blog Standard</a></li>
                      <li><a href="#" @click.prevent>Blog Details</a></li>
                      <li><a href="#" @click.prevent>Blog Details Two</a></li>
                    </ul>
                  </li>

                  <li><a href="#" @click.prevent>Contact Us</a></li>
                </ul>
              </div>
            </nav>
          </div>

          <div class="header_right_content auth-header-actions-wrap">
            <button class="search-toggler" @click="$emit('open-search')">
              <i class="icon-50"></i>
            </button>

            <div class="auth-header-actions" v-if="!isAuthenticated">
              <RouterLink
                to="/login"
                class="auth-btn auth-btn-login"
                :class="{ active: isActive('/login') }"
              >
                Login
              </RouterLink>

              <RouterLink
                to="/register"
                class="auth-btn auth-btn-register"
                :class="{ active: isActive('/register') }"
              >
                Register
              </RouterLink>
            </div>

            <div class="auth-header-actions" v-else>
              <RouterLink
                :to="dashboardRoute"
                class="auth-btn auth-btn-register"
                :class="{ active: isDashboardActive }"
              >
                Dashboard
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

        <div class="mobile-auth-actions" v-if="!isAuthenticated">
          <RouterLink
            to="/login"
            class="mobile-auth-btn mobile-auth-btn-login"
            :class="{ active: isActive('/login') }"
            @click="$emit('close-mobile-menu')"
          >
            Login
          </RouterLink>

          <RouterLink
            to="/register"
            class="mobile-auth-btn mobile-auth-btn-register"
            :class="{ active: isActive('/register') }"
            @click="$emit('close-mobile-menu')"
          >
            Register
          </RouterLink>
        </div>

        <div class="mobile-auth-actions" v-else>
          <RouterLink
            :to="dashboardRoute"
            class="mobile-auth-btn mobile-auth-btn-register"
            :class="{ active: isDashboardActive }"
            @click="$emit('close-mobile-menu')"
          >
            Dashboard
          </RouterLink>
        </div>

        <div class="menu-outer">
          <ul class="navigation clearfix">
            <li class="dropdown" :class="{ current: isActive('/') }">
              <RouterLink to="/" @click="$emit('close-mobile-menu')">Home</RouterLink>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.home }" @click="$emit('toggle-mobile-dropdown', 'home')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.home ? 'block' : 'none' }">
                <li><RouterLink to="/" @click="$emit('close-mobile-menu')">Home One</RouterLink></li>
                <li><a href="#" @click.prevent>Home Two</a></li>
                <li><a href="#" @click.prevent>Home Three</a></li>
                <li><a href="#" @click.prevent>Home Four</a></li>
                <li><a href="#" @click.prevent>Home Five</a></li>
              </ul>
            </li>

            <li :class="{ current: isActive('/about') }">
              <RouterLink to="/about" @click="$emit('close-mobile-menu')">About Us</RouterLink>
            </li>

            <li class="dropdown">
              <a href="#" @click.prevent>Services</a>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.services }" @click="$emit('toggle-mobile-dropdown', 'services')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.services ? 'block' : 'none' }">
                <li><a href="#" @click.prevent>Services</a></li>
                <li><a href="#" @click.prevent>Services Two</a></li>
                <li><a href="#" @click.prevent>Service Details</a></li>
              </ul>
            </li>

            <li class="dropdown">
              <a href="#" @click.prevent>Project</a>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.project }" @click="$emit('toggle-mobile-dropdown', 'project')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.project ? 'block' : 'none' }">
                <li><a href="#" @click.prevent>Project</a></li>
                <li><a href="#" @click.prevent>Project Details</a></li>
              </ul>
            </li>

            <li class="dropdown">
              <a href="#" @click.prevent>Pages</a>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.pages }" @click="$emit('toggle-mobile-dropdown', 'pages')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.pages ? 'block' : 'none' }">
                <li><a href="#" @click.prevent>Faq's</a></li>
                <li><a href="#" @click.prevent>Error Page</a></li>
                <li><a href="#" @click.prevent>Pricing</a></li>
                <li><a href="#" @click.prevent>Shop Page</a></li>
                <li><a href="#" @click.prevent>Shop Details</a></li>
                <li><a href="#" @click.prevent>Cart Page</a></li>
                <li><a href="#" @click.prevent>Check Out</a></li>
                <li><a href="#" @click.prevent>Testimonial One</a></li>
                <li><a href="#" @click.prevent>Testimonial Two</a></li>
                <li><a href="#" @click.prevent>Our Team</a></li>
                <li><a href="#" @click.prevent>Team Details</a></li>
              </ul>
            </li>

            <li class="dropdown">
              <a href="#" @click.prevent>News</a>
              <div class="dropdown-btn" :class="{ open: mobileDropdowns.news }" @click="$emit('toggle-mobile-dropdown', 'news')">
                <i class="fa fa-angle-right"></i>
              </div>
              <ul :style="{ display: mobileDropdowns.news ? 'block' : 'none' }">
                <li><a href="#" @click.prevent>Blog Grid</a></li>
                <li><a href="#" @click.prevent>Blog Standard</a></li>
                <li><a href="#" @click.prevent>Blog Details</a></li>
                <li><a href="#" @click.prevent>Blog Details Two</a></li>
              </ul>
            </li>

            <li><a href="#" @click.prevent>Contact Us</a></li>
          </ul>
        </div>

        <div class="contact-info">
          <h4>Contact Info</h4>
          <ul>
            <li>Chicago 12, Melborne City, USA</li>
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
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.12);
}

.mobile-auth-btn-login.active {
  color: #6d28d9;
  border-color: rgba(109, 40, 217, 0.28);
}

.mobile-auth-btn-register {
  color: #ffffff;
  background: linear-gradient(135deg, #7c3aed, #2563eb);
  border: 1px solid transparent;
}

.mobile-auth-btn-register.active {
  box-shadow: 0 10px 24px rgba(37, 99, 235, 0.20);
}

@media (max-width: 1199px) {
  .auth-btn {
    min-width: 96px;
    padding: 0 18px;
    height: 44px;
    font-size: 14px;
  }

  .auth-header-actions {
    gap: 10px;
  }
}

@media (max-width: 991px) {
  .auth-header-actions {
    display: none;
  }

  .auth-header-actions-wrap {
    gap: 10px;
  }
}
</style>
