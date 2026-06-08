<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'
import {
  createRequestEmailTemplate,
  listRequestEmailTemplates,
  toggleRequestEmailTemplateActive,
  updateRequestEmailTemplate,
  type RequestEmailTemplateField,
  type RequestEmailTemplateFieldType,
  type RequestEmailTemplateItem,
  type RequestEmailTemplatePayload,
} from '@/services/requestEmailTemplates'

type TemplateForm = {
  id: number | null
  name: string
  code: string
  subject: string
  body: string
  fields_json: RequestEmailTemplateField[]
  sort_order: number
  is_active: boolean
}

const { locale } = useI18n()
const fieldTypes = computed<Array<{ value: RequestEmailTemplateFieldType; label: string }>>(() => [
  { value: 'text', label: fieldTypeLabel('text') },
  { value: 'textarea', label: fieldTypeLabel('textarea') },
  { value: 'number', label: fieldTypeLabel('number') },
  { value: 'date', label: fieldTypeLabel('date') },
  { value: 'email', label: fieldTypeLabel('email') },
  { value: 'phone', label: fieldTypeLabel('phone') },
])

const templates = ref<RequestEmailTemplateItem[]>([])
const pagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 20 })
const isLoading = ref(false)
const isSaving = ref(false)
const formError = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const form = ref<TemplateForm>(createDefaultForm())
const subjectInputRef = ref<HTMLInputElement | null>(null)
const bodyTextareaRef = ref<HTMLTextAreaElement | null>(null)
let generatedFieldCounter = 0

const isEditing = computed(() => form.value.id !== null)
const activeCount = computed(() => templates.value.filter((template) => template.is_active).length)
const totalFields = computed(() => templates.value.reduce((sum, template) => sum + (template.fields_json?.length ?? 0), 0))
const usableFields = computed(() => form.value.fields_json.filter((field) => field.key.trim() || field.label.trim()))
const previewBody = computed(() => renderTemplateBody(serializeTemplateContent(form.value.body), previewValues.value))
const previewSubject = computed(() => renderTemplateSubject(serializeTemplateContent(form.value.subject), previewValues.value))
const previewBodyFallback = computed(() => `<p>${escapeHtml(uiText('Body preview appears here.', 'ستظهر معاينة نص البريد هنا.'))}</p>`)
const previewValues = computed(() => {
  const values: Record<string, string> = {}
  form.value.fields_json.forEach((field) => {
    values[field.key] = field.placeholder || field.label || field.key
  })

  return values
})

onMounted(async () => {
  await fetchTemplates()
})

function createDefaultForm(): TemplateForm {
  return {
    id: null,
    name: '',
    code: '',
    subject: '',
    body: '<p></p>',
    fields_json: [],
    sort_order: templates.value.length + 1,
    is_active: true,
  }
}

function clearMessages() {
  formError.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
}

function uiText(en: string, ar: string) {
  return String(locale.value || '').startsWith('ar') ? ar : en
}

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

async function fetchTemplates(page = pagination.value.current_page) {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listRequestEmailTemplates({
      page,
      per_page: pagination.value.per_page,
    })
    templates.value = data.data
    pagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: pagination.value.per_page }

    if (!isEditing.value) {
      form.value.sort_order = Math.max(pagination.value.total, templates.value.length) + 1
    }
  } catch (error) {
    formError.value = extractErrorMessage(error, uiText('Unable to load request email templates.', 'تعذر تحميل قوالب البريد.'))
  } finally {
    isLoading.value = false
  }
}

function resetForm() {
  clearMessages()
  form.value = createDefaultForm()
}

function addField() {
  form.value.fields_json.push({
    key: nextFieldKey(),
    label: '',
    type: 'text',
    required: true,
    placeholder: '',
    help_text: '',
  })
}

function removeField(index: number) {
  form.value.fields_json.splice(index, 1)
}

