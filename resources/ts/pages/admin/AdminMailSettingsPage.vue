<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDateTime } from '@/utils/dateTime'
import {
  getMailboxSettings,
  listStaffMailboxUsers,
  saveMailboxSettings,
  testMailboxSettings,
  type MailboxSettings,
  type StaffMailboxDirectoryItem,
} from '@/services/mailSettings'

const { t, locale } = useI18n()

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

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
    { label: uiText('Selected mailbox', 'البريد المحدد'), value: settings.value?.sender_email || settings.value?.default_sender_email || '—', tone: 'blue' },
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
        <span class="admin-hero__eyebrow">{{ uiText('Admin managed mailboxes', 'صناديق بريد تُدار من الإدارة') }}</span>
        <h2>{{ t('mailSettingsPage.hero.title') }}</h2>
        <p>{{ uiText('Configure Hostinger mailbox credentials for staff accounts here. Staff users can only send request emails after their mailbox is saved and tested by the admin.', 'قم بإعداد بيانات بريد Hostinger للموظفين من هنا. لا يمكن للموظف إرسال رسائل الطلب إلا بعد حفظ البريد واختباره من الإدارة.') }}</p>
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

    <div class="admin-question-stats-grid admin-question-stats-grid--balanced admin-reveal-up admin-reveal-delay-1">
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
            <span class="admin-panel__eyebrow">{{ uiText('Staff mailbox directory', 'دليل بريد الموظفين') }}</span>
            <h2>{{ uiText('Select the staff mailbox to configure', 'اختر بريد الموظف لإعداده') }}</h2>
          </div>
        </div>

        <div v-if="directoryLoading" class="empty-state">{{ uiText('Loading staff mailbox directory…', 'جارٍ تحميل دليل بريد الموظفين…') }}</div>
        <template v-else>
          <label class="admin-field admin-field--full">
            <span>{{ uiText('Staff account', 'حساب الموظف') }}</span>
            <select v-model="selectedStaffId" class="admin-select">
              <option v-for="staff in staffUsers" :key="staff.id" :value="staff.id">
                {{ staff.name }} · {{ staff.email }}
              </option>
            </select>
            <small class="admin-helper-text">{{ uiText('Configured and verified mailboxes', 'صناديق البريد المُعدة والمُوثقة') }}: {{ activeMailboxCount }} / {{ staffUsers.length }}</small>
          </label>

          <div v-if="selectedStaff" class="summary-grid summary-grid--tight admin-mail-summary-grid">
            <div><span>{{ uiText('Staff name', 'اسم الموظف') }}</span><strong>{{ selectedStaff.name }}</strong></div>
            <div><span>{{ uiText('Login email', 'بريد تسجيل الدخول') }}</span><strong>{{ selectedStaff.email }}</strong></div>
            <div><span>{{ uiText('Account status', 'حالة الحساب') }}</span><strong>{{ selectedStaff.is_active ? uiText('Active', 'نشط') : uiText('Inactive', 'غير نشط') }}</strong></div>
            <div><span>{{ uiText('Mailbox state', 'حالة البريد') }}</span><strong>{{ selectedStaff.mailbox_settings?.smtp_enabled ? uiText('Verified', 'موثّق') : uiText('Pending', 'قيد الانتظار') }}</strong></div>
          </div>
        </template>
      </section>

      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ uiText('Mailbox credentials', 'بيانات اعتماد البريد') }}</span>
            <h2>{{ t('mailSettingsPage.form.title') }}</h2>
          </div>
        </div>

        <div v-if="loading" class="empty-state">{{ t('mailSettingsPage.states.loading') }}</div>
        <div v-else-if="!selectedStaffId" class="empty-state">{{ uiText('Choose a staff account first.', 'اختر حساب موظف أولاً.') }}</div>
        <div v-else class="admin-form-grid">
          <label class="admin-field admin-field--full">
            <span>{{ t('mailSettingsPage.form.loginEmail') }}</span>
            <input class="admin-input" :value="settings?.default_sender_email || selectedStaff?.email || ''" type="text" readonly>
            <small class="admin-helper-text">{{ uiText('This is the staff login email stored on the user record.', 'هذا هو بريد تسجيل دخول الموظف المحفوظ في سجل المستخدم.') }}</small>
          </label>

          <label class="admin-field admin-field--full">
            <span>{{ t('mailSettingsPage.form.smtpUsername') }}</span>
            <input v-model="form.smtp_username" class="admin-input" type="email" :placeholder="settings?.default_sender_email || selectedStaff?.email || 'staff@domain.com'">
            <small v-if="firstError('smtp_username')" class="admin-field__error">{{ firstError('smtp_username') }}</small>
            <small class="admin-helper-text">{{ uiText('Save the full Hostinger mailbox email used for SMTP login.', 'احفظ عنوان بريد Hostinger الكامل المستخدم لتسجيل دخول SMTP.') }}</small>
          </label>

          <label class="admin-field admin-field--full">
            <span>{{ t('mailSettingsPage.form.senderName') }}</span>
            <input v-model="form.smtp_sender_name" class="admin-input" type="text" :placeholder="selectedStaff?.name || uiText('Finance Staff', 'موظف التمويل')">
            <small v-if="firstError('smtp_sender_name')" class="admin-field__error">{{ firstError('smtp_sender_name') }}</small>
          </label>

          <label class="admin-field admin-field--full">
            <span>{{ t('mailSettingsPage.form.smtpPassword') }}</span>
            <input v-model="form.smtp_password" class="admin-input" type="password" :placeholder="t('mailSettingsPage.form.smtpPasswordPlaceholder')">
            <small v-if="firstError('smtp_password')" class="admin-field__error">{{ firstError('smtp_password') }}</small>
            <small class="admin-helper-text">{{ uiText('This password is stored encrypted and is only used for request email sending.', 'يتم حفظ كلمة المرور هذه بشكل مشفر وتُستخدم فقط لإرسال رسائل الطلب.') }}</small>
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
            <span class="admin-panel__eyebrow">{{ uiText('Selected staff summary', 'ملخص الموظف المحدد') }}</span>
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
            <span>{{ formatDateTime(settings?.smtp_verified_at, locale, t('mailSettingsPage.status.notVerified')) }}</span>
          </div>
        </div>

        <div class="notes-box">
          <span>{{ uiText('Admin-only reminder', 'تذكير خاص بالإدارة') }}</span>
          <p>{{ uiText('Save the mailbox details first, then run the mailbox test. Staff will only see whether their mailbox is ready when they open the request email composer.', 'احفظ إعدادات البريد أولاً ثم شغّل اختبار البريد. سيرى الموظف فقط ما إذا كان بريده جاهزًا عند فتح مُحرر إرسال رسائل الطلب.') }}</p>
        </div>

        <div v-if="settings?.smtp_last_error" class="admin-alert admin-alert--warning admin-mail-warning">
          {{ settings.smtp_last_error }}
        </div>
      </section>
    </div>
  </div>
</template>
