<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { BankItem } from '@/services/banks'

type AgentForm = {
  id: number | null
  name: string
  email: string
  phone: string
  company_name: string
  bank_id: number | null
  agent_type: string
  notes: string
  is_active: boolean
}

const props = defineProps<{
  modelValue: AgentForm
  isEditing: boolean
  isSaving: boolean
  banks: BankItem[]
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: AgentForm): void
  (e: 'save'): void
  (e: 'reset'): void
}>()
const { t } = useI18n()

const form = computed({
  get: () => props.modelValue,
  set: (value: AgentForm) => emit('update:modelValue', value),
})

function updateField<K extends keyof AgentForm>(key: K, value: AgentForm[K]) {
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
        <span class="admin-panel__eyebrow">{{ isEditing ? t('adminAgentBuilder.eyebrow.edit') : t('adminAgentBuilder.eyebrow.create') }}</span>
        <h2>{{ t('adminAgentBuilder.title') }}</h2>
      </div>
      <button type="button" class="admin-panel__action" @click="$emit('reset')">
        {{ isEditing ? t('adminAgentBuilder.actions.cancelEdit') : t('adminAgentBuilder.actions.clear') }}
      </button>
    </div>

    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-form-field">
        <span>{{ t('adminAgentBuilder.fields.name') }}</span>
        <input :value="form.name" type="text" class="admin-form-input" :class="{ 'has-error': firstError('name') }" :placeholder="t('adminAgentBuilder.placeholders.name')" @input="updateField('name', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('name')" class="admin-form-error">{{ firstError('name') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ t('adminAgentBuilder.fields.bank') }}</span>
        <select
          class="admin-form-select"
          :class="{ 'has-error': firstError('bank_id') }"
          :value="form.bank_id ?? ''"
          @change="updateField('bank_id', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
        >
          <option value="">{{ t('adminAgentBuilder.fields.selectBank') }}</option>
          <option v-for="bank in banks" :key="bank.id" :value="bank.id">
            {{ bank.name }}{{ bank.short_name ? ` · ${bank.short_name}` : '' }}{{ !bank.is_active ? ` (${t('adminAgentBuilder.states.inactive')})` : '' }}
          </option>
        </select>
        <small v-if="firstError('bank_id')" class="admin-form-error">{{ firstError('bank_id') }}</small>
        <small v-else-if="banks.length === 0" class="admin-form-hint">{{ t('adminAgentBuilder.help.createBanksFirst') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ t('adminAgentBuilder.fields.agentType') }}</span>
        <input :value="form.agent_type" type="text" class="admin-form-input" :class="{ 'has-error': firstError('agent_type') }" :placeholder="t('adminAgentBuilder.placeholders.agentType')" @input="updateField('agent_type', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('agent_type')" class="admin-form-error">{{ firstError('agent_type') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ t('adminAgentBuilder.fields.email') }}</span>
        <input :value="form.email" type="email" class="admin-form-input" :class="{ 'has-error': firstError('email') }" :placeholder="t('adminAgentBuilder.placeholders.email')" @input="updateField('email', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('email')" class="admin-form-error">{{ firstError('email') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ t('adminAgentBuilder.fields.phone') }}</span>
        <input :value="form.phone" type="text" class="admin-form-input" :class="{ 'has-error': firstError('phone') }" :placeholder="t('adminAgentBuilder.placeholders.phone')" @input="updateField('phone', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('phone')" class="admin-form-error">{{ firstError('phone') }}</small>
      </label>

      <label class="admin-form-field admin-form-field--full">
        <span>{{ t('adminAgentBuilder.fields.companyName') }}</span>
        <input :value="form.company_name" type="text" class="admin-form-input" :class="{ 'has-error': firstError('company_name') }" :placeholder="t('adminAgentBuilder.placeholders.companyName')" @input="updateField('company_name', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('company_name')" class="admin-form-error">{{ firstError('company_name') }}</small>
      </label>

      <label class="admin-form-field admin-form-field--full">
        <span>{{ t('adminAgentBuilder.fields.notes') }}</span>
        <textarea :value="form.notes" rows="4" class="admin-form-textarea" :class="{ 'has-error': firstError('notes') }" :placeholder="t('adminAgentBuilder.placeholders.notes')" @input="updateField('notes', ($event.target as HTMLTextAreaElement).value)"></textarea>
        <small v-if="firstError('notes')" class="admin-form-error">{{ firstError('notes') }}</small>
      </label>

      <div class="admin-form-switches admin-form-field--full">
        <label class="admin-switch-card">
          <input :checked="form.is_active" type="checkbox" @change="updateField('is_active', ($event.target as HTMLInputElement).checked)" />
          <div>
            <strong>{{ t('adminAgentBuilder.switches.activeTitle') }}</strong>
            <span>{{ t('adminAgentBuilder.switches.activeDesc') }}</span>
          </div>
        </label>
      </div>
    </div>

    <div class="admin-form-actions">
      <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="$emit('save')">
        {{ isSaving ? (isEditing ? t('adminAgentBuilder.actions.updating') : t('adminAgentBuilder.actions.saving')) : isEditing ? t('adminAgentBuilder.actions.updateAgent') : t('adminAgentBuilder.actions.createAgent') }}
      </button>
      <button type="button" class="admin-secondary-btn" @click="$emit('reset')">{{ isEditing ? t('adminAgentBuilder.actions.cancelEdit') : t('adminAgentBuilder.actions.reset') }}</button>
    </div>
  </section>
</template>