function nextFieldKey() {
  const usedKeys = new Set(form.value.fields_json.map((field) => field.key).filter(Boolean))

  for (let attempt = 0; attempt < 1000; attempt++) {
    const key = `field_${Date.now()}_${++generatedFieldCounter}`
    if (!usedKeys.has(key)) return key
  }

  return `field_${Date.now()}_${Math.round(Math.random() * 100000)}`
}

function tokenFor(field: RequestEmailTemplateField) {
  return field.key ? `{{${field.key}}}` : '{{field_key}}'
}

function friendlyPlaceholderFor(field: RequestEmailTemplateField) {
  const index = form.value.fields_json.indexOf(field)
  const label = fieldDisplayLabel(field, index >= 0 ? index : 0)
    .replace(/[\[\]]/g, '')
    .trim()

  return `[[${label || uiText('Staff input', 'مدخل الموظف')}]]`
}

function canUseField(field: RequestEmailTemplateField) {
  return field.key.trim() !== '' && field.label.trim() !== ''
}

function fieldDisplayLabel(field: RequestEmailTemplateField, index: number) {
  return field.label.trim() || uiText(`Staff input ${index + 1}`, `مدخل الموظف ${index + 1}`)
}

function validatedTokenFor(field: RequestEmailTemplateField) {
  if (canUseField(field)) {
    return friendlyPlaceholderFor(field)
  }

  formError.value = uiText('Add a field label before using it in the template.', 'أضف اسماً للحقل قبل استخدامه داخل القالب.')

  return ''
}

function appendTokenToSubject(field: RequestEmailTemplateField) {
  const token = validatedTokenFor(field)
  if (!token) return

  insertTokenInSubject(token)
}

function appendTokenToBody(field: RequestEmailTemplateField) {
  const token = validatedTokenFor(field)
  if (!token) return

  insertTokenInBody(token)
}

function insertTokenInSubject(token: string) {
  insertAtControlCursor(subjectInputRef.value, 'subject', token)
}

function insertTokenInBody(token: string) {
  insertAtControlCursor(bodyTextareaRef.value, 'body', token)
}

function insertAtControlCursor(input: HTMLInputElement | HTMLTextAreaElement | null, target: 'subject' | 'body', marker: string) {
  if (input && typeof input.selectionStart === 'number' && typeof input.selectionEnd === 'number') {
    const current = form.value[target]
    const start = input.selectionStart
    const end = input.selectionEnd
    const before = current.slice(0, start)
    const after = current.slice(end)
    const prefix = before && !/\s$/.test(before) ? ' ' : ''
    const suffix = after && !/^\s/.test(after) ? ' ' : ''
    const inserted = `${prefix}${marker}${suffix}`
    form.value[target] = `${before}${inserted}${after}`

    void nextTick(() => {
      const caret = before.length + inserted.length
      input.focus()
      input.setSelectionRange(caret, caret)
    })

    return
  }

  form.value[target] = `${form.value[target].trim()} ${marker}`.trim()
}

function buildPayload(): RequestEmailTemplatePayload {
  const subject = serializeTemplateContent(form.value.subject.trim())
  const body = plainTemplateToHtml(serializeTemplateContent(form.value.body.trim()))

  return {
    name: form.value.name.trim(),
    code: form.value.code.trim() || null,
    subject,
    body,
    fields_json: form.value.fields_json
      .map((field) => ({
        key: field.key.trim(),
        label: field.label.trim(),
        type: field.type,
        required: Boolean(field.required),
        placeholder: field.placeholder?.trim() || null,
        help_text: field.help_text?.trim() || null,
      }))
      .filter((field) => field.key || field.label),
    sort_order: form.value.sort_order,
    is_active: form.value.is_active,
  }
}

