<template>
  <PublicPageShell>
    <div ref="aboutRoot" class="about-page-root">
      <section class="page-title centred">
        <div class="container">
          <div class="content-box">
            <h1>{{ t('aboutPage.heroTitle') }}</h1>
            <p>{{ t('aboutPage.heroText') }}</p>
          </div>
        </div>
      </section>

      <section
        class="about_page_banner aos-init"
        data-aos="fade-up"
        data-aos-easing="linear"
        data-aos-duration="500"
      >
        <div class="container">
          <div class="banner_content centred">
            <div class="tag_text">
              <h6>{{ t('aboutPage.bannerTag') }}</h6>
            </div>
            <h1>
              {{ t('aboutPage.bannerTitleLine1') }} <br />
              {{ t('aboutPage.bannerTitleLine2') }}
            </h1>
            <p>{{ t('aboutPage.bannerText') }}</p>
            <div class="banner_image">
              <img src="/financer/assets/images/resource/about_banner_image.jpg" alt="" />
            </div>
          </div>
        </div>
      </section>

      <section
        class="novacast-profile-section aos-init"
        data-aos="fade-up"
        data-aos-easing="linear"
        data-aos-duration="500"
      >
        <div class="container">
          <div class="section_title centred">
            <div class="tag_text">
              <h6>{{ t('aboutPage.profileTag') }}</h6>
            </div>
            <h2>{{ t('aboutPage.profileTitle') }}</h2>
            <p>{{ t('aboutPage.profileBody') }}</p>
          </div>

          <div class="novacast-identity-grid">
            <article
              v-for="(item, index) in identityItems"
              :key="'identity-' + index"
              class="novacast-identity-card aos-init"
              data-aos="fade-up"
              data-aos-easing="linear"
              :data-aos-duration="500 + index * 100"
            >
              <h4>{{ item.title }}</h4>
              <p>{{ item.text }}</p>
            </article>
          </div>

          <div
            class="novacast-values-band aos-init"
            data-aos="fade-up"
            data-aos-easing="linear"
            data-aos-duration="650"
          >
            <strong>{{ t('aboutPage.valuesTitle') }}</strong>
            <div>
              <span v-for="(value, index) in values" :key="'value-' + index">{{ value.label }}</span>
            </div>
          </div>
        </div>
      </section>

      <section
        class="why_choose_us home_four about_page aos-init"
        data-aos="fade-up"
        data-aos-easing="linear"
        data-aos-duration="500"
      >
        <div class="shape_circle float-bob-y">
          <img src="/financer/assets/images/icons/ring_shape.png" alt="" />
        </div>
        <div class="mouse_pointer float-bob-x">
          <img src="/financer/assets/images/icons/mouse-pointer.png" alt="" />
        </div>
        <div class="container">
          <div class="border_top"></div>
          <div class="section_title centred">
            <div class="tag_text">
              <h6>{{ t('aboutPage.whyChooseTag') }}</h6>
            </div>
            <h2>{{ t('aboutPage.whyChooseTitle') }}</h2>
          </div>
          <div class="row">
            <div
              v-for="(col, index) in whyChooseColumns"
              :key="'why-choose-' + index"
              class="col-xl-4 col-md-6 col-sm-12"
            >
              <div
                class="why_choose_block_two mb_40 aos-init"
                :class="{ hover: index === 1 }"
                :data-aos="index === 0 ? 'fade-right' : index === 2 ? 'fade-left' : 'fade-up'"
                data-aos-easing="linear"
                data-aos-duration="500"
              >
                <div class="choose_icon">
                  <i :class="col.icon"></i>
                </div>
                <h4>{{ col.title }}</h4>
                <p>{{ col.text }}</p>
                <div class="link_btn">
                  <a href="#" @click.prevent
                    >{{ t('homePage.discoverMore') }} <i class="fa-solid fa-angle-right"></i
                  ></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="feature_section_two home_four">
        <div class="circle_one float-bob-y"></div>
        <div class="circle_two float-bob-x"></div>
        <div class="container">
          <div class="row">
            <div class="col-xl-5 col-lg-5 col-md-12">
              <div
                class="feature_content_block aos-init"
                data-aos="fade-right"
                data-aos-easing="linear"
                data-aos-duration="500"
              >
                <div class="section_title">
                  <div class="tag_text">
                    <h6>{{ t('aboutPage.featuresTag') }}</h6>
                  </div>
                  <h2>{{ t('aboutPage.featuresTitle') }}</h2>
                </div>
                <ul class="accordion_box">
                  <li
                    v-for="(item, index) in featureAccordion"
                    :key="'feature-' + index"
                    class="accordion block aos-init"
                    :class="{ 'active-block': activeFeatureIndex === index }"
                    data-aos="fade-up"
                    data-aos-easing="linear"
                    data-aos-duration="500"
                  >
                    <div
                      class="acc-btn"
                      :class="{ active: activeFeatureIndex === index }"
                      @click="selectFeatureItem(index)"
                    >
                      <h4>{{ item.title }}</h4>
                      <div class="icon-box"></div>
                    </div>
                    <div
                      v-show="activeFeatureIndex === index"
                      class="acc-content"
                      :class="{ current: activeFeatureIndex === index }"
                    >
                      <div class="text">
                        <p>{{ item.body }}</p>
                      </div>
                    </div>
                  </li>
                </ul>
              </div>
            </div>
            <div class="col-xl-6 col-lg-6 col-sm-12 offset-xl-1">
              <div
                class="feature_image_block aos-init"
                data-aos="fade-left"
                data-aos-easing="linear"
                data-aos-duration="500"
              >
                <div class="icon_box_one float-bob-y"><i class="icon-44"></i></div>
                <div class="icon_box_two float-bob-y"><i class="icon-29"></i></div>
                <div class="feture_image">
                  <img src="/financer/assets/images/resource/feature_image_2.jpg" alt="" />
                </div>
                <div class="chart_image_five float-bob-x">
                  <img src="/financer/assets/images/resource/chart_5.png" alt="" />
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

