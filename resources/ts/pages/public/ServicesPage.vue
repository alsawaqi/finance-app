<template>
  <PublicPageShell>
    <div ref="servicesRoot" class="services-page-root">
      <section class="page-title centred">
        <div class="container">
          <div class="content-box">
            <h1>{{ t('servicesPage.heroTitle') }}</h1>
            <p>{{ t('servicesPage.heroText') }}</p>
          </div>
        </div>
      </section>

      <section
        class="service_style_one novacast-services-tabs pt_150 aos-init"
        data-aos="fade-up"
        data-aos-easing="linear"
        data-aos-duration="500"
      >
        <div class="ring_shape_icon float-bob-y">
          <img src="/financer/assets/images/icons/ring_shape.png" alt="" />
        </div>
        <div class="container">
          <div class="section_title centred">
            <div class="tag_text">
              <h6>{{ t('servicesPage.sectionTag') }}</h6>
            </div>
            <h2>{{ t('servicesPage.sectionTitle') }}</h2>
            <p>{{ t('aboutPage.serviceDetailText') }}</p>
          </div>

          <div class="nav nav-tabs novacast-services-tab-nav" role="tablist">
            <button
              v-for="(group, index) in serviceGroups"
              :id="'service-tab-' + index"
              :key="'service-tab-' + index"
              type="button"
              class="nav-link"
              :class="{ active: activeServiceIndex === index }"
              role="tab"
              :aria-controls="'service-panel-' + index"
              :aria-selected="activeServiceIndex === index"
              @click="selectService(index)"
            >
              <span>{{ formatServiceNumber(index) }}</span>
              {{ group.title }}
            </button>
          </div>

          <div class="tab-content">
            <div
              v-for="(group, index) in serviceGroups"
              :id="'service-panel-' + index"
              :key="'service-panel-' + index"
              class="tab-pane fade"
              :class="{ 'show active': activeServiceIndex === index }"
              role="tabpanel"
              :aria-labelledby="'service-tab-' + index"
            >
              <div class="row align-items-center">
                <div class="col-lg-6 col-md-12 col-sm-12 content_column">
                  <div class="content_box mr_70">
                    <div class="novacast-service-kicker">
                      <i :class="group.icon"></i>
                      <span>{{ formatServiceNumber(index) }}</span>
                    </div>
                    <h3>{{ group.title }}</h3>
                    <p>{{ group.intro }}</p>

                    <div class="novacast-service-options">
                      <div class="content_item_one">
                        <div class="icon_box"><i class="icon-17"></i></div>
                        <div class="icon_content">
                          <h4>{{ t('servicesPage.primaryFeatureTitle') }}</h4>
                          <ul class="novacast-service-option-list">
                            <li v-for="item in firstHalf(group.items)" :key="'first-' + group.title + '-' + item">
                              {{ item }}
                            </li>
                          </ul>
                        </div>
                      </div>

                      <div class="content_item_one">
                        <div class="icon_box"><i class="icon-18"></i></div>
                        <div class="icon_content">
                          <h4>{{ t('servicesPage.supportFeatureTitle') }}</h4>
                          <ul class="novacast-service-option-list">
                            <li v-for="item in secondHalf(group.items)" :key="'second-' + group.title + '-' + item">
                              {{ item }}
                            </li>
                          </ul>
                        </div>
                      </div>
                    </div>

                    <div class="novacast-service-goal">
                      <i class="icon-33"></i>
                      <p>{{ group.goal }}</p>
                    </div>
                  </div>
                </div>
                <div class="col-lg-6 col-md-12 col-sm-12 image_column">
                  <figure class="image_box novacast-service-image">
                    <img src="/financer/assets/images/resource/services_image_1.jpg" alt="" />
                  </figure>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </PublicPageShell>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PublicPageShell from './inc/PublicPageShell.vue'

const { t, tm, locale } = useI18n()

type ServiceGroup = {
  icon: string
  title: string
  intro: string
  items: string[]
  goal: string
}

const serviceGroups = computed<ServiceGroup[]>(() => tm('aboutPage.serviceGroups') as ServiceGroup[])
const activeServiceIndex = ref(0)
const servicesRoot = ref<HTMLElement | null>(null)
let aosObserver: IntersectionObserver | null = null

function selectService(index: number) {
  activeServiceIndex.value = index
  void refreshAosScroll()
}

function formatServiceNumber(index: number) {
  return String(index + 1).padStart(2, '0')
}

function splitPoint(items: string[]) {
  return Math.ceil(items.length / 2)
}

function firstHalf(items: string[]) {
  return items.slice(0, splitPoint(items))
}

function secondHalf(items: string[]) {
  return items.slice(splitPoint(items))
}

function shouldAnimateAosImmediately(el: Element) {
  const rect = el.getBoundingClientRect()
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight

  return rect.top < viewportHeight * 0.92
}