async function saveTemplate() {
  clearMessages()
  isSaving.value = true

  try {
    const payload = buildPayload()

    if (hasUnresolvedFriendlyMarkers(payload.subject) || hasUnresolvedFriendlyMarkers(payload.body)) {
      formError.value = uiText(
        'One staff input marker is no longer linked. Remove it and use the button again.',
        'يوجد مدخل موظف غير مرتبط. احذفه واستخدم الزر مرة أخرى.',
      )

      return
    }

    if (hasUnknownManualMarkers(payload.subject) || hasUnknownManualMarkers(payload.body)) {
      formError.value = uiText(
        'One typed bracket field does not match a staff input. Use the button, or type the exact staff input name inside brackets.',
        'يوجد حقل مكتوب بالأقواس لا يطابق مدخل موظف. استخدم الزر أو اكتب اسم مدخل الموظف كما هو داخل الأقواس.',
      )

      return
    }

    if (isEditing.value && form.value.id) {
      await updateRequestEmailTemplate(form.value.id, payload)
      successMessage.value = uiText('Request email template updated successfully.', 'تم تحديث قالب البريد بنجاح.')
    } else {
      await createRequestEmailTemplate(payload)
      successMessage.value = uiText('Request email template created successfully.', 'تم إنشاء قالب البريد بنجاح.')
    }

    await fetchTemplates()
    resetForm()
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? uiText('Unable to save the request email template.', 'تعذر حفظ قالب البريد.')
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = uiText('Unable to save the request email template.', 'تعذر حفظ قالب البريد.')
    }
  } finally {
    isSaving.value = false
  }
}

function editTemplate(template: RequestEmailTemplateItem) {
  clearMessages()
  const fields = (template.fields_json ?? []).map((field) => ({
    key: field.key,
    label: field.label,
    type: field.type,
    required: Boolean(field.required),
    placeholder: field.placeholder ?? '',
    help_text: field.help_text ?? '',
  }))

  form.value = {
    id: template.id,
    name: template.name,
    code: template.code ?? '',
    subject: displayTemplateContent(template.subject, fields),
    body: displayTemplateContent(htmlToPlainText(template.body), fields),
    fields_json: fields,
    sort_order: template.sort_order,
    is_active: template.is_active,
  }

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function toggleTemplate(template: RequestEmailTemplateItem) {
  clearMessages()

  try {
    const { data } = await toggleRequestEmailTemplateActive(template.id)
    templates.value = templates.value.map((item) => (item.id === template.id ? data.data : item))
    successMessage.value = data.data?.is_active
      ? uiText('Request email template activated successfully.', 'تم تفعيل قالب البريد بنجاح.')
      : uiText('Request email template deactivated successfully.', 'تم إيقاف قالب البريد بنجاح.')
  } catch (error) {
    formError.value = extractErrorMessage(error, uiText('Unable to update the template status.', 'تعذر تحديث حالة القالب.'))
  }
}

function fieldTypeLabel(type: RequestEmailTemplateFieldType) {
  const labels: Record<RequestEmailTemplateFieldType, string> = {
    text: uiText('Text', 'نص'),
    textarea: uiText('Long text', 'نص طويل'),
    number: uiText('Number', 'رقم'),
    date: uiText('Date', 'تاريخ'),
    email: uiText('Email', 'بريد إلكتروني'),
    phone: uiText('Phone', 'هاتف'),
  }

  return labels[type] ?? type
}

function renderTemplateSubject(subject: string, values: Record<string, string>) {
  return replaceTokens(subject, values, false).replace(/\s+/g, ' ').trim()
}

function renderTemplateBody(body: string, values: Record<string, string>) {
  return renderPlainTemplateAsHtml(body || '', values)
}

function replaceTokens(template: string, values: Record<string, string>, html: boolean) {
  return String(template || '').replace(/{{\s*([A-Za-z][A-Za-z0-9_]*)\s*}}/g, (_match, key: string) => {
    const value = values[key] ?? ''
    return html ? escapeHtml(value).replace(/\n/g, '<br>') : value.replace(/\s+/g, ' ')
  })
}

function renderPlainTemplateAsHtml(template: string, values: Record<string, string>) {
  const escaped = escapeHtml(template || '')
  const rendered = escaped.replace(/{{\s*([A-Za-z][A-Za-z0-9_]*)\s*}}/g, (_match, key: string) => {
    return escapeHtml(values[key] ?? '').replace(/\r\n|\r|\n/g, '<br>')
  })

  return rendered.replace(/\r\n|\r|\n/g, '<br>')
}

function serializeTemplateContent(content: string) {
  let serialized = String(content || '')

  form.value.fields_json.forEach((field) => {
    if (!canUseField(field)) return
    serialized = replaceAllLiteral(serialized, friendlyPlaceholderFor(field), tokenFor(field))
    serialized = replaceLabelBrackets(serialized, field)
  })

  return serialized
}

function replaceLabelBrackets(content: string, field: RequestEmailTemplateField) {
  const label = field.label.trim()
  if (!label) return content

  return content.replace(new RegExp(`{{\\s*${escapeRegExp(label)}\\s*}}`, 'g'), tokenFor(field))
}

function plainTemplateToHtml(content: string) {
  return escapeHtml(content || '').replace(/\r\n|\r|\n/g, '<br>')
}

function htmlToPlainText(html: string) {
  const withLineBreaks = String(html || '')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>\s*<p[^>]*>/gi, '\n\n')
    .replace(/<\/div>\s*<div[^>]*>/gi, '\n')

  const parser = new DOMParser()
  const document = parser.parseFromString(withLineBreaks, 'text/html')

  return (document.body.textContent || '').replace(/\u00a0/g, ' ').trim()
}

