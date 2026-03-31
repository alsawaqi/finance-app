<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

type BankForm = {
  id: number | null
  name: string
  code: string
  short_name: string
  is_active: boolean
}

const props = defineProps<{
  modelValue: BankForm
  isEditing: boolean
  isSaving: boolean
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: BankForm): void
  (e: 'save'): void
  (e: 'reset'): void
}>()
const { t } = useI18n()

const form = computed({
  get: () => props.modelValue,
  set: (value: BankForm) => emit('update:modelValue', value),
})

function updateField<K extends keyof BankForm>(key: K, value: BankForm[K]) {
  form.value = {
    ...form.value,
    [key]: value,
  }
}

function firstError(field: string) {
  return props.errors?.[field]?.[0] ?? ''
}
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ isEditing ? t('adminBankBuilder.eyebrow.edit') : t('adminBankBuilder.eyebrow.create') }}</span>
        <h2>{{ t('adminBankBuilder.title') }}</h2>
      </div>
      <button type="button" class="admin-panel__action" @click="$emit('reset')">
        {{ isEditing ? t('adminBankBuilder.actions.cancelEdit') : t('adminBankBuilder.actions.clear') }}
      </button>
    </div>

    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-form-field">
        <span>{{ t('adminBankBuilder.fields.bankName') }}</span>
        <input :value="form.name" type="text" class="admin-form-input" :class="{ 'has-error': firstError('name') }" :placeholder="t('adminBankBuilder.placeholders.bankName')" @input="updateField('name', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('name')" class="admin-form-error">{{ firstError('name') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ t('adminBankBuilder.fields.code') }}</span>
        <input :value="form.code" type="text" class="admin-form-input" :class="{ 'has-error': firstError('code') }" :placeholder="t('adminBankBuilder.placeholders.code')" @input="updateField('code', ($event.target as HTMLInputElement).value.toUpperCase())" />
        <small v-if="firstError('code')" class="admin-form-error">{{ firstError('code') }}</small>
      </label>

      <label class="admin-form-field admin-form-field--full">
        <span>{{ t('adminBankBuilder.fields.shortName') }}</span>
        <input :value="form.short_name" type="text" class="admin-form-input" :class="{ 'has-error': firstError('short_name') }" :placeholder="t('adminBankBuilder.placeholders.shortName')" @input="updateField('short_name', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('short_name')" class="admin-form-error">{{ firstError('short_name') }}</small>
      </label>

      <div class="admin-form-switches admin-form-field--full">
        <label class="admin-switch-card">
          <input :checked="form.is_active" type="checkbox" @change="updateField('is_active', ($event.target as HTMLInputElement).checked)" />
          <div>
            <strong>{{ t('adminBankBuilder.switches.activeTitle') }}</strong>
            <span>{{ t('adminBankBuilder.switches.activeDesc') }}</span>
          </div>
        </label>
      </div>
    </div>

    <div class="admin-form-actions">
      <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="$emit('save')">
        {{ isSaving ? (isEditing ? t('adminBankBuilder.actions.updating') : t('adminBankBuilder.actions.saving')) : isEditing ? t('adminBankBuilder.actions.updateBank') : t('adminBankBuilder.actions.createBank') }}
      </button>
      <button type="button" class="admin-secondary-btn" @click="$emit('reset')">{{ isEditing ? t('adminBankBuilder.actions.cancelEdit') : t('adminBankBuilder.actions.reset') }}</button>
    </div>
  </section>
</template>
