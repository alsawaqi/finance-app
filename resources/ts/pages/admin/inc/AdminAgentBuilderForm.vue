<script setup lang="ts">
import { computed } from 'vue'

type AgentForm = {
  id: number | null
  name: string
  email: string
  phone: string
  company_name: string
  agent_type: string
  notes: string
  is_active: boolean
}

const props = defineProps<{
  modelValue: AgentForm
  isEditing: boolean
  isSaving: boolean
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: AgentForm): void
  (e: 'save'): void
  (e: 'reset'): void
}>()

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
        <span class="admin-panel__eyebrow">{{ isEditing ? 'Edit agent' : 'Create agent' }}</span>
        <h2>Agent setup</h2>
      </div>
      <button type="button" class="admin-panel__action" @click="$emit('reset')">
        {{ isEditing ? 'Cancel edit' : 'Clear' }}
      </button>
    </div>

    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-form-field">
        <span>Name</span>
        <input :value="form.name" type="text" class="admin-form-input" :class="{ 'has-error': firstError('name') }" placeholder="Agent or contact name" @input="updateField('name', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('name')" class="admin-form-error">{{ firstError('name') }}</small>
      </label>

      <label class="admin-form-field">
        <span>Agent type</span>
        <input :value="form.agent_type" type="text" class="admin-form-input" :class="{ 'has-error': firstError('agent_type') }" placeholder="bank, broker, insurance, government..." @input="updateField('agent_type', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('agent_type')" class="admin-form-error">{{ firstError('agent_type') }}</small>
      </label>

      <label class="admin-form-field">
        <span>Email</span>
        <input :value="form.email" type="email" class="admin-form-input" :class="{ 'has-error': firstError('email') }" placeholder="Optional email" @input="updateField('email', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('email')" class="admin-form-error">{{ firstError('email') }}</small>
      </label>

      <label class="admin-form-field">
        <span>Phone</span>
        <input :value="form.phone" type="text" class="admin-form-input" :class="{ 'has-error': firstError('phone') }" placeholder="Optional phone" @input="updateField('phone', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('phone')" class="admin-form-error">{{ firstError('phone') }}</small>
      </label>

      <label class="admin-form-field admin-form-field--full">
        <span>Company name</span>
        <input :value="form.company_name" type="text" class="admin-form-input" :class="{ 'has-error': firstError('company_name') }" placeholder="Associated company or institution" @input="updateField('company_name', ($event.target as HTMLInputElement).value)" />
        <small v-if="firstError('company_name')" class="admin-form-error">{{ firstError('company_name') }}</small>
      </label>

      <label class="admin-form-field admin-form-field--full">
        <span>Notes</span>
        <textarea :value="form.notes" rows="4" class="admin-form-textarea" :class="{ 'has-error': firstError('notes') }" placeholder="Anything useful for internal follow-up" @input="updateField('notes', ($event.target as HTMLTextAreaElement).value)"></textarea>
        <small v-if="firstError('notes')" class="admin-form-error">{{ firstError('notes') }}</small>
      </label>

      <div class="admin-form-switches admin-form-field--full">
        <label class="admin-switch-card">
          <input :checked="form.is_active" type="checkbox" @change="updateField('is_active', ($event.target as HTMLInputElement).checked)" />
          <div>
            <strong>Active contact</strong>
            <span>Inactive agents stay in the system but are hidden from active workflows later.</span>
          </div>
        </label>
      </div>
    </div>

    <div class="admin-form-actions">
      <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="$emit('save')">
        {{ isSaving ? (isEditing ? 'Updating...' : 'Saving...') : isEditing ? 'Update agent' : 'Create agent' }}
      </button>
      <button type="button" class="admin-secondary-btn" @click="$emit('reset')">{{ isEditing ? 'Cancel edit' : 'Reset' }}</button>
    </div>
  </section>
</template>