function displayTemplateContent(content: string, fields: RequestEmailTemplateField[]) {
  let displayed = String(content || '')

  fields.forEach((field, index) => {
    const key = field.key?.trim()
    if (!key) return
    const marker = `[[${(field.label?.trim() || uiText(`Staff input ${index + 1}`, `مدخل الموظف ${index + 1}`)).replace(/[\[\]]/g, '')}]]`
    displayed = displayed.replace(new RegExp(`{{\\s*${escapeRegExp(key)}\\s*}}`, 'g'), marker)
  })

  return displayed
}

function replaceAllLiteral(content: string, search: string, replacement: string) {
  if (!search) return content

  return content.replace(new RegExp(escapeRegExp(search), 'g'), replacement)
}

function escapeRegExp(value: string) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function hasUnresolvedFriendlyMarkers(content: string) {
  return /\[\[[^\]]+\]\]/.test(content)
}

function hasUnknownManualMarkers(content: string) {
  const allowedKeys = new Set(form.value.fields_json.map((field) => field.key).filter(Boolean))
  let hasUnknown = false

  String(content || '').replace(/{{\s*([^}]+)\s*}}/g, (_match, key: string) => {
    if (!allowedKeys.has(key.trim())) {
      hasUnknown = true
    }

    return _match
  })

  return hasUnknown
}

