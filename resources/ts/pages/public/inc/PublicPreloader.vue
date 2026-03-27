<template>
    <div v-if="show" class="loader-wrap">
      <div class="preloader">
        <div class="preloader-close" @click="$emit('close')">x</div>
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
  defineProps<{
    show: boolean
    letters: string[]
  }>()
  
  defineEmits<{
    (e: 'close'): void
  }>()
  </script>