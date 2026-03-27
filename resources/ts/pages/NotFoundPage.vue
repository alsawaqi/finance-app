<template>

<PublicPageShell>
<!-- Error Section -->
<section class="error_section pt_150 pb_150">
    <div class="container">
        <div class="error_content centred">
            <div class="error_image_box float-bob-x">
                <img src="../../../public/financer/assets/images/resource/error_image.png" alt="">
            </div>
            <h2><span>Oops!</span> That Page Can Not be Found.</h2>
            <div class="error_btn_box">
              
                <RouterLink to="/" class="btn_style_two"><span>Go Back</span></RouterLink>
                <RouterLink to="/" class="btn_style_two"><span>Back to Homepage</span></RouterLink>
            </div>
        </div>
    </div>
</section>
<!-- Error Section End -->


</PublicPageShell>
    
  </template>
  
  <script setup lang="ts">
  import PublicPageShell from './public/inc/PublicPageShell.vue'
  </script>