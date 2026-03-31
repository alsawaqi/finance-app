<template>

<PublicPageShell>
<!-- Error Section -->
<section class="error_section pt_150 pb_150">
    <div class="container">
        <div class="error_content centred">
            <div class="error_image_box float-bob-x">
                <img src="../../../public/financer/assets/images/resource/error_image.png" alt="">
            </div>
            <h2><span>{{ t('notFound.oops') }}</span> {{ t('notFound.title') }}</h2>
            <div class="error_btn_box">
              
                <RouterLink to="/" class="btn_style_two"><span>{{ t('notFound.goBack') }}</span></RouterLink>
                <RouterLink to="/" class="btn_style_two"><span>{{ t('notFound.backToHomepage') }}</span></RouterLink>
            </div>
        </div>
    </div>
</section>
<!-- Error Section End -->


</PublicPageShell>
    
  </template>
  
  <script setup lang="ts">
  import { useI18n } from 'vue-i18n'
  import PublicPageShell from './public/inc/PublicPageShell.vue'
  const { t } = useI18n()
  </script>