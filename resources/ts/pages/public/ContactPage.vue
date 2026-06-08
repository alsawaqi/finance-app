<template>
  <PublicPageShell>
    <div ref="contactRoot" class="contact-page-root">
      <section class="page-title centred">
        <div class="container">
          <div class="content-box">
            <h1>{{ t('contactPage.heroTitle') }}</h1>
            <p>{{ t('contactPage.heroText') }}</p>
          </div>
        </div>
      </section>

      <section class="contact_info_section pt_150 pb_120">
        <div class="container">
          <div class="section_title centred">
            <div class="tag_text">
              <h6>{{ t('contactPage.infoTag') }}</h6>
            </div>
            <h2>{{ t('contactPage.infoTitle') }}</h2>
          </div>

          <div class="row">
            <div
              v-for="(card, index) in contactCards"
              :key="'contact-card-' + index"
              class="col-xl-4 col-lg-4 col-md-6 col-sm-12"
            >
              <div
                class="contact_block_one mb_30 aos-init"
                data-aos="fade-up"
                data-aos-easing="linear"
                :data-aos-duration="500 + index * 50"
              >
                <div class="contact_block_icon">
                  <i :class="card.icon"></i>
                </div>
                <div class="contact_block_title">
                  <h4>{{ card.title }}</h4>
                </div>
                <div class="contact_block_text">
                  <p>{{ card.text }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section
        class="google_map novacast-contact-map aos-init"
        data-aos="fade-up"
        data-aos-easing="linear"
        data-aos-duration="500"
      >
        <div class="container">
          <div class="map_outer_box">
            <iframe
              :title="t('contactPage.mapTitle')"
              src="https://www.google.com/maps?q=Building%203275%2C%20Al%20Farisha%20Street%2C%20Ishbiliyah%20District%2C%20Riyadh%2013225%2C%20Saudi%20Arabia&output=embed"
              width="100%"
              height="520"
              style="border: 0"
              allowfullscreen
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
            ></iframe>
          </div>
        </div>
      </section>

      <section
        class="contact_section pt_150 pb_120 aos-init"
        data-aos="fade-up"
        data-aos-easing="linear"
        data-aos-duration="500"
      >
        <div class="container">
          <div class="section_title centred">
            <div class="tag_text">
              <h6>{{ t('contactPage.formTag') }}</h6>
            </div>
            <h2>{{ t('contactPage.formTitle') }}</h2>
          </div>

          <form class="contact_form" @submit.prevent>
            <div class="row">
              <div class="col-xl-6 col-md-6 col-sm-12">
                <div class="form-group">
                  <input type="text" name="user" :placeholder="t('contactPage.namePlaceholder')" />
                </div>
              </div>
              <div class="col-xl-6 col-md-6 col-sm-12">
                <div class="form-group">
                  <input type="email" name="email" :placeholder="t('contactPage.emailPlaceholder')" />
                </div>
              </div>
              <div class="col-xl-12 col-md-12 col-sm-12">
                <div class="form-group">
                  <textarea name="message" :placeholder="t('contactPage.messagePlaceholder')"></textarea>
                </div>
              </div>
              <div class="col-xl-12 col-md-12 col-sm-12">
                <div class="form-group centred">
                  <button type="submit" name="button" class="btn_style_one">
                    {{ t('contactPage.sendButton') }}
                  </button>
                </div>
              </div>
            </div>
          </form>
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

type ContactCard = {
  icon: string
  title: string
  text: string
}

const contactCards = computed<ContactCard[]>(() => tm('contactPage.cards') as ContactCard[])
const contactRoot = ref<HTMLElement | null>(null)
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

  const root = contactRoot.value
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
.contact-page-root .contact_block_one {
  min-height: 310px;
  box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
}

.contact-page-root .contact_block_text p {
  margin: 0;
  overflow-wrap: anywhere;
}

.novacast-contact-map .map_outer_box {
  box-shadow: 0 26px 70px rgba(15, 23, 42, 0.12);
}

.contact-page-root .contact_form input,
.contact-page-root .contact_form textarea {
  padding: 0 26px;
}

.contact-page-root .contact_form textarea {
  padding-top: 22px;
  resize: vertical;
}

@media (max-width: 767px) {
  .contact-page-root .contact_block_one {
    min-height: auto;
  }
}
</style>
