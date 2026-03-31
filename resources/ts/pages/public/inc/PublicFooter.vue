<template>
    <footer class="main_footer">
      <div class="footer_top">
        <div class="container">
          <div class="row">
            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-7 col-xs-12">
              <div class="footer_widget about_widget aos-init" data-aos="fade-right" data-aos-easing="linear" data-aos-duration="500">
                <figure class="footer_logo">
                  <RouterLink to="/">
                    <img src="/financer/assets/images/footer-logo.png" alt="" />
                  </RouterLink>
                </figure>
                <p>{{ t('publicFooter.aboutText') }}</p>
                <ul class="social-links">
                  <li><a href="#" @click.prevent><i class="icon-43"></i></a></li>
                  <li><a href="#" @click.prevent><i class="icon-42"></i></a></li>
                  <li><a href="#" @click.prevent><i class="icon-40"></i></a></li>
                  <li><a href="#" @click.prevent><i class="icon-41"></i></a></li>
                </ul>
              </div>
            </div>
  
            <div class="col-xl-2 col-lg-2 col-md-6 col-sm-5 col-xs-12">
              <div class="footer_widget page_widget aos-init" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="550">
                <h4 class="footer_widget_title">{{ t('publicFooter.pagesTitle') }}</h4>
                <ul class="page_list">
                  <li><RouterLink to="/about">{{ t('publicFooter.pages.aboutUs') }}</RouterLink></li>
                  <li><a href="#" @click.prevent>{{ t('publicFooter.pages.blogs') }}</a></li>
                  <li><a href="#" @click.prevent>{{ t('publicFooter.pages.faq') }}</a></li>
                  <li><a href="#" @click.prevent>{{ t('publicFooter.pages.contact') }}</a></li>
                  <li><a href="#" @click.prevent>{{ t('publicFooter.pages.support') }}</a></li>
                </ul>
              </div>
            </div>
  
            <div class="col-xl-2 col-lg-2 col-md-6 col-sm-5 col-xs-12">
              <div class="footer_widget primary_page_widget aos-init" data-aos="fade-down" data-aos-easing="linear" data-aos-duration="600">
                <h4 class="footer_widget_title">{{ t('publicFooter.primaryTitle') }}</h4>
                <ul class="primary_page_list">
                  <li><a href="#" @click.prevent>{{ t('publicFooter.primary.services') }}</a></li>
                  <li><a href="#" @click.prevent>{{ t('publicFooter.primary.careers') }}</a></li>
                  <li><a href="#" @click.prevent>{{ t('publicFooter.primary.pricingPlans') }}</a></li>
                  <li><a href="#" @click.prevent>{{ t('publicFooter.primary.portfolio') }}</a></li>
                  <li><a href="#" @click.prevent>{{ t('publicFooter.primary.news') }}</a></li>
                </ul>
              </div>
            </div>
  
            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-7 col-xs-12">
              <div class="footer_widget newsletter_widget aos-init" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="650">
                <h4 class="footer_widget_title">{{ t('publicFooter.newsletterTitle') }}</h4>
                <p>{{ t('publicFooter.newsletterText') }}</p>
                <div class="subscribe-inner">
                  <form class="subscribe-form" @submit.prevent>
                    <div class="form-group">
                      <input type="email" name="email" :placeholder="t('publicFooter.emailPlaceholder')" />
                      <button type="submit" class="btn_style_one">{{ t('publicFooter.subscribe') }}</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <div class="footer_bottom">
        <div class="container">
          <div class="copyright">
            {{ t('publicFooter.copyrightPrefix') }} &copy; 2024 &nbsp;<RouterLink to="/">Financer</RouterLink>{{ t('publicFooter.copyrightSuffix') }}
          </div>
        </div>
      </div>
  
      <div class="footer_shape"></div>
    </footer>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>