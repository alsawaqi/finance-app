<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { StaffUserItem } from '@/services/staffUsers'
import { formatDateOnly, formatDateTime } from '@/utils/dateTime'

defineProps<{
  rows: StaffUserItem[]
  loading: boolean
  totalCount?: number
}>()

defineEmits<{
  (e: 'edit', row: StaffUserItem): void
  (e: 'toggle', row: StaffUserItem): void
}>()
const { t, locale } = useI18n()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ t('adminStaffLibrary.eyebrow') }}</span>
        <h2>{{ t('adminStaffLibrary.title') }}</h2>
      </div>
      <span class="admin-panel__action is-static">{{ t('adminStaffLibrary.accountsCount', { count: totalCount ?? rows.length }) }}</span>
    </div>

    <div v-if="loading" class="admin-table-empty">{{ t('adminStaffLibrary.states.loading') }}</div>

    <template v-else>
      <div v-if="rows.length === 0" class="admin-table-empty">
        {{ t('adminStaffLibrary.states.empty') }}
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>{{ t('adminStaffLibrary.columns.staff') }}</th>
              <th>{{ t('adminStaffLibrary.columns.contact') }}</th>
              <th>{{ t('adminStaffLibrary.columns.rolePermissions') }}</th>
              <th>{{ t('adminStaffLibrary.columns.status') }}</th>
              <th>{{ t('adminStaffLibrary.columns.lastLogin') }}</th>
              <th>{{ t('adminStaffLibrary.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.name }}</strong>
                  <small>#{{ row.id }} | {{ row.email }}</small>
                </div>
              </td>
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.phone || t('adminStaffLibrary.states.noPhone') }}</strong>
                  <small>{{ formatDateOnly(row.created_at, locale, t('adminStaffLibrary.states.recentlyCreated')) }}</small>
                </div>
              </td>
              <td>
                <div class="admin-chip-list">
                  <span class="admin-chip admin-chip--violet">{{ t('adminStaffLibrary.states.staffRole') }}</span>
                  <span v-if="row.permissions_count > 0" class="admin-chip admin-chip--blue">
                    {{ t('adminStaffLibrary.states.directPermissions', { count: row.permissions_count }) }}
                  </span>
                  <span v-else class="admin-chip admin-chip--muted">{{ t('adminStaffLibrary.states.baseRoleOnly') }}</span>
                </div>
              </td>
              <td>
                <span class="admin-status-pill" :class="row.is_active ? 'is-success' : 'is-muted'">
                  {{ row.is_active ? t('adminStaffLibrary.states.active') : t('adminStaffLibrary.states.inactive') }}
                </span>
              </td>
              <td>{{ formatDateTime(row.last_login_at, locale, t('adminStaffLibrary.states.never')) }}</td>
              <td>
                <div class="admin-table-actions">
                  <button type="button" class="admin-inline-link" @click="$emit('edit', row)">{{ t('adminStaffLibrary.actions.edit') }}</button>
                  <button type="button" class="admin-inline-link" @click="$emit('toggle', row)">
                    {{ row.is_active ? t('adminStaffLibrary.actions.deactivate') : t('adminStaffLibrary.actions.activate') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>
