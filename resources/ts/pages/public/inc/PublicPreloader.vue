<template>
    <div v-if="show" class="loader-wrap">
      <div class="preloader">
        <button type="button" class="preloader-close" :aria-label="closeLabel" @click="$emit('close')">×</button>
        <div id="handle-preloader" class="handle-preloader">
          <div class="animation-preloader">
            <div class="spinner"></div>
            <div class="txt-loading">
              <span
                v-for="letter in letters"
                :key="letter"
                class="letters-loading"
                :data-text-preloader="letter"
              >
                {{ letter }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'

  defineProps<{
    show: boolean
    letters: string[]
  }>()

  const { locale } = useI18n()
  const closeLabel = computed(() => (locale.value === 'ar' ? 'إغلاق' : 'Close'))
  
  defineEmits<{
    (e: 'close'): void
  }>()
  </script>
