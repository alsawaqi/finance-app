<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  type AppNotificationItem,
} from '@/services/notifications'

const props = withDefaults(defineProps<{
  theme?: 'admin' | 'client'
}>(), {
  theme: 'admin',
})

const { t, locale } = useI18n()
const router = useRouter()

const root = ref<HTMLElement | null>(null)
const open = ref(false)
const loading = ref(false)
const loadingMore = ref(false)
const actionBusy = ref(false)
const errorMessage = ref('')
const notifications = ref<AppNotificationItem[]>([])
const unreadCount = ref(0)
const currentPage = ref(1)
const lastPage = ref(1)

let pollHandle: number | null = null

const hasMore = computed(() => currentPage.value < lastPage.value)
const hasUnread = computed(() => unreadCount.value > 0)
const unreadBadge = computed(() => (unreadCount.value > 99 ? '99+' : String(unreadCount.value)))
const activeLocale = computed(() => (locale.value || 'en').toLowerCase())

function shouldFetchInCurrentInstance(): boolean {
  if (open.value) {
    return true
  }

  if (!root.value) {
    return true
  }

  return root.value.offsetParent !== null
}

function isArabicLocale(): boolean {
  return activeLocale.value.startsWith('ar')
}

function localizedTitle(notification: AppNotificationItem): string {
  if (isArabicLocale()) {
    return notification.title_ar || notification.title_en || t('common.notifications.fallbackTitle')
  }

  return notification.title_en || notification.title_ar || t('common.notifications.fallbackTitle')
}

function localizedDescription(notification: AppNotificationItem): string {
  if (isArabicLocale()) {
    return notification.description_ar || notification.description_en || ''
  }

  return notification.description_en || notification.description_ar || ''
}

function notificationMeta(notification: AppNotificationItem): string {
  const ref = notification.reference_number ? `#${notification.reference_number}` : null
  const company = notification.company_name || null
  const stage = notification.workflow_stage ? String(notification.workflow_stage).replaceAll('_', ' ') : null

  return [ref, company, stage].filter(Boolean).join(' • ')
}

function formatDate(value?: string | null): string {
  if (!value) {
    return t('common.notifications.justNow')
  }

  const parsed = new Date(value)

  if (Number.isNaN(parsed.getTime())) {
    return t('common.notifications.justNow')
  }

  return new Intl.DateTimeFormat(isArabicLocale() ? 'ar-SA' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(parsed)
}

async function fetchNotifications(page = 1, append = false) {
  if (append) {
    loadingMore.value = true
  } else {
    loading.value = true
  }
  errorMessage.value = ''

  try {
    const response = await listNotifications({
      page,
      per_page: 8,
    })

    currentPage.value = response.pagination.current_page
    lastPage.value = response.pagination.last_page
    unreadCount.value = response.unread_count
    notifications.value = append
      ? [...notifications.value, ...response.notifications]
      : response.notifications
  } catch {
    errorMessage.value = t('common.notifications.loadFailed')
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

async function refreshUnreadCount() {
  if (!shouldFetchInCurrentInstance()) {
    return
  }

  try {
    const response = await listNotifications({
      page: 1,
      per_page: 8,
    })

    unreadCount.value = response.unread_count

    if (open.value) {
      currentPage.value = response.pagination.current_page
      lastPage.value = response.pagination.last_page
      notifications.value = response.notifications
    }
  } catch {
    // Silent polling errors: keep current state and avoid noisy UI.
  }
}

async function toggleDropdown() {
  open.value = !open.value

  if (open.value) {
    await fetchNotifications(1, false)
  }
}

function closeDropdown() {
  open.value = false
}

async function loadMore() {
  if (!hasMore.value || loadingMore.value) {
    return
  }

  await fetchNotifications(currentPage.value + 1, true)
}

async function markOne(notification: AppNotificationItem) {
  if (notification.is_read || actionBusy.value) {
    return
  }

  actionBusy.value = true

  try {
    const response = await markNotificationRead(notification.id)
    unreadCount.value = response.unread_count
    notifications.value = notifications.value.map((item) => (
      item.id === notification.id
        ? {
            ...item,
            is_read: true,
            read_at: response.notification.read_at,
          }
        : item
    ))
  } finally {
    actionBusy.value = false
  }
}

async function markAll() {
  if (!hasUnread.value || actionBusy.value) {
    return
  }

  actionBusy.value = true

  try {
    const response = await markAllNotificationsRead()
    unreadCount.value = response.unread_count
    notifications.value = notifications.value.map((item) => ({
      ...item,
      is_read: true,
      read_at: item.read_at || new Date().toISOString(),
    }))
  } finally {
    actionBusy.value = false
  }
}

async function openNotification(notification: AppNotificationItem) {
  if (!notification.is_read) {
    await markOne(notification)
  }

  const target = notification.target

  if (!target) {
    closeDropdown()
    return
  }

  try {
    if (target.route_name) {
      const location: RouteLocationRaw = {
        name: target.route_name,
        params: target.params ?? {},
      }
      await router.push(location)
    } else if (target.path) {
      await router.push(target.path)
    }
  } finally {
    closeDropdown()
  }
}

function onDocumentClick(event: MouseEvent) {
  if (!open.value) {
    return
  }

  const target = event.target as Node | null

  if (root.value && target && !root.value.contains(target)) {
    closeDropdown()
  }
}

function onEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeDropdown()
  }
}

function onVisibilityChange() {
  if (document.visibilityState === 'visible') {
    void refreshUnreadCount()
  }
}

onMounted(() => {
  void refreshUnreadCount()
  pollHandle = window.setInterval(() => {
    void refreshUnreadCount()
  }, 45000)

  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onEscape)
  document.addEventListener('visibilitychange', onVisibilityChange)
})

