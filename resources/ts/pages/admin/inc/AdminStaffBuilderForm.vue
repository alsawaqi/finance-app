<script setup lang="ts">
import { computed } from 'vue'

type StaffForm = {
  id: number | null
  name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
  is_active: boolean
  permission_names: string[]
}

const props = defineProps<{
  modelValue: StaffForm
  availablePermissions: string[]
  isEditing: boolean
  isSaving: boolean
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: StaffForm): void
  (e: 'save'): void
  (e: 'reset'): void
}>()

const form = computed({
  get: () => props.modelValue,
  set: (value: StaffForm) => emit('update:modelValue', value),
})

function updateField<K extends keyof StaffForm>(key: K, value: StaffForm[K]) {
  form.value = {
    ...form.value,
    [key]: value,
  }
}

function togglePermission(name: string) {
  const next = new Set(form.value.permission_names)

  if (next.has(name)) next.delete(name)
  else next.add(name)

  updateField('permission_names', Array.from(next).sort())
}

function firstError(field: string) {
  return props.errors?.[field]?.[0] ?? ''
}
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ isEditing ? 'Edit staff' : 'Create staff' }}</span>
        <h2>Staff account setup</h2>
      </div>
      <button type="button" class="admin-panel__action" @click="$emit('reset')">
        {{ isEditing ? 'Cancel edit' : 'Clear' }}
      </button>
    </div>

    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-form-field">
        <span>Full name</span>
        <input
          :value="form.name"
          type="text"
          class="admin-form-input"
          :class="{ 'has-error': firstError('name') }"
          placeholder="Enter staff full name"
          @input="updateField('name', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('name')" class="admin-form-error">{{ firstError('name') }}</small>
      </label>

      <label class="admin-form-field">
        <span>Email address</span>
        <input
          :value="form.email"
          type="email"
          class="admin-form-input"
          :class="{ 'has-error': firstError('email') }"
          placeholder="staff@example.com"
          @input="updateField('email', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('email')" class="admin-form-error">{{ firstError('email') }}</small>
      </label>

      <label class="admin-form-field">
        <span>Phone</span>
        <input
          :value="form.phone"
          type="text"
          class="admin-form-input"
          :class="{ 'has-error': firstError('phone') }"
          placeholder="Optional phone number"
          @input="updateField('phone', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('phone')" class="admin-form-error">{{ firstError('phone') }}</small>
      </label>

      <label class="admin-form-field">
        <span>Account type</span>
        <input value="staff" type="text" class="admin-form-input" disabled />
        <small class="admin-form-help">Staff users share the admin workspace but can be limited by permissions.</small>
      </label>

      <label class="admin-form-field">
        <span>{{ isEditing ? 'New password' : 'Password' }}</span>
        <input
          :value="form.password"
          type="password"
          class="admin-form-input"
          :class="{ 'has-error': firstError('password') }"
          :placeholder="isEditing ? 'Leave blank to keep current password' : 'Minimum 8 characters'"
          @input="updateField('password', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('password')" class="admin-form-error">{{ firstError('password') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ isEditing ? 'Confirm new password' : 'Confirm password' }}</span>
        <input
          :value="form.password_confirmation"
          type="password"
          class="admin-form-input"
          :class="{ 'has-error': firstError('password_confirmation') }"
          placeholder="Repeat password"
          @input="updateField('password_confirmation', ($event.target as HTMLInputElement).value)"
        />
      </label>

      <div class="admin-form-field admin-form-field--full">
        <span>Direct permissions</span>
        <div class="admin-permission-grid">
          <label v-for="permission in availablePermissions" :key="permission" class="admin-check-card">
            <input
              :checked="form.permission_names.includes(permission)"
              type="checkbox"
              @change="togglePermission(permission)"
            />
            <div>
              <strong>{{ permission }}</strong>
              <span>Grant this permission directly to the staff member.</span>
            </div>
          </label>
        </div>
        <small v-if="firstError('permission_names')" class="admin-form-error">{{ firstError('permission_names') }}</small>
      </div>

      <div class="admin-form-switches admin-form-field--full">
        <label class="admin-switch-card">
          <input
            :checked="form.is_active"
            type="checkbox"
            @change="updateField('is_active', ($event.target as HTMLInputElement).checked)"
          />
          <div>
            <strong>Active account</strong>
            <span>Inactive staff can no longer use the admin workspace.</span>
          </div>
        </label>
      </div>
    </div>

    <div class="admin-form-actions">
      <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="$emit('save')">
        {{ isSaving ? (isEditing ? 'Updating...' : 'Saving...') : isEditing ? 'Update staff' : 'Create staff' }}
      </button>
      <button type="button" class="admin-secondary-btn" @click="$emit('reset')">
        {{ isEditing ? 'Cancel edit' : 'Reset' }}
      </button>
    </div>
  </section>
</template>