function setupAosScroll(contentRoot: HTMLElement) {
  const scope = contentRoot.closest('.boxed_wrapper') ?? contentRoot
  const elements = Array.from(scope.querySelectorAll('[data-aos]'))

  if (!('IntersectionObserver' in window)) {
    elements.forEach((el) => el.classList.add('aos-animate'))
    return
  }

  aosObserver?.disconnect()
  aosObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('aos-animate')
          aosObserver?.unobserve(entry.target)
        }
      })
    },
    { threshold: 0.15 },
  )

  elements.forEach((el) => {
    if (el.classList.contains('aos-animate') || shouldAnimateAosImmediately(el)) {
      el.classList.add('aos-animate')
      return
    }

    aosObserver?.observe(el)
  })
}

function nextAnimationFrame() {
  return new Promise<void>((resolve) => {
    window.requestAnimationFrame(() => resolve())
  })
}

async function refreshAosScroll() {
  await nextTick()
  await nextAnimationFrame()

  const root = servicesRoot.value
  if (root) {
    setupAosScroll(root)
  }
}

onMounted(() => {
  void refreshAosScroll()
})

watch(locale, () => {
  activeServiceIndex.value = 0
  void refreshAosScroll()
})

onBeforeUnmount(() => {
  aosObserver?.disconnect()
  aosObserver = null
})
</script>

<style scoped>
.novacast-services-tabs {
  overflow: hidden;
  padding-bottom: 125px;
}

.novacast-services-tabs .section_title {
  max-width: 850px;
  margin: 0 auto 48px;
}

.novacast-services-tabs .section_title p {
  margin: 18px auto 0;
  color: #6a6a6a;
  line-height: 1.85;
}

.novacast-services-tab-nav {
  justify-content: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 58px;
  border-bottom: 0;
}

.novacast-services-tab-nav .nav-link {
  min-height: 64px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
  -webkit-text-fill-color: currentColor;
}

.novacast-services-tab-nav .nav-link::before {
  display: none;
}

.novacast-services-tab-nav .nav-link span {
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: #f4f0ff;
  color: #6d28d9;
  font-size: 15px;
  font-weight: 800;
}

.novacast-services-tab-nav .nav-link.active,
.novacast-services-tab-nav .nav-link:hover {
  color: #ffffff;
  background: #111827;
  border-color: #111827;
  -webkit-text-fill-color: currentColor;
}

.novacast-services-tab-nav .nav-link.active span,
.novacast-services-tab-nav .nav-link:hover span {
  color: #111827;
  background: #ffffff;
}

.novacast-services-tabs .content_box {
  max-width: none;
}

.novacast-service-kicker {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding: 9px 13px;
  border-radius: 999px;
  background: #f4f0ff;
  color: #6d28d9;
  font-weight: 800;
}

.novacast-service-kicker i {
  font-size: 20px;
}

.novacast-services-tabs .content_box h3 {
  line-height: 1.18;
}

.novacast-service-options {
  display: grid;
  gap: 22px;
}

.novacast-service-options .content_item_one {
  padding: 18px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 18px 42px rgba(15, 23, 42, 0.06);
}

.novacast-service-option-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 9px 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.novacast-service-option-list li {
  position: relative;
  padding-inline-start: 16px;
  color: #4b5563;
  font-size: 15px;
  line-height: 1.55;
}

.novacast-service-option-list li::before {
  position: absolute;
  inset-inline-start: 0;
  top: 10px;
  width: 7px;
  height: 7px;
  content: '';
  border-radius: 50%;
  background: #9f70fd;
}

.novacast-service-goal {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-top: 24px;
  padding: 20px;
  border-radius: 8px;
  background: #111827;
  color: #ffffff;
}

.novacast-service-goal i {
  width: 42px;
  height: 42px;
  display: grid;
  flex-shrink: 0;
  place-items: center;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
  font-size: 20px;
}

.novacast-service-goal p {
  margin: 0;
  color: #ffffff;
  font-weight: 800;
  line-height: 1.7;
}

.novacast-service-image {
  min-height: 530px;
  box-shadow: 0 26px 70px rgba(15, 23, 42, 0.16);
}

.novacast-service-image::before {
  position: absolute;
  inset: 0;
  z-index: 1;
  content: '';
  background:
    linear-gradient(180deg, rgba(17, 24, 39, 0.06), rgba(17, 24, 39, 0.32)),
    radial-gradient(circle at 25% 20%, rgba(159, 112, 253, 0.42), transparent 38%);
  pointer-events: none;
}

.novacast-service-image img {
  width: 100%;
  min-height: 530px;
  object-fit: cover;
}

html[dir='rtl'] .novacast-services-tabs .image_box {
  margin-left: 0;
  margin-right: 25px;
}

@media (max-width: 991px) {
  .novacast-services-tabs {
    padding-bottom: 95px;
  }

  .novacast-services-tabs .content_box {
    margin-right: 0;
    margin-bottom: 42px;
  }

  .novacast-service-image,
  .novacast-service-image img {
    min-height: 380px;
  }

  html[dir='rtl'] .novacast-services-tabs .image_box {
    margin-right: 0;
  }
}

@media (max-width: 575px) {
  .novacast-services-tab-nav .nav-link {
    width: 100%;
    justify-content: flex-start;
    text-align: start;
  }

  .novacast-service-options .content_item_one {
    gap: 16px;
  }

  .novacast-service-option-list {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