type WhyColumn = {
  icon: string
  title: string
  text: string
}

type FeatureAccordionItem = {
  title: string
  body: string
}

type IdentityItem = {
  title: string
  text: string
}

type ValueItem = {
  label: string
}

const whyChooseColumns = computed<WhyColumn[]>(() => tm('aboutPage.whyChooseColumns') as WhyColumn[])
const featureAccordion = computed<FeatureAccordionItem[]>(
  () => tm('aboutPage.featureAccordion') as FeatureAccordionItem[],
)
const identityItems = computed<IdentityItem[]>(() => tm('aboutPage.identityItems') as IdentityItem[])
const values = computed<ValueItem[]>(() => tm('aboutPage.values') as ValueItem[])

const activeFeatureIndex = ref(2)

function selectFeatureItem(index: number) {
  activeFeatureIndex.value = index
}

const aboutRoot = ref<HTMLElement | null>(null)
let aosObserver: IntersectionObserver | null = null

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

  const root = aboutRoot.value
  if (root) {
    setupAosScroll(root)
  }
}

onMounted(() => {
  void refreshAosScroll()
})

watch(locale, () => {
  void refreshAosScroll()
})

onBeforeUnmount(() => {
  aosObserver?.disconnect()
  aosObserver = null
})
</script>

<style scoped>
.novacast-profile-section {
  padding: 105px 0;
  background: #ffffff;
}

.novacast-profile-section .section_title p {
  max-width: 880px;
  margin: 18px auto 0;
  line-height: 1.85;
  color: #6b7280;
}

.novacast-identity-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 24px;
  margin-top: 46px;
}

.novacast-identity-card {
  min-height: 210px;
  padding: 32px;
  border-radius: 8px;
  border: 1px solid rgba(17, 24, 39, 0.08);
  background: #f8fafc;
}

.novacast-identity-card h4 {
  margin-bottom: 14px;
}

.novacast-identity-card p {
  margin: 0;
  line-height: 1.8;
  color: #6b7280;
}

.novacast-values-band {
  margin-top: 26px;
  padding: 26px 30px;
  border-radius: 8px;
  background: #111827;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
}

.novacast-values-band strong {
  font-size: 20px;
}

.novacast-values-band div {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.novacast-values-band span {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  padding: 7px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  font-weight: 700;
}

@media (max-width: 991px) {
  .novacast-identity-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
