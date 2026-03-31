<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PublicPageShell from './inc/PublicPageShell.vue'

const { t, tm } = useI18n()

type FunFact = {
  title: string
  target: number
  prefix?: string
  suffix: string
  decimals?: number
}

type SimpleCard = {
  icon: string
  title: string
  text: string
  extraClass?: string
}

type TeamMember = {
  name: string
  designation: string
  image: string
}

type Testimonial = {
  text: string
  image: string
  name: string
  designation: string
}

type FAQ = {
  question: string
  answer: string
}


const showPreloader = ref(true)
const searchOpen = ref(false)
const mobileMenuOpen = ref(false)
const isSticky = ref(false)
const showScrollTop = ref(false)

const ctaEmail = ref('')

const activeFaq = ref(1)
const testimonialIndex = ref(0)
const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1440)
const animationFrameIds: number[] = []
let testimonialTimer: number | null = null
let countObserver: IntersectionObserver | null = null
let aosObserver: IntersectionObserver | null = null
let preloaderTimer: number | null = null

const mobileDropdowns = ref<Record<string, boolean>>({
  home: false,
  services: false,
  project: false,
  pages: false,
  news: false,
})

const funfacts = computed<FunFact[]>(() => tm('homePage.funfacts') as FunFact[])
const chooseUs = computed<SimpleCard[]>(() => tm('homePage.chooseUs') as SimpleCard[])
const services = computed<SimpleCard[]>(() => tm('homePage.services') as SimpleCard[])
const processes = computed<SimpleCard[]>(() => tm('homePage.processes') as SimpleCard[])
const team = computed<TeamMember[]>(() => tm('homePage.team') as TeamMember[])
const testimonialBase = computed<Testimonial[]>(() => tm('homePage.testimonials') as Testimonial[])

const testimonialItems = computed<Testimonial[]>(() => {
  const base = testimonialBase.value
  return Array.from({ length: 9 }, (_, index) => base[index % base.length])
})

const faqs = computed<FAQ[]>(() => tm('homePage.faqs') as FAQ[])

const animatedCounts = ref<number[]>([0, 0, 0, 0])

const visibleCards = computed(() => {
  if (viewportWidth.value < 768) return 1
  if (viewportWidth.value < 1200) return 2
  return 3
})

const testimonialDots = computed(() => {
  return Math.ceil(testimonialItems.value.length / visibleCards.value)
})

const activeDot = computed(() => {
  return Math.floor(testimonialIndex.value / visibleCards.value)
})

const visibleTestimonials = computed(() => {
  const cards = visibleCards.value
  return Array.from({ length: cards }, (_, index) => {
    return testimonialItems.value[(testimonialIndex.value + index) % testimonialItems.value.length]
  })
})

function formatCount(value: number, decimals = 0) {
  return decimals > 0 ? value.toFixed(decimals) : Math.floor(value).toString()
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

function setActiveFaq(index: number) {
  activeFaq.value = activeFaq.value === index ? index : index
}

function updateHeaderState() {
  const scrollTop = window.scrollY || document.documentElement.scrollTop
  isSticky.value = scrollTop >= 150
  showScrollTop.value = scrollTop >= 150
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeSearch()
    closeMobileMenu()
  }
}

function onResize() {
  viewportWidth.value = window.innerWidth
}

 

function animateCounter(index: number, target: number, decimals = 0, speed = 1500) {
  const start = performance.now()

  const frame = (now: number) => {
    const progress = Math.min((now - start) / speed, 1)
    const current = target * progress
    animatedCounts.value[index] = decimals > 0 ? Number(current.toFixed(decimals)) : Math.floor(current)

    if (progress < 1) {
      const id = requestAnimationFrame(frame)
      animationFrameIds.push(id)
    } else {
      animatedCounts.value[index] = target
    }
  }

  const id = requestAnimationFrame(frame)
  animationFrameIds.push(id)
}

function setupCounters() {
  const list = funfacts.value
  if (!('IntersectionObserver' in window)) {
    list.forEach((item, index) => {
      animatedCounts.value[index] = item.target
    })
    return
  }

  countObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return

        const target = entry.target as HTMLElement
        const index = Number(target.dataset.countIndex ?? '-1')
        const item = funfacts.value[index]

        if (index >= 0 && item && animatedCounts.value[index] === 0) {
          animateCounter(index, item.target, item.decimals ?? 0)
          countObserver?.unobserve(entry.target)
        }
      })
    },
    { threshold: 0.3 }
  )

  document.querySelectorAll('.count-box').forEach((box) => countObserver?.observe(box))
}

function setupAOSLikeAnimation() {
  if (!('IntersectionObserver' in window)) {
    document.querySelectorAll('[data-aos]').forEach((el) => el.classList.add('aos-animate'))
    return
  }

  aosObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('aos-animate')
          aosObserver?.unobserve(entry.target)
        }
      })
    },
    { threshold: 0.15 }
  )

  document.querySelectorAll('[data-aos]').forEach((el) => aosObserver?.observe(el))
}

