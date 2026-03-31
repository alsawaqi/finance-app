<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
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
const { t } = useI18n()

const stats = computed(() => {
  const total = rows.value.length
  const active = rows.value.filter((item) => item.is_active).length
  const withDirectPermissions = rows.value.filter((item) => item.permissions_count > 0).length
  const loggedIn = rows.value.filter((item) => !!item.last_login_at).length

  return [
    { label: t('adminStaffPage.stats.totalStaff'), value: String(total), tone: 'violet' },
    { label: t('adminStaffPage.stats.activeStaff'), value: String(active), tone: 'emerald' },
    { label: t('adminStaffPage.stats.withDirectPermissions'), value: String(withDirectPermissions), tone: 'blue' },
    { label: t('adminStaffPage.stats.loggedInAtLeastOnce'), value: String(loggedIn), tone: 'amber' },
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
    formError.value = extractErrorMessage(error, t('adminStaffPage.errors.loadFailed'))
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
      formError.value = error.response?.data?.message ?? t('adminStaffPage.errors.saveFailed')
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = t('adminStaffPage.errors.saveFailed')
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
    formError.value = extractErrorMessage(error, t('adminStaffPage.errors.toggleFailed'))
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
        <span class="admin-hero__eyebrow">{{ t('adminStaffPage.hero.eyebrow') }}</span>
        <h2>{{ t('adminStaffPage.hero.title') }}</h2>
        <p>
          {{ t('adminStaffPage.hero.subtitle') }}
        </p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveRow">
          {{ isSaving ? (isEditing ? t('adminStaffPage.actions.updating') : t('adminStaffPage.actions.saving')) : isEditing ? t('adminStaffPage.actions.updateStaff') : t('adminStaffPage.actions.createStaff') }}
        </button>
        <button type="button" class="admin-secondary-btn" @click="resetForm">
          {{ isEditing ? t('adminStaffPage.actions.cancelEdit') : t('adminStaffPage.actions.resetForm') }}
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
            <span class="admin-panel__eyebrow">{{ t('adminStaffPage.notes.eyebrow') }}</span>
            <h2>{{ t('adminStaffPage.notes.title') }}</h2>
          </div>
        </div>

        <div class="admin-question-preview__notes">
          <article class="admin-question-preview__note">
            <span>{{ t('adminStaffPage.notes.sharedWorkspaceLabel') }}</span>
            <strong>{{ t('adminStaffPage.notes.sharedWorkspaceValue') }}</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>{{ t('adminStaffPage.notes.roleLabel') }}</span>
            <strong>{{ t('adminStaffPage.notes.roleValue') }}</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>{{ t('adminStaffPage.notes.directPermissionsLabel') }}</span>
            <strong>{{ t('adminStaffPage.notes.directPermissionsValue') }}</strong>
          </article>
        </div>

        <div class="admin-pill-list">
          <span class="admin-chip admin-chip--violet">{{ t('adminStaffPage.chips.sharedDashboard') }}</span>
          <span class="admin-chip admin-chip--blue">{{ t('adminStaffPage.chips.visibilityPermissions') }}</span>
          <span class="admin-chip admin-chip--emerald">{{ t('adminStaffPage.chips.inactiveCannotOperate') }}</span>
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
