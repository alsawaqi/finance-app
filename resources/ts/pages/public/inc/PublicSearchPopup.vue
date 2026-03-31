<template>
    <div id="search-popup" class="search-popup" :class="{ 'popup-visible': open }">
      <div class="popup-inner">
        <div class="upper-box">
          <figure class="logo-box">
            <RouterLink to="/">
              <img src="/financer/assets/images/logo.png" alt="" />
            </RouterLink>
          </figure>
          <div class="close-search" @click="$emit('close')">
            <span class="fa-solid fa-xmark"></span>
          </div>
        </div>
  
        <div class="overlay-layer" @click="$emit('close')"></div>
  
        <div class="container">
          <div class="search-form">
            <form @submit.prevent>
              <div class="form-group">
                <fieldset>
                  <input
                    :value="modelValue"
                    type="search"
                    class="form-control"
                    name="search-input"
                    :placeholder="t('publicSearchPopup.placeholder')"
                    required
                    @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
                  />
                  <button type="submit">
                    <i class="icon-50"></i>
                  </button>
                </fieldset>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()

  defineProps<{
    open: boolean
    modelValue: string
  }>()
  
  defineEmits<{
    (e: 'close'): void
    (e: 'update:modelValue', value: string): void
  }>()
  </script>