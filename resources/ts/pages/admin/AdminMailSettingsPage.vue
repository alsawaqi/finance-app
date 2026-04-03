<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getMailboxSettings,
  listStaffMailboxUsers,
  saveMailboxSettings,
  testMailboxSettings,
  type MailboxSettings,
  type StaffMailboxDirectoryItem,
} from '@/services/mailSettings'

const { t } = useI18n()

const loading = ref(true)
const directoryLoading = ref(true)
const saving = ref(false)
const testing = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const settings = ref<MailboxSettings | null>(null)
const staffUsers = ref<StaffMailboxDirectoryItem[]>([])
const selectedStaffId = ref<number | null>(null)
const selectedStaff = ref<StaffMailboxDirectoryItem | null>(null)
const form = ref({
  smtp_username: '',
  smtp_sender_name: '',
  smtp_password: '',
  remove_smtp_password: false,
})

const stats = computed(() => {
  const verified = settings.value?.smtp_enabled && settings.value?.smtp_verified_at
  return [
    { label: 'Selected mailbox', value: settings.value?.sender_email || settings.value?.default_sender_email || '—', tone: 'blue' },
    { label: t('mailSettingsPage.stats.status'), value: verified ? t('mailSettingsPage.status.verified') : t('mailSettingsPage.status.pending'), tone: verified ? 'emerald' : 'amber' },
    { label: t('mailSettingsPage.stats.password'), value: settings.value?.has_smtp_password ? t('mailSettingsPage.status.saved') : t('mailSettingsPage.status.missing'), tone: settings.value?.has_smtp_password ? 'violet' : 'rose' },
    { label: t('mailSettingsPage.stats.transport'), value: `${settings.value?.smtp_host || '—'}:${settings.value?.smtp_port || '—'}`, tone: 'slate' },
  ]
})

const activeMailboxCount = computed(() => staffUsers.value.filter((item) => item.mailbox_settings?.smtp_enabled).length)

onMounted(async () => {
  await fetchDirectory()
})

watch(selectedStaffId, async (value, previous) => {
  if (!value || value === previous) return
  await fetchSettings(value)
})

function firstError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

function clearMessages() {
  errorMessage.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
}

async function fetchDirectory() {
  directoryLoading.value = true
  clearMessages()

  try {
    const { staff } = await listStaffMailboxUsers()
    staffUsers.value = staff ?? []

    if (!staffUsers.value.length) {
      selectedStaffId.value = null
      selectedStaff.value = null
      settings.value = null
      return
    }

    if (!selectedStaffId.value || !staffUsers.value.some((item) => item.id === selectedStaffId.value)) {
      selectedStaffId.value = staffUsers.value[0].id
    }

    await fetchSettings(selectedStaffId.value)
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, t('mailSettingsPage.errors.loadFailed'))
  } finally {
    directoryLoading.value = false
  }
}

async function fetchSettings(staffUserId?: number | null) {
  if (!staffUserId) return

  loading.value = true
  clearMessages()

  try {
    const { settings: result, staff_user } = await getMailboxSettings(staffUserId)
    settings.value = result
    selectedStaff.value = staff_user
    syncDirectoryRecord(staff_user)
    hydrateForm(result)
  } catch (error) {
    errorMessage.value = extractErrorMessage(error, t('mailSettingsPage.errors.loadFailed'))
  } finally {
    loading.value = false
  }
}

function hydrateForm(result: MailboxSettings) {
  form.value.smtp_username = result.smtp_username || result.default_sender_email || ''
  form.value.smtp_sender_name = result.sender_name || selectedStaff.value?.name || ''
  form.value.smtp_password = ''
  form.value.remove_smtp_password = false
}

function syncDirectoryRecord(staffUser?: StaffMailboxDirectoryItem | null) {
  if (!staffUser) return
  const index = staffUsers.value.findIndex((item) => item.id === staffUser.id)
  if (index >= 0) {
    staffUsers.value[index] = staffUser
  }
}

async function saveSettings() {
  if (!selectedStaffId.value) return

  saving.value = true
  clearMessages()

  try {
    const payload = {
      smtp_username: form.value.smtp_username.trim() || null,
      smtp_sender_name: form.value.smtp_sender_name.trim() || null,
      smtp_password: form.value.smtp_password.trim() || null,
      remove_smtp_password: form.value.remove_smtp_password,
    }
    const { message, settings: result, staff_user } = await saveMailboxSettings(selectedStaffId.value, payload)
    settings.value = result
    selectedStaff.value = staff_user
    syncDirectoryRecord(staff_user)
    hydrateForm(result)
    successMessage.value = message
  } catch (error) {
    if (axios.isAxiosError(error)) {
      errorMessage.value = error.response?.data?.message ?? t('mailSettingsPage.errors.saveFailed')
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      errorMessage.value = t('mailSettingsPage.errors.saveFailed')
    }
  } finally {
    saving.value = false
  }
}

async function runMailboxTest() {
  if (!selectedStaffId.value) return

  testing.value = true
  clearMessages()

  try {
    const { message, settings: result, staff_user } = await testMailboxSettings(selectedStaffId.value)
    settings.value = result
    selectedStaff.value = staff_user
    syncDirectoryRecord(staff_user)
    hydrateForm(result)
    successMessage.value = message
  } catch (error) {
    if (axios.isAxiosError(error)) {
      errorMessage.value = error.response?.data?.message ?? t('mailSettingsPage.errors.testFailed')
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      errorMessage.value = t('mailSettingsPage.errors.testFailed')
    }
  } finally {
    testing.value = false
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
        <span class="admin-hero__eyebrow">Admin managed mailboxes</span>
        <h2>{{ t('mailSettingsPage.hero.title') }}</h2>
        <p>Configure Hostinger mailbox credentials for staff accounts here. Staff users can only send request emails after their mailbox is saved and tested by the admin.</p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="saving || !selectedStaffId" @click="saveSettings">
          {{ saving ? t('mailSettingsPage.actions.saving') : t('mailSettingsPage.actions.save') }}
        </button>
        <button type="button" class="admin-secondary-btn" :disabled="testing || loading || !selectedStaffId" @click="runMailboxTest">
          {{ testing ? t('mailSettingsPage.actions.testing') : t('mailSettingsPage.actions.test') }}
        </button>
      </div>
    </section>

    <div class="admin-question-stats-grid admin-reveal-up admin-reveal-delay-1">
      <article v-for="stat in stats" :key="stat.label" class="admin-question-stat" :class="`tone-${stat.tone}`">
        <strong>{{ stat.value }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </div>

    <div v-if="errorMessage" class="admin-alert admin-alert--error">{{ errorMessage }}</div>
    <div v-if="successMessage" class="admin-alert admin-alert--success">{{ successMessage }}</div>

    <div class="admin-question-builder-grid">
      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">Staff mailbox directory</span>
            <h2>Select the staff mailbox to configure</h2>
          </div>
        </div>

        <div v-if="directoryLoading" class="empty-state">Loading staff mailbox directory…</div>
        <template v-else>
          <label class="admin-field admin-field--full">
            <span>Staff account</span>
            <select v-model="selectedStaffId" class="admin-select">
              <option v-for="staff in staffUsers" :key="staff.id" :value="staff.id">
                {{ staff.name }} · {{ staff.email }}
              </option>
            </select>
            <small class="admin-helper-text">Configured and verified mailboxes: {{ activeMailboxCount }} / {{ staffUsers.length }}</small>
          </label>

          <div v-if="selectedStaff" class="summary-grid summary-grid--tight" style="margin-top: 1rem;">
            <div><span>Staff name</span><strong>{{ selectedStaff.name }}</strong></div>
            <div><span>Login email</span><strong>{{ selectedStaff.email }}</strong></div>
            <div><span>Account status</span><strong>{{ selectedStaff.is_active ? 'Active' : 'Inactive' }}</strong></div>
            <div><span>Mailbox state</span><strong>{{ selectedStaff.mailbox_settings?.smtp_enabled ? 'Verified' : 'Pending' }}</strong></div>
          </div>
        </template>
      </section>

      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">Mailbox credentials</span>
            <h2>{{ t('mailSettingsPage.form.title') }}</h2>
          </div>
        </div>

        <div v-if="loading" class="empty-state">{{ t('mailSettingsPage.states.loading') }}</div>
        <div v-else-if="!selectedStaffId" class="empty-state">Choose a staff account first.</div>
        <div v-else class="admin-form-grid">
          <label class="admin-field admin-field--full">
            <span>{{ t('mailSettingsPage.form.loginEmail') }}</span>
            <input class="admin-input" :value="settings?.default_sender_email || selectedStaff?.email || ''" type="text" readonly>
            <small class="admin-helper-text">This is the staff login email stored on the user record.</small>
          </label>

          <label class="admin-field admin-field--full">
            <span>{{ t('mailSettingsPage.form.smtpUsername') }}</span>
            <input v-model="form.smtp_username" class="admin-input" type="email" :placeholder="settings?.default_sender_email || selectedStaff?.email || 'staff@domain.com'">
            <small v-if="firstError('smtp_username')" class="admin-field__error">{{ firstError('smtp_username') }}</small>
            <small class="admin-helper-text">Save the full Hostinger mailbox email used for SMTP login.</small>
          </label>

          <label class="admin-field admin-field--full">
            <span>{{ t('mailSettingsPage.form.senderName') }}</span>
            <input v-model="form.smtp_sender_name" class="admin-input" type="text" :placeholder="selectedStaff?.name || 'Finance Staff'">
            <small v-if="firstError('smtp_sender_name')" class="admin-field__error">{{ firstError('smtp_sender_name') }}</small>
          </label>

          <label class="admin-field admin-field--full">
            <span>{{ t('mailSettingsPage.form.smtpPassword') }}</span>
            <input v-model="form.smtp_password" class="admin-input" type="password" :placeholder="t('mailSettingsPage.form.smtpPasswordPlaceholder')">
            <small v-if="firstError('smtp_password')" class="admin-field__error">{{ firstError('smtp_password') }}</small>
            <small class="admin-helper-text">This password is stored encrypted and is only used for request email sending.</small>
          </label>

          <label class="admin-checkbox-field admin-field--full">
            <input v-model="form.remove_smtp_password" type="checkbox">
            <span>{{ t('mailSettingsPage.form.removeSavedPassword') }}</span>
          </label>
        </div>
      </section>

      <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">Selected staff summary</span>
            <h2>{{ t('mailSettingsPage.summary.title') }}</h2>
          </div>
        </div>

        <div class="admin-info-stack">
          <div class="admin-info-row">
            <strong>{{ t('mailSettingsPage.summary.senderEmail') }}</strong>
            <span>{{ settings?.sender_email || settings?.default_sender_email || '—' }}</span>
          </div>
          <div class="admin-info-row">
            <strong>{{ t('mailSettingsPage.summary.senderName') }}</strong>
            <span>{{ settings?.sender_name || selectedStaff?.name || '—' }}</span>
          </div>
          <div class="admin-info-row">
            <strong>{{ t('mailSettingsPage.summary.transport') }}</strong>
            <span>{{ settings?.smtp_host || '—' }}:{{ settings?.smtp_port || '—' }}<template v-if="settings?.smtp_encryption"> · {{ settings.smtp_encryption }}</template></span>
          </div>
          <div class="admin-info-row">
            <strong>{{ t('mailSettingsPage.summary.verifiedAt') }}</strong>
            <span>{{ settings?.smtp_verified_at ? new Date(settings.smtp_verified_at).toLocaleString() : t('mailSettingsPage.status.notVerified') }}</span>
          </div>
        </div>

        <div class="notes-box" style="margin-top: 1rem;">
          <span>Admin-only reminder</span>
          <p>Save the mailbox details first, then run the mailbox test. Staff will only see whether their mailbox is ready when they open the request email composer.</p>
        </div>

        <div v-if="settings?.smtp_last_error" class="admin-alert admin-alert--warning" style="margin-top: 1rem;">
          {{ settings.smtp_last_error }}
        </div>
      </section>
    </div>
  </div>
</template>
