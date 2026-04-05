<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppLocale } from '@/composables/useAppLocale'
import type { AppLocale } from '@/i18n'

const props = withDefaults(defineProps<{
  id?: string
  mode?: 'public' | 'client' | 'mobile' | 'admin'
  shortLabels?: boolean
}>(), {
  id: 'app-locale-select',
  mode: 'public',
  shortLabels: false,
})

const { t } = useI18n()
const { currentLocale, localeOptions, changeLocale } = useAppLocale()

const resolvedOptions = computed(() => localeOptions.value.map((option) => ({
  ...option,
  label: props.shortLabels ? option.value.toUpperCase() : option.label,
})))

function onChange(event: Event) {
  const target = event.target as HTMLSelectElement | null
  if (!target) return
  changeLocale(target.value as AppLocale)
}
</script>

<template>
  <div class="app-locale-select" :class="[`app-locale-select--${mode}`]">
    <label class="app-locale-select__sr" :for="id">{{ t('common.languageLabel') }}</label>

    <span class="app-locale-select__icon" aria-hidden="true">
      <i class="fa-solid fa-earth-asia"></i>
    </span>

    <select
      :id="id"
      class="app-locale-select__control"
      :value="currentLocale"
      @change="onChange"
    >
      <option
        v-for="option in resolvedOptions"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>

    <span class="app-locale-select__chevron" aria-hidden="true">
      <i class="fa-solid fa-angle-down"></i>
    </span>
  </div>
</template>

<style scoped>
.app-locale-select {
  --locale-height: 50px;
  --locale-radius: 18px;
  --locale-width: 174px;
  position: relative;
  display: inline-flex;
  align-items: center;
  min-width: var(--locale-width);
  width: var(--locale-width);
  height: var(--locale-height);
  border-radius: var(--locale-radius);
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.95));
  border: 1px solid rgba(148, 163, 184, 0.26);
  box-shadow:
    0 18px 36px rgba(15, 23, 42, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.8);
  overflow: hidden;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.app-locale-select:hover,
.app-locale-select:focus-within {
  border-color: rgba(124, 58, 237, 0.34);
  box-shadow:
    0 22px 42px rgba(37, 99, 235, 0.12),
    0 0 0 4px rgba(124, 58, 237, 0.08);
  transform: translateY(-1px);
}

.app-locale-select::after {
  content: '';
  position: absolute;
  inset: 1px;
  border-radius: calc(var(--locale-radius) - 1px);
  background: linear-gradient(135deg, rgba(255,255,255,0.24), rgba(255,255,255,0));
  pointer-events: none;
}

.app-locale-select--client {
  --locale-height: 46px;
  --locale-radius: 16px;
  --locale-width: 156px;
}

.app-locale-select--admin {
  --locale-height: 44px;
  --locale-radius: 14px;
  --locale-width: 132px;
  background: rgba(255, 255, 255, 0.92);
  border-color: rgba(148, 163, 184, 0.24);
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
}

.app-locale-select--mobile {
  --locale-height: 52px;
  --locale-radius: 18px;
  --locale-width: 100%;
  display: flex;
}

.app-locale-select__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.app-locale-select__icon,
.app-locale-select__chevron {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  z-index: 1;
}

.app-locale-select__icon {
  inset-inline-start: 16px;
  color: #475569;
  font-size: 14px;
}

.app-locale-select__chevron {
  inset-inline-end: 16px;
  color: #94a3b8;
  font-size: 13px;
}

.app-locale-select__control {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 100%;
  border: 0;
  outline: none;
  background: transparent;
  color: #0f172a;
  font-size: 14px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: 0.01em;
  cursor: pointer;
  appearance: none;
  padding-inline-start: 44px;
  padding-inline-end: 42px;
}

.app-locale-select--admin .app-locale-select__control {
  font-size: 13px;
  font-weight: 700;
}

.app-locale-select__control option {
  color: #0f172a;
  font-weight: 700;
}

@media (max-width: 575px) {
  .app-locale-select--public {
    --locale-width: 150px;
  }

  .app-locale-select--client,
  .app-locale-select--admin {
    --locale-width: 100%;
  }
}
</style>