function escapeHtml(value: string) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
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
        <span class="admin-hero__eyebrow">{{ uiText('Request Email Templates', 'قوالب بريد الطلبات') }}</span>
        <h2>{{ uiText('Create reusable email templates for assigned-agent communication.', 'أنشئ قوالب بريد قابلة لإعادة الاستخدام للتواصل مع الوكلاء المسندين.') }}</h2>
        <p>{{ uiText('Define the fixed message once, add staff inputs, and let staff fill only the allowed values before sending.', 'اكتب الرسالة الثابتة مرة واحدة، وأضف مدخلات الموظف، ثم دع الموظف يملأ القيم المسموح بها فقط قبل الإرسال.') }}</p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveTemplate">
          {{ isSaving ? uiText('Saving...', 'جارٍ الحفظ...') : isEditing ? uiText('Update template', 'تحديث القالب') : uiText('Save template', 'حفظ القالب') }}
        </button>
        <button type="button" class="admin-secondary-btn" @click="resetForm">
          {{ isEditing ? uiText('Cancel edit', 'إلغاء التعديل') : uiText('Reset form', 'إعادة ضبط النموذج') }}
        </button>
      </div>
    </section>

    <section class="admin-question-stats-grid admin-question-stats-grid--balanced admin-reveal-up admin-reveal-delay-1">
      <article class="admin-question-stat tone-violet">
        <strong>{{ pagination.total }}</strong>
        <span>{{ uiText('Total templates', 'إجمالي القوالب') }}</span>
      </article>
      <article class="admin-question-stat tone-emerald">
        <strong>{{ activeCount }}</strong>
        <span>{{ uiText('Active templates', 'القوالب النشطة') }}</span>
      </article>
      <article class="admin-question-stat tone-blue">
        <strong>{{ totalFields }}</strong>
        <span>{{ uiText('Staff inputs', 'مدخلات الموظف') }}</span>
      </article>
      <article class="admin-question-stat tone-amber">
        <strong>{{ isEditing ? uiText('Editing', 'تعديل') : uiText('New', 'جديد') }}</strong>
        <span>{{ uiText('Current mode', 'الوضع الحالي') }}</span>
      </article>
    </section>

    <div v-if="formError" class="admin-alert admin-alert--error">{{ formError }}</div>
    <div v-if="successMessage" class="admin-alert admin-alert--success">{{ successMessage }}</div>

    <section class="admin-question-builder-grid">
      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ uiText('Template builder', 'منشئ القوالب') }}</span>
            <h2>{{ isEditing ? uiText('Edit saved template', 'تعديل قالب محفوظ') : uiText('Create request email template', 'إنشاء قالب بريد للطلب') }}</h2>
          </div>
          <button type="button" class="admin-panel__action" @click="addField">{{ uiText('Add staff input', 'إضافة مدخل للموظف') }}</button>
        </div>

        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-form-field">
            <span>{{ uiText('Template name', 'اسم القالب') }}</span>
            <input v-model="form.name" type="text" class="admin-form-input" :class="{ 'has-error': firstFieldError('name') }" :placeholder="uiText('Welcome template', 'قالب الترحيب')">
            <small v-if="firstFieldError('name')" class="admin-form-error">{{ firstFieldError('name') }}</small>
          </label>

          <label class="admin-form-field admin-form-field--full">
            <span>{{ uiText('Subject', 'عنوان البريد') }}</span>
            <input
              ref="subjectInputRef"
              v-model="form.subject"
              type="text"
              class="admin-form-input"
              :class="{ 'has-error': firstFieldError('subject') }"
              :placeholder="uiText('Welcome message', 'رسالة ترحيب')"
            >
            <small v-if="firstFieldError('subject')" class="admin-form-error">{{ firstFieldError('subject') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ uiText('Display order', 'ترتيب العرض') }}</span>
            <input v-model.number="form.sort_order" type="number" min="0" class="admin-form-input" :class="{ 'has-error': firstFieldError('sort_order') }">
          </label>

          <label class="admin-switch-card">
            <input v-model="form.is_active" type="checkbox">
            <div>
              <strong>{{ uiText('Active', 'نشط') }}</strong>
              <span>{{ uiText('Active templates appear in the staff email composer.', 'القوالب النشطة تظهر للموظف في شاشة إنشاء البريد.') }}</span>
            </div>
          </label>

          <label class="admin-form-field admin-form-field--full">
            <span>{{ uiText('Email body', 'نص البريد') }}</span>
            <textarea
              ref="bodyTextareaRef"
              v-model="form.body"
              rows="12"
              class="admin-form-input template-body-textarea"
              :placeholder="uiText('Write the email message here. Place staff inputs with the buttons below.', 'اكتب نص البريد هنا. أضف مدخلات الموظف باستخدام الأزرار أدناه.')"
            ></textarea>
            <small v-if="firstFieldError('body')" class="admin-form-error">{{ firstFieldError('body') }}</small>
          </label>
        </div>

        <div class="template-fields">
          <div class="template-fields__head">
            <div>
              <span class="admin-panel__eyebrow">{{ uiText('Staff inputs', 'مدخلات الموظف') }}</span>
              <h3>{{ uiText('Information staff can fill', 'المعلومات التي يستطيع الموظف تعبئتها') }}</h3>
            </div>
            <button type="button" class="admin-secondary-btn" @click="addField">{{ uiText('Add staff input', 'إضافة مدخل للموظف') }}</button>
          </div>

          <div v-if="!form.fields_json.length" class="admin-table-empty">
            {{ uiText('Add a staff input when staff should fill a value inside the template.', 'أضف مدخلاً للموظف عندما تريد منه تعبئة قيمة داخل القالب.') }}
          </div>

          <div v-else class="template-slot-palette">
            <div class="template-slot-palette__head">
              <div>
                <span class="admin-panel__eyebrow">{{ uiText('Place inputs visually', 'وضع المدخلات بصرياً') }}</span>
                <h4>{{ uiText('Place the cursor where you want the input, then click a button. Manual bracket typing also works when the name matches a staff input.', 'ضع المؤشر في المكان المطلوب ثم اضغط على الزر. الكتابة اليدوية بالأقواس تعمل أيضاً عندما يطابق الاسم مدخل الموظف.') }}</h4>
              </div>
            </div>

            <div class="template-slot-list">
              <article
                v-for="(field, index) in usableFields"
                :key="`${field.key || field.label}-${index}`"
                class="template-slot-chip"
                :class="{ 'is-disabled': !canUseField(field) }"
              >
                <span class="template-slot-chip__icon" aria-hidden="true"><i class="fas fa-pen-to-square"></i></span>
                <div class="template-slot-chip__text">
                  <strong>{{ fieldDisplayLabel(field, index) }}</strong>
                  <small>
                    {{ fieldTypeLabel(field.type) }} · {{ field.required ? uiText('Required', 'إجباري') : uiText('Optional', 'اختياري') }}
                  </small>
                </div>
                <div class="template-slot-chip__actions">
                  <button type="button" class="admin-inline-link" :disabled="!canUseField(field)" @click="appendTokenToSubject(field)">
                    {{ uiText('Use in subject', 'استخدم في العنوان') }}
                  </button>
                  <button type="button" class="admin-inline-link" :disabled="!canUseField(field)" @click="appendTokenToBody(field)">
                    {{ uiText('Use in body', 'استخدم في النص') }}
                  </button>
                </div>
              </article>
            </div>
          </div>

          <article v-for="(field, index) in form.fields_json" :key="index" class="template-field-row">
            <div class="admin-form-grid admin-form-grid--2">
              <label class="admin-form-field">
                <span>{{ uiText('Staff input name', 'اسم مدخل الموظف') }}</span>
                <input v-model="field.label" type="text" class="admin-form-input" :placeholder="uiText('Customer name', 'اسم العميل')">
              </label>
              <label class="admin-form-field">
                <span>{{ uiText('Type', 'النوع') }}</span>
                <select v-model="field.type" class="admin-form-select">
                  <option v-for="type in fieldTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
              </label>
              <label class="admin-form-field">
                <span>{{ uiText('Placeholder', 'النص الإرشادي') }}</span>
                <input v-model="field.placeholder" type="text" class="admin-form-input" :placeholder="uiText('Value staff will enter', 'القيمة التي سيدخلها الموظف')">
              </label>
              <label class="admin-form-field admin-form-field--full">
                <span>{{ uiText('Help text', 'نص مساعد') }}</span>
                <input v-model="field.help_text" type="text" class="admin-form-input" :placeholder="uiText('Optional guidance for staff', 'إرشاد اختياري للموظف')">
              </label>
            </div>

            <div class="template-field-row__actions">
              <span class="admin-status-pill">{{ fieldDisplayLabel(field, index) }}</span>
              <button type="button" class="admin-inline-link" :disabled="!canUseField(field)" @click="appendTokenToSubject(field)">{{ uiText('Use in subject', 'استخدم في العنوان') }}</button>
              <button type="button" class="admin-inline-link" :disabled="!canUseField(field)" @click="appendTokenToBody(field)">{{ uiText('Use in body', 'استخدم في النص') }}</button>
              <label class="template-field-required">
                <input v-model="field.required" type="checkbox">
                {{ uiText('Required', 'إجباري') }}
              </label>
              <button type="button" class="admin-inline-link" @click="removeField(index)">{{ uiText('Remove', 'إزالة') }}</button>
            </div>
          </article>
        </div>

        <div class="admin-form-actions">
          <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveTemplate">
            {{ isSaving ? uiText('Saving...', 'جارٍ الحفظ...') : isEditing ? uiText('Update template', 'تحديث القالب') : uiText('Save template', 'حفظ القالب') }}
          </button>
          <button type="button" class="admin-secondary-btn" @click="resetForm">{{ isEditing ? uiText('Cancel edit', 'إلغاء التعديل') : uiText('Reset form', 'إعادة ضبط النموذج') }}</button>
        </div>
      </section>

      <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ uiText('Preview', 'المعاينة') }}</span>
            <h2>{{ uiText('Staff-filled message preview', 'معاينة الرسالة بعد تعبئة الموظف') }}</h2>
          </div>
        </div>

        <div class="template-preview">
          <span class="admin-panel__eyebrow">{{ uiText('Subject', 'عنوان البريد') }}</span>
          <strong>{{ previewSubject || uiText('Subject preview appears here', 'ستظهر معاينة عنوان البريد هنا') }}</strong>
          <span class="admin-panel__eyebrow">{{ uiText('Body', 'النص') }}</span>
          <div class="template-preview__body" v-html="previewBody || previewBodyFallback"></div>
        </div>

        <div class="admin-question-preview__notes">
          <article class="admin-question-preview__note">
            <span>{{ uiText('No coding needed', 'لا حاجة لأي كود') }}</span>
            <strong>{{ uiText('Use the buttons', 'استخدم الأزرار') }}</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>{{ uiText('Staff inputs', 'مدخلات الموظف') }}</span>
            <strong>{{ form.fields_json.length }}</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>{{ uiText('Status', 'الحالة') }}</span>
            <strong>{{ form.is_active ? uiText('Active', 'نشط') : uiText('Inactive', 'غير نشط') }}</strong>
          </article>
        </div>
      </section>
    </section>

    <section class="admin-panel admin-reveal-up">
      <div class="admin-panel__head">
        <div>
          <span class="admin-panel__eyebrow">{{ uiText('Template library', 'مكتبة القوالب') }}</span>
          <h2>{{ uiText('Saved request email templates', 'قوالب بريد الطلبات المحفوظة') }}</h2>
        </div>
        <span class="admin-panel__action is-static">{{ pagination.total }}</span>
      </div>

      <div v-if="isLoading" class="admin-table-empty">{{ uiText('Loading request email templates...', 'جارٍ تحميل قوالب البريد...') }}</div>

      <template v-else>
        <div v-if="!templates.length" class="admin-table-empty">{{ uiText('No request email templates have been created yet.', 'لم يتم إنشاء أي قوالب بريد للطلبات بعد.') }}</div>

        <div v-else class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>{{ uiText('Name', 'الاسم') }}</th>
                <th>{{ uiText('Subject', 'عنوان البريد') }}</th>
                <th>{{ uiText('Staff inputs', 'مدخلات الموظف') }}</th>
                <th>{{ uiText('Status', 'الحالة') }}</th>
                <th>{{ uiText('Actions', 'الإجراءات') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="template in templates" :key="template.id" class="is-clickable-row" @click="editTemplate(template)">
                <td>
                  <strong>{{ template.name }}</strong>
                </td>
                <td>{{ template.subject }}</td>
                <td>
                  <span class="admin-status-pill">{{ template.fields_json.length }} {{ uiText('inputs', 'مدخلات') }}</span>
                  <span v-if="template.fields_json.length" class="client-subtext">
                    {{ template.fields_json.map((field) => `${field.label} (${fieldTypeLabel(field.type)})`).join(', ') }}
                  </span>
                </td>
                <td>
                  <span class="admin-status-pill" :class="template.is_active ? 'is-success' : 'is-muted'">
                    {{ template.is_active ? uiText('Active', 'نشط') : uiText('Inactive', 'غير نشط') }}
                  </span>
                </td>
                <td @click.stop>
                  <div class="admin-table-actions">
                    <button type="button" class="admin-inline-link" @click="editTemplate(template)">{{ uiText('Edit', 'تعديل') }}</button>
                    <button type="button" class="admin-inline-link" @click="toggleTemplate(template)">
                      {{ template.is_active ? uiText('Deactivate', 'إيقاف') : uiText('Activate', 'تفعيل') }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <AppPagination :pagination="pagination" :disabled="isLoading" @change="fetchTemplates" />
      </template>
    </section>
  </div>
</template>

<style scoped>
.template-body-textarea {
  min-height: 260px;
  resize: vertical;
  line-height: 1.7;
}

.template-fields {
  display: grid;
  gap: 0.9rem;
  margin-top: 1.2rem;
}

.template-slot-palette {
  display: grid;
  gap: 0.8rem;
  padding: 1rem;
  border: 1px dashed rgba(37, 99, 235, 0.32);
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(239, 246, 255, 0.86), rgba(255, 255, 255, 0.88));
}

.template-slot-palette__head h4 {
  margin: 0.25rem 0 0;
  color: var(--admin-text);
}

.template-slot-list {
  display: grid;
  gap: 0.65rem;
}

.template-slot-chip {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid rgba(148, 163, 184, 0.3);
  border-radius: 12px;
  background: #ffffff;
}

.template-slot-chip.is-disabled {
  cursor: not-allowed;
  opacity: 0.62;
}

.template-slot-chip__icon {
  width: 2rem;
  height: 2rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: rgba(37, 99, 235, 0.1);
  color: #1d4ed8;
}

.template-slot-chip__text {
  min-width: 0;
  display: grid;
  gap: 0.15rem;
}

.template-slot-chip__text strong,
.template-slot-chip__text small {
  overflow-wrap: anywhere;
}

.template-slot-chip__text small {
  color: var(--admin-text-light);
  font-weight: 700;
}

.template-slot-chip__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.45rem;
}

.template-fields__head,
.template-field-row__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.65rem;
}

.template-fields__head h3 {
  margin: 0.2rem 0 0;
}

.template-field-row {
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 14px;
  padding: 1rem;
  background: rgba(255, 255, 255, 0.72);
}

.template-field-row__actions {
  justify-content: flex-start;
  margin-top: 0.8rem;
}

.template-field-required {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--admin-text-light);
  font-weight: 700;
}

.admin-inline-link:disabled {
  cursor: not-allowed;
  opacity: 0.48;
}

.template-preview {
  display: grid;
  gap: 0.75rem;
}

.template-preview > strong {
  display: block;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  background: rgba(248, 250, 252, 0.9);
  color: var(--admin-text);
}

.template-preview__body {
  min-height: 220px;
  padding: 1rem;
  border-radius: 14px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  background: #ffffff;
  color: var(--admin-text);
  line-height: 1.65;
}

@media (max-width: 720px) {
  .template-slot-chip {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .template-slot-chip__actions {
    grid-column: 1 / -1;
    justify-content: flex-start;
  }
}
</style>
