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
              :key="col.title"
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
                    :key="item.title"
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

      <section class="team_section">
        <div class="container">
          <div class="section_title centred">
            <div class="tag_text">
              <h6>{{ t('aboutPage.teamTag') }}</h6>
            </div>
            <h2>{{ t('aboutPage.teamTitle') }}</h2>
          </div>
          <div class="row">
            <div
              v-for="(member, index) in team"
              :key="member.name"
              class="col-lg-3 col-md-6 col-sm-12 team_block"
            >
              <div
                class="team_block_one aos-init"
                data-aos="fade-up"
                data-aos-easing="linear"
                :data-aos-duration="500 + index * 100"
              >
                <div class="inner_box">
                  <div class="image_box">
                    <figure class="image">
                      <img :src="member.image" alt="" />
                    </figure>
                    <ul class="team_social_links">
                      <li>
                        <a href="#" @click.prevent><i class="fab fa-facebook-f"></i></a>
                      </li>
                      <li>
                        <a href="#" @click.prevent><i class="fab fa-twitter"></i></a>
                      </li>
                      <li>
                        <a href="#" @click.prevent><i class="fab fa-skype"></i></a>
                      </li>
                    </ul>
                  </div>
                  <div class="lower_content">
                    <h4>
                      <a href="#" @click.prevent>{{ member.name }}</a>
                    </h4>
                    <span class="designation">{{ member.designation }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section
        class="app_section style_three aos-init"
        data-aos="fade-up"
        data-aos-easing="linear"
        data-aos-duration="500"
      >
        <div class="shape_icon_12">
          <img src="/financer/assets/images/icons/shape_icon_12.png" alt="" />
        </div>
        <div class="container">
          <div class="app_inner_box">
            <div class="app_outer_box">
              <div class="shape_one float-bob-x"></div>
              <div class="shape_two float-bob-y"></div>
              <div class="shape_three rotate-me"></div>
              <div class="content_box">
                <h2>{{ t('aboutPage.appTitle') }}</h2>
                <p>{{ t('aboutPage.appText') }}</p>
              </div>
              <div class="app_links">
                <div class="apple_link">
                  <a href="#" @click.prevent
                    ><img src="/financer/assets/images/icons/apple_icon.png" alt=""
                  /></a>
                </div>
                <div class="play_link">
                  <a href="#" @click.prevent
                    ><img src="/financer/assets/images/icons/play_icon.png" alt=""
                  /></a>
                </div>
              </div>
            </div>
            <div class="app_image float-bob-x">
              <img src="/financer/assets/images/resource/app_image.png" alt="" />
            </div>
          </div>
        </div>
      </section>

      <section class="testimonial_section_two">
        <div class="container">
          <div class="row">
            <div class="col-xl-6 col-md-12 content_column">
              <div
                class="testimonial_content aos-init"
                data-aos="fade-right"
                data-aos-easing="linear"
                data-aos-duration="500"
              >
                <div class="section_title">
                  <div class="tag_text">
                    <h6>{{ t('homePage.testimonialsTag') }}</h6>
                  </div>
                  <h2>{{ t('homePage.testimonialsTitle') }}</h2>
                </div>
                <div class="single-item-carousel owl-carousel owl-theme owl-dots-none nav-style-one">
                  <div
                    v-for="(slide, idx) in testimonialSlides"
                    :key="'about-t-' + idx"
                    class="testimonial_block_three"
                  >
                    <div class="inner_box">
                      <div class="quort_icon"><i class="icon-19"></i></div>
                      <p>{{ slide.text }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xl-6 col-md-12 image_column">
              <figure
                class="testimonial_image aos-init float-bob-y"
                data-aos="fade-left"
                data-aos-easing="linear"
                data-aos-duration="500"
              >
                <img src="/financer/assets/images/resource/testimonial-4.png" alt="" />
              </figure>
            </div>
          </div>
        </div>
      </section>
    </div>
  </PublicPageShell>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import PublicPageShell from './inc/PublicPageShell.vue'

const { t, tm } = useI18n()

type WhyColumn = {
  icon: string
  title: string
  text: string
}

type FeatureAccordionItem = {
  title: string
  body: string
}

type TeamMember = {
  name: string
  designation: string
  image: string
}

type TestimonialSlide = {
  text: string
}

const whyChooseColumns = computed<WhyColumn[]>(() => tm('aboutPage.whyChooseColumns') as WhyColumn[])
const featureAccordion = computed<FeatureAccordionItem[]>(
  () => tm('aboutPage.featureAccordion') as FeatureAccordionItem[],
)
const team = computed<TeamMember[]>(() => tm('homePage.team') as TeamMember[])
const testimonialSlides = computed<TestimonialSlide[]>(
  () => tm('aboutPage.testimonialSlides') as TestimonialSlide[],
)

const activeFeatureIndex = ref(2)

function selectFeatureItem(index: number) {
  activeFeatureIndex.value = index
}

const aboutRoot = ref<HTMLElement | null>(null)
let aosObserver: IntersectionObserver | null = null

function setupAosScroll(contentRoot: HTMLElement) {
  const scope = contentRoot.closest('.boxed_wrapper') ?? contentRoot

  if (!('IntersectionObserver' in window)) {
    scope.querySelectorAll('[data-aos]').forEach((el) => el.classList.add('aos-animate'))
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

  scope.querySelectorAll('[data-aos]').forEach((el) => aosObserver?.observe(el))
}

onMounted(() => {
  void nextTick(() => {
    const root = aboutRoot.value
    if (root) {
      setupAosScroll(root)
    }
  })
})

onBeforeUnmount(() => {
  aosObserver?.disconnect()
  aosObserver = null
})
</script>