function startTestimonials() {
  stopTestimonials()
  testimonialTimer = window.setInterval(() => {
    testimonialIndex.value = (testimonialIndex.value + visibleCards.value) % testimonialItems.value.length
  }, 3000)
}

function stopTestimonials() {
  if (testimonialTimer) {
    window.clearInterval(testimonialTimer)
    testimonialTimer = null
  }
}

function goToDot(dot: number) {
  testimonialIndex.value = dot * visibleCards.value
  startTestimonials()
}

onMounted(() => {
  document.body.classList.add('boxed_wrapper')
  updateHeaderState()
  window.addEventListener('scroll', updateHeaderState)
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('resize', onResize)

  preloaderTimer = window.setTimeout(() => {
    showPreloader.value = false
  }, 1200)

  setupCounters()
  setupAOSLikeAnimation()
  startTestimonials()
})

onBeforeUnmount(() => {
  document.body.classList.remove('boxed_wrapper')
  document.body.classList.remove('mobile-menu-visible')
  window.removeEventListener('scroll', updateHeaderState)
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('resize', onResize)

  if (preloaderTimer) {
    window.clearTimeout(preloaderTimer)
  }

  stopTestimonials()
  countObserver?.disconnect()
  aosObserver?.disconnect()
  animationFrameIds.forEach((id) => cancelAnimationFrame(id))
})
</script>

