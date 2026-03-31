<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

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
const { t } = useI18n()

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
        <span class="admin-panel__eyebrow">{{ isEditing ? t('adminStaffBuilder.eyebrow.edit') : t('adminStaffBuilder.eyebrow.create') }}</span>
        <h2>{{ t('adminStaffBuilder.title') }}</h2>
      </div>
      <button type="button" class="admin-panel__action" @click="$emit('reset')">
        {{ isEditing ? t('adminStaffBuilder.actions.cancelEdit') : t('adminStaffBuilder.actions.clear') }}
      </button>
    </div>

    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-form-field">
        <span>{{ t('adminStaffBuilder.fields.fullName') }}</span>
        <input
          :value="form.name"
          type="text"
          class="admin-form-input"
          :class="{ 'has-error': firstError('name') }"
          :placeholder="t('adminStaffBuilder.placeholders.fullName')"
          @input="updateField('name', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('name')" class="admin-form-error">{{ firstError('name') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ t('adminStaffBuilder.fields.email') }}</span>
        <input
          :value="form.email"
          type="email"
          class="admin-form-input"
          :class="{ 'has-error': firstError('email') }"
          :placeholder="t('adminStaffBuilder.placeholders.email')"
          @input="updateField('email', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('email')" class="admin-form-error">{{ firstError('email') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ t('adminStaffBuilder.fields.phone') }}</span>
        <input
          :value="form.phone"
          type="text"
          class="admin-form-input"
          :class="{ 'has-error': firstError('phone') }"
          :placeholder="t('adminStaffBuilder.placeholders.phone')"
          @input="updateField('phone', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('phone')" class="admin-form-error">{{ firstError('phone') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ t('adminStaffBuilder.fields.accountType') }}</span>
        <input value="staff" type="text" class="admin-form-input" disabled />
        <small class="admin-form-help">{{ t('adminStaffBuilder.help.accountType') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ isEditing ? t('adminStaffBuilder.fields.newPassword') : t('adminStaffBuilder.fields.password') }}</span>
        <input
          :value="form.password"
          type="password"
          class="admin-form-input"
          :class="{ 'has-error': firstError('password') }"
          :placeholder="isEditing ? t('adminStaffBuilder.placeholders.newPassword') : t('adminStaffBuilder.placeholders.password')"
          @input="updateField('password', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('password')" class="admin-form-error">{{ firstError('password') }}</small>
      </label>

      <label class="admin-form-field">
        <span>{{ isEditing ? t('adminStaffBuilder.fields.confirmNewPassword') : t('adminStaffBuilder.fields.confirmPassword') }}</span>
        <input
          :value="form.password_confirmation"
          type="password"
          class="admin-form-input"
          :class="{ 'has-error': firstError('password_confirmation') }"
          :placeholder="t('adminStaffBuilder.placeholders.confirmPassword')"
          @input="updateField('password_confirmation', ($event.target as HTMLInputElement).value)"
        />
      </label>

      <div class="admin-form-field admin-form-field--full">
        <span>{{ t('adminStaffBuilder.fields.directPermissions') }}</span>
        <div class="admin-permission-grid">
          <label v-for="permission in availablePermissions" :key="permission" class="admin-check-card">
            <input
              :checked="form.permission_names.includes(permission)"
              type="checkbox"
              @change="togglePermission(permission)"
            />
            <div>
              <strong>{{ permission }}</strong>
              <span>{{ t('adminStaffBuilder.help.directPermission') }}</span>
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
            <strong>{{ t('adminStaffBuilder.switches.activeTitle') }}</strong>
            <span>{{ t('adminStaffBuilder.switches.activeDesc') }}</span>
          </div>
        </label>
      </div>
    </div>

    <div class="admin-form-actions">
      <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="$emit('save')">
        {{ isSaving ? (isEditing ? t('adminStaffBuilder.actions.updating') : t('adminStaffBuilder.actions.saving')) : isEditing ? t('adminStaffBuilder.actions.updateStaff') : t('adminStaffBuilder.actions.createStaff') }}
      </button>
      <button type="button" class="admin-secondary-btn" @click="$emit('reset')">
        {{ isEditing ? t('adminStaffBuilder.actions.cancelEdit') : t('adminStaffBuilder.actions.reset') }}
      </button>
    </div>
  </section>
</template>