onBeforeUnmount(() => {
  if (pollHandle !== null) {
    window.clearInterval(pollHandle)
    pollHandle = null
  }

  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onEscape)
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
  <div
    ref="root"
    class="app-notification"
    :class="[
      `app-notification--${props.theme}`,
      { 'is-open': open },
    ]"
  >
    <button
      type="button"
      class="app-notification__trigger"
      :aria-label="t('common.notifications.open')"
      @click="toggleDropdown"
    >
      <i class="fas fa-bell"></i>
      <span v-if="hasUnread" class="app-notification__badge">{{ unreadBadge }}</span>
    </button>

    <div v-if="open" class="app-notification__panel">
      <div class="app-notification__head">
        <div>
          <strong>{{ t('common.notifications.title') }}</strong>
          <small v-if="hasUnread">{{ t('common.notifications.unreadCount', { count: unreadCount }) }}</small>
        </div>

        <button
          type="button"
          class="app-notification__mark-all"
          :disabled="!hasUnread || actionBusy"
          @click="markAll"
        >
          {{ t('common.notifications.markAllRead') }}
        </button>
      </div>

      <div class="app-notification__body">
        <div v-if="loading" class="app-notification__state">
          {{ t('common.notifications.loading') }}
        </div>

        <div v-else-if="errorMessage" class="app-notification__state app-notification__state--error">
          {{ errorMessage }}
        </div>

        <div v-else-if="notifications.length === 0" class="app-notification__state">
          {{ t('common.notifications.empty') }}
        </div>

        <button
          v-for="notification in notifications"
          :key="notification.id"
          type="button"
          class="app-notification__item"
          :class="{ 'is-unread': !notification.is_read }"
          @click="openNotification(notification)"
        >
          <div class="app-notification__item-head">
            <strong>{{ localizedTitle(notification) }}</strong>
            <time>{{ formatDate(notification.created_at) }}</time>
          </div>

          <p v-if="notificationMeta(notification)" class="app-notification__meta">
            {{ notificationMeta(notification) }}
          </p>

          <p v-if="localizedDescription(notification)" class="app-notification__desc">
            {{ localizedDescription(notification) }}
          </p>
        </button>
      </div>

      <div v-if="hasMore" class="app-notification__foot">
        <button
          type="button"
          class="app-notification__load-more"
          :disabled="loadingMore"
          @click="loadMore"
        >
          {{ loadingMore ? t('common.notifications.loading') : t('common.notifications.loadMore') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.app-notification {
  position: relative;
  flex: 0 0 auto;
}

.app-notification__trigger {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 14px;
  border: 1px solid rgba(148, 163, 184, 0.25);
  background: rgba(255, 255, 255, 0.94);
  color: #334155;
  cursor: pointer;
  transition: border-color .2s ease, transform .2s ease, box-shadow .2s ease;
}

.app-notification__trigger:hover {
  border-color: rgba(79, 70, 229, 0.4);
  transform: translateY(-1px);
  box-shadow: 0 10px 20px rgba(79, 70, 229, 0.14);
}

.app-notification__badge {
  position: absolute;
  inset-block-start: -6px;
  inset-inline-end: -7px;
  min-width: 19px;
  height: 19px;
  border-radius: 999px;
  border: 2px solid #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  background: #ef4444;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  line-height: 1;
}

.app-notification__panel {
  position: absolute;
  inset-block-start: calc(100% + 10px);
  inset-inline-end: 0;
  width: min(92vw, 400px);
  max-height: min(72vh, 560px);
  border-radius: 18px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  background: #fff;
  box-shadow: 0 26px 60px rgba(15, 23, 42, 0.18);
  overflow: hidden;
  z-index: 120;
}

[dir='rtl'] .app-notification__panel {
  inset-inline-end: auto;
  inset-inline-start: 0;
}

.app-notification__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 14px 12px;
  border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.app-notification__head strong {
  color: #0f172a;
  font-size: 14px;
  line-height: 1.2;
}

.app-notification__head small {
  display: block;
  margin-top: 4px;
  color: #64748b;
  font-size: 11px;
}

.app-notification__mark-all {
  min-height: 30px;
  padding: 0 10px;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.24);
  background: rgba(248, 250, 252, 0.9);
  color: #334155;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.app-notification__mark-all:disabled {
  opacity: .5;
  cursor: not-allowed;
}

.app-notification__body {
  display: grid;
  gap: 8px;
  padding: 10px;
  max-height: min(58vh, 460px);
  overflow-y: auto;
}

.app-notification__state {
  padding: 18px 14px;
  border-radius: 14px;
  border: 1px dashed rgba(148, 163, 184, 0.26);
  background: rgba(248, 250, 252, 0.8);
  color: #64748b;
  text-align: center;
  font-size: 12px;
}

.app-notification__state--error {
  border-style: solid;
  border-color: rgba(248, 113, 113, 0.24);
  background: rgba(254, 242, 242, 0.88);
  color: #b91c1c;
}

.app-notification__item {
  width: 100%;
  text-align: start;
  display: grid;
  gap: 8px;
  padding: 12px;
  border-radius: 14px;
  border: 1px solid rgba(226, 232, 240, 0.92);
  background: rgba(255, 255, 255, 0.98);
  cursor: pointer;
  transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.app-notification__item:hover {
  border-color: rgba(79, 70, 229, 0.24);
  box-shadow: 0 12px 22px rgba(79, 70, 229, 0.1);
  transform: translateY(-1px);
}

.app-notification__item.is-unread {
  border-color: rgba(59, 130, 246, 0.24);
  background: linear-gradient(180deg, rgba(239, 246, 255, 0.82), rgba(255, 255, 255, 0.98));
}

.app-notification__item-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.app-notification__item-head strong {
  color: #0f172a;
  font-size: 12px;
  line-height: 1.45;
}

.app-notification__item-head time {
  color: #94a3b8;
  font-size: 10px;
  white-space: nowrap;
}

.app-notification__meta {
  margin: 0;
  color: #475569;
  font-size: 11px;
  line-height: 1.5;
}

.app-notification__desc {
  margin: 0;
  color: #64748b;
  font-size: 11px;
  line-height: 1.55;
}

.app-notification__foot {
  padding: 10px;
  border-top: 1px solid rgba(226, 232, 240, 0.9);
}

.app-notification__load-more {
  width: 100%;
  min-height: 34px;
  border-radius: 10px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  background: rgba(248, 250, 252, 0.92);
  color: #334155;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.app-notification__load-more:disabled {
  opacity: .6;
  cursor: not-allowed;
}

.app-notification--client .app-notification__trigger {
  border-radius: 999px;
}

@media (max-width: 768px) {
  .app-notification__panel {
    inset-inline-end: -8px;
    width: min(95vw, 360px);
  }

  [dir='rtl'] .app-notification__panel {
    inset-inline-end: auto;
    inset-inline-start: -8px;
  }
}
</style>
