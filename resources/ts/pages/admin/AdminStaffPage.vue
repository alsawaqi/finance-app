<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import AdminStaffBuilderForm from './inc/AdminStaffBuilderForm.vue'
import AdminStaffLibraryTable from './inc/AdminStaffLibraryTable.vue'
import type { StaffUserItem, StaffUserPayload } from '@/services/staffUsers'
import { createStaffUser, listStaffUsers, toggleStaffUserActive, updateStaffUser } from '@/services/staffUsers'

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

const rows = ref<StaffUserItem[]>([])
const availablePermissions = ref<string[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const formError = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<StaffForm>(createDefaultForm())
const isEditing = computed(() => form.value.id !== null)

const stats = computed(() => {
  const total = rows.value.length
  const active = rows.value.filter((item) => item.is_active).length
  const withDirectPermissions = rows.value.filter((item) => item.permissions_count > 0).length
  const loggedIn = rows.value.filter((item) => !!item.last_login_at).length

  return [
    { label: 'Total staff', value: String(total), tone: 'violet' },
    { label: 'Active staff', value: String(active), tone: 'emerald' },
    { label: 'With direct permissions', value: String(withDirectPermissions), tone: 'blue' },
    { label: 'Logged in at least once', value: String(loggedIn), tone: 'amber' },
  ]
})

onMounted(async () => {
  await fetchRows()
})

function createDefaultForm(): StaffForm {
  return {
    id: null,
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    is_active: true,
    permission_names: [],
  }
}

function clearMessages() {
  formError.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
}

async function fetchRows() {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listStaffUsers()
    rows.value = data.data
    availablePermissions.value = data.meta.available_permissions ?? []
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to load staff accounts right now.')
  } finally {
    isLoading.value = false
  }
}

function resetForm() {
  clearMessages()
  form.value = createDefaultForm()
}

function buildPayload(): StaffUserPayload {
  const payload: StaffUserPayload = {
    name: form.value.name.trim(),
    email: form.value.email.trim(),
    phone: form.value.phone.trim() || null,
    is_active: form.value.is_active,
    permission_names: [...form.value.permission_names],
  }

  if (form.value.password.trim()) {
    payload.password = form.value.password
    payload.password_confirmation = form.value.password_confirmation
  }

  return payload
}

async function saveRow() {
  clearMessages()
  isSaving.value = true

  try {
    if (isEditing.value && form.value.id) {
      const { data } = await updateStaffUser(form.value.id, buildPayload())
      successMessage.value = data.message
    } else {
      const { data } = await createStaffUser(buildPayload())
      successMessage.value = data.message
    }

    await fetchRows()
    resetForm()
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? 'Unable to save the staff account.'
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = 'Unable to save the staff account.'
    }
  } finally {
    isSaving.value = false
  }
}

function editRow(row: StaffUserItem) {
  clearMessages()
  form.value = {
    id: row.id,
    name: row.name,
    email: row.email,
    phone: row.phone ?? '',
    password: '',
    password_confirmation: '',
    is_active: row.is_active,
    permission_names: [...row.permission_names],
  }

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function toggleRow(row: StaffUserItem) {
  clearMessages()

  try {
    const { data } = await toggleStaffUserActive(row.id)
    successMessage.value = data.message
    rows.value = rows.value.map((item) => (item.id === row.id ? data.data : item))
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to update staff status right now.')
  }
}

function extractErrorMessage(error: unknown, fallback: string) {
  if (axios.isAxiosError(error)) {
    return error.response?.data?.message ?? fallback
  }

  return fallback
}
</script>

<template>
  <div class="admin-question-page">
    <section class="admin-hero admin-reveal-up">
      <div class="admin-hero__content">
        <span class="admin-hero__eyebrow">Staff Management</span>
        <h2>Create internal staff accounts and control what they can access.</h2>
        <p>
          Staff members use the admin workspace, but you can grant direct permissions for setup pages,
          request operations, and later page-level visibility.
        </p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveRow">
          {{ isSaving ? (isEditing ? 'Updating...' : 'Saving...') : isEditing ? 'Update staff' : 'Create staff' }}
        </button>
        <button type="button" class="admin-secondary-btn" @click="resetForm">
          {{ isEditing ? 'Cancel edit' : 'Reset form' }}
        </button>
      </div>
    </section>

    <div class="admin-question-stats-grid admin-reveal-up admin-reveal-delay-1">
      <article v-for="stat in stats" :key="stat.label" class="admin-question-stat" :class="`tone-${stat.tone}`">
        <strong>{{ stat.value }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </div>

    <div v-if="formError" class="admin-alert admin-alert--error">{{ formError }}</div>
    <div v-if="successMessage" class="admin-alert admin-alert--success">{{ successMessage }}</div>

    <div class="admin-question-builder-grid">
      <AdminStaffBuilderForm
        v-model="form"
        :available-permissions="availablePermissions"
        :is-editing="isEditing"
        :is-saving="isSaving"
        :errors="fieldErrors"
        @save="saveRow"
        @reset="resetForm"
      />

      <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">Permission notes</span>
            <h2>How staff access works</h2>
          </div>
        </div>

        <div class="admin-question-preview__notes">
          <article class="admin-question-preview__note">
            <span>Shared workspace</span>
            <strong>Staff sign into the same admin area as admins.</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>Role</span>
            <strong>Each account is automatically assigned the <code>staff</code> role.</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>Direct permissions</span>
            <strong>Extra permissions can be added per user for setup and management pages.</strong>
          </article>
        </div>

        <div class="admin-pill-list">
          <span class="admin-chip admin-chip--violet">admin + staff share dashboard</span>
          <span class="admin-chip admin-chip--blue">permissions for page visibility later</span>
          <span class="admin-chip admin-chip--emerald">inactive staff cannot continue operations</span>
        </div>
      </section>
    </div>

    <AdminStaffLibraryTable
      :rows="rows"
      :loading="isLoading"
      @edit="editRow"
      @toggle="toggleRow"
    />
  </div>
</template>