<template>
   <PublicPageShell>


  

    <!-- Banner Style One -->
    <section class="banner_style_one">
      <div class="shape_one float-bob-x" style="background-image: url('/financer/assets/images/icons/mouse-pointer.png');"></div>
      <div class="shape_two float-bob-y" style="background-image: url('/financer/assets/images/icons/shape_icon_1.png');"></div>

      <div class="container">
        <div class="banner_content">
          <div class="tag_text"><h6>{{ t('homePage.bannerTag') }}</h6></div>
          <h1>{{ t('homePage.bannerTitleLine1') }} <br /> {{ t('homePage.bannerTitleLine2') }}</h1>
          <p>
            {{ t('homePage.bannerSubtitleLine1') }} <br />
            {{ t('homePage.bannerSubtitleLine2') }}
          </p>

          <div class="subscribe-inner">
           
          </div>

          <div class="income_chart float-bob-y">
            <div class="title_box">
              <h6>{{ t('homePage.totalIncome') }}</h6>
              <div class="rate">{{ t('homePage.incomeAmount') }}</div>
            </div>
            <div class="percentage"><i class="fa-solid fa-arrow-trend-up"></i> {{ t('homePage.incomeTrend') }}</div>
          </div>

          <div class="banner_image">
            <img src="/financer/assets/images/resource/banner_image.png" alt="" />
          </div>

          <div class="shape_three"></div>
          <div class="shape_four float-bob-x">
            <img src="/financer/assets/images/resource/chart_1.png" alt="" />
          </div>
          <div class="shape_five rotate-me">
            <img src="/financer/assets/images/icons/star_icon.png" alt="" />
          </div>
        </div>
      </div>
    </section>

    <!-- Funfact Section -->
    <section class="funfact-section">
      <div class="bg_layer" style="background-image: url('/financer/assets/images/background/funfact_shape_bg.png');"></div>

      <div class="container">
        <div class="row">
          <div v-for="(item, index) in funfacts" :key="item.title" class="col-xl-3 col-lg-3 col-md-6 col-sm-12">
            <div class="funfact-block-one aos-init" data-aos="fade-up" data-aos-easing="linear" :data-aos-duration="500 + index * 50">
              <div class="count-outer count-box" :data-count-index="index">
                <template v-if="item.prefix">{{ item.prefix }}</template>
                <span class="count-text">
                  {{ formatCount(animatedCounts[index], item.decimals) }}
                </span>
                <span>{{ item.suffix }}</span>
              </div>
              <h6>{{ item.title }}</h6>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Feature Section -->
    <section class="feature_section">
      <div class="container">
        <div class="row">
          <div class="col-xl-5 col-lg-6 col-md-12 col-sm-12">
            <div class="feature_block_one aos-init" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="500">
              <div class="tag_icon">
                <i class="icon-49"></i>
              </div>
              <h3>{{ t('homePage.feature1Title') }}</h3>
              <p>{{ t('homePage.feature1Text') }}</p>
              <div class="chart_box">
                <img src="/financer/assets/images/resource/chart_2.png" alt="" />
              </div>
            </div>
          </div>

          <div class="col-xl-7 col-lg-6 col-md-12 col-sm-12">
            <div class="feature_block_one aos-init" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
              <div class="tag_icon">
                <i class="icon-48"></i>
              </div>
              <h3>{{ t('homePage.feature2Title') }}</h3>
              <p>{{ t('homePage.feature2Text') }}</p>
              <div class="chart_box">
                <img src="/financer/assets/images/resource/chart_3.png" alt="" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Why Choose Us -->
    <section class="why_choose_us">
      <div class="container">
        <div class="row why_choose_us_row">
          <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
            <div class="why_choose_left aos-init" data-aos="fade-right" data-aos-easing="linear" data-aos-duration="500">
              <div class="tag_text"><h6>{{ t('homePage.whyChooseTag') }}</h6></div>
              <h2>{{ t('homePage.whyChooseTitle') }}</h2>
              <p>
                {{ t('homePage.whyChooseBody') }}
              </p>
              <div class="link_btn">
                <a href="#" class="btn_style_one" @click.prevent>{{ t('publicHeader.contact') }}</a>
              </div>
            </div>
          </div>

          <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
            <div class="why_choose_right aos-init" data-aos="fade-left" data-aos-easing="linear" data-aos-duration="600">
              <div class="row">
                <div v-for="(item, index) in chooseUs" :key="item.title" class="col-xl-6 col-lg-6 col-md-6 col-sm-12 colmun">
                  <div
                    class="why_choose_block_one mb_40 aos-init"
                    :class="item.extraClass"
                    data-aos="fade-up"
                    data-aos-easing="linear"
                    :data-aos-duration="600 + index * 50"
                  >
                    <div class="choose_icon">
                      <i :class="item.icon"></i>
                    </div>
                    <h4>{{ item.title }}</h4>
                    <p>{{ item.text }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Service Section -->
    <section class="service_section">
      <div class="circle_one"></div>
      <div class="circle_two"></div>

      <div class="container">
        <div class="section_title light centred">
          <div class="tag_text"><h6>{{ t('homePage.servicesTag') }}</h6></div>
          <h2>{{ t('homePage.servicesTitle') }}</h2>
        </div>

        <div class="row">
          <div v-for="(item, index) in services" :key="item.title" class="col-xl-3 col-lg-3 col-md-6 col-sm-12">
            <div class="service_block_one aos-init" data-aos="fade-up" data-aos-easing="linear" :data-aos-duration="300 + index * 200">
              <div class="service_icon">
                <i :class="item.icon"></i>
              </div>
              <h4><a href="#" @click.prevent>{{ item.title }}</a></h4>
              <p>{{ item.text }}</p>
              <div class="link_btn"><a href="#" @click.prevent>{{ t('homePage.discoverMore') }}</a></div>
            </div>
          </div>
        </div>

        <h1 class="section_tag">{{ t('homePage.servicesWatermark') }}</h1>
      </div>
    </section>

    <!-- Work Process -->
    <section class="work_process_section">
      <div class="container">
        <div class="section_title centred">
          <div class="tag_text"><h6>{{ t('homePage.workProcessTag') }}</h6></div>
          <h2>{{ t('homePage.workProcessTitle') }}</h2>
        </div>

        <div class="row">
          <div v-for="(item, index) in processes" :key="item.title" class="col-xl-4 col-lg-4 col-md-6 col-sm-12">
            <div
              class="process_block_one centred aos-init"
              :class="item.extraClass"
              data-aos="fade-up"
              data-aos-easing="linear"
              :data-aos-duration="300 + index * 100"
            >
              <div class="process_icon">
                <i :class="item.icon"></i>
              </div>
              <h4>{{ item.title }}</h4>
              <p>{{ item.text }}</p>
            </div>
          </div>
        </div>

        <div
          class="video_box aos-init"
          data-aos="fade-up"
          data-aos-easing="linear"
          data-aos-duration="600"
          style="background-image: url('/financer/assets/images/background/video_box_bg.jpg');"
        >
          <a href="https://www.youtube.com/watch?v=nfP5N9Yc72A&t=28s" target="_blank" rel="noopener noreferrer" class="lightbox-image">
            <div class="icon_box"><i class="icon-38"></i></div>
          </a>
        </div>
      </div>
    </section>

    <!-- Team -->
    <section class="team_section">
      <div class="shape_one float-bob-x" style="background-image: url('/financer/assets/images/icons/mouse-pointer.png');"></div>

      <div class="container">
        <div class="section_title centred">
          <div class="tag_text"><h6>{{ t('homePage.teamTag') }}</h6></div>
          <h2>{{ t('homePage.teamTitle') }}</h2>
        </div>

        <div class="row">
          <div v-for="(member, index) in team" :key="member.name" class="col-lg-3 col-md-6 col-sm-12 team_block">
            <div class="team_block_one aos-init" data-aos="fade-up" data-aos-easing="linear" :data-aos-duration="300 + index * 200">
              <div class="inner_box">
                <div class="image_box">
                  <figure class="image">
                    <img :src="member.image" alt="" />
                  </figure>
                  <ul class="team_social_links">
                    <li><a href="#" @click.prevent><i class="fab fa-facebook-f"></i></a></li>
                    <li><a href="#" @click.prevent><i class="fab fa-twitter"></i></a></li>
                    <li><a href="#" @click.prevent><i class="fab fa-skype"></i></a></li>
                  </ul>
                </div>
                <div class="lower_content">
                  <h4><a href="#" @click.prevent>{{ member.name }}</a></h4>
                  <span class="designation">{{ member.designation }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta_section aos-init" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="500">
      <div class="container">
        <div class="cta_inner">
          <h3>{{ t('homePage.ctaTitleLine1') }} <br /> {{ t('homePage.ctaTitleLine2') }}</h3>

          <div class="subscribe-inner">
            <form class="subscribe-form" @submit.prevent>
              <div class="form-group">
                <input v-model="ctaEmail" type="email" name="email" :placeholder="t('homePage.emailPlaceholder')" />
                <button type="submit" class="btn_style_one">{{ t('homePage.getStarted') }}</button>
              </div>
            </form>
          </div>

          <div class="cta_shape float-bob-y">
            <img src="/financer/assets/images/icons/shape_icon_3.png" alt="" />
          </div>

          <div class="cta_image">
            <figure>
              <img src="/financer/assets/images/resource/cta_image.png" alt="" />
            </figure>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonial_section aos-init" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="500">
      <div class="container">
        <div class="shape_bg"></div>

        <div class="section_title centred">
          <div class="tag_text"><h6>{{ t('homePage.testimonialsTag') }}</h6></div>
          <h2>{{ t('homePage.testimonialsTitle') }}</h2>
        </div>

        <div class="three-item-carousel owl-carousel owl-theme owl-dots-one owl-nav-none owl-loaded">
          <div class="home-testimonial-track">
            <div v-for="(item, index) in visibleTestimonials" :key="item.image + '-' + index + '-' + testimonialIndex" class="testimonial_block_one">
              <div class="inner_box">
                <ul class="rating">
                  <li v-for="star in 5" :key="star"><i class="icon-39"></i></li>
                </ul>
                <p>{{ item.text }}</p>
                <div class="author_box">
                  <figure class="thumb_box">
                    <img :src="item.image" alt="" />
                  </figure>
                  <div class="author_info">
                    <h5>{{ item.name }}</h5>
                    <span class="designation">{{ item.designation }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="owl-dots custom-owl-dots">
            <button
              v-for="dot in testimonialDots"
              :key="'dot-' + dot"
              class="owl-dot"
              :class="{ active: dot === activeDot }"
              @click="goToDot(dot)"
            >
              <span></span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="faq_section aos-init" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="500">
      <div class="container">
        <div class="section_title centred">
          <div class="tag_text"><h6>{{ t('homePage.faqTag') }}</h6></div>
          <h2>{{ t('homePage.faqTitle') }}</h2>
        </div>

        <div class="inner_box">
          <ul class="accordion_box">
            <li
              v-for="(item, index) in faqs"
              :key="item.question"
              class="accordion block"
              :class="{ 'active-block': activeFaq === index }"
            >
              <div class="acc-btn" :class="{ active: activeFaq === index }" @click="setActiveFaq(index)">
                <h4>{{ item.question }}</h4>
                <div class="icon-box"></div>
              </div>
              <div v-show="activeFaq === index" class="acc-content" :class="{ current: activeFaq === index }">
                <div class="text">
                  <p>{{ item.answer }}</p>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </section>

  </PublicPageShell>
</template>


<style>
.financer-home [data-aos].aos-init {
  opacity: 1;
  transform: none;
}

.financer-home .home-testimonial-track {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 30px;
}

.financer-home .custom-owl-dots {
  margin-top: 35px;
  text-align: center;
}

.financer-home .custom-owl-dots .owl-dot {
  background: transparent;
  border: 0;
  padding: 0;
  margin: 0 5px;
}

.financer-home .custom-owl-dots .owl-dot span {
  display: block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #d1d5db;
  transition: all 0.3s ease;
}

.financer-home .custom-owl-dots .owl-dot.active span {
  width: 28px;
  border-radius: 9999px;
  background: #7c3aed;
}

.financer-home .acc-content {
  display: block;
}

@media (max-width: 1199px) {
  .financer-home .home-testimonial-track {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .financer-home .banner_style_one {
    padding-top: 120px;
  }
}

@media (max-width: 767px) {
  .financer-home .home-testimonial-track {
    grid-template-columns: minmax(0, 1fr);
  }

  .financer-home .banner_style_one {
    padding-top: 110px;
  }
}
</style>
