<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { BankItem } from '@/services/banks'

defineProps<{
  rows: BankItem[]
  loading: boolean
  totalCount?: number
}>()

defineEmits<{
  (e: 'edit', row: BankItem): void
  (e: 'toggle', row: BankItem): void
}>()
const { t } = useI18n()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ t('adminBankLibrary.eyebrow') }}</span>
        <h2>{{ t('adminBankLibrary.title') }}</h2>
      </div>
      <span class="admin-panel__action is-static">{{ t('adminBankLibrary.count', { count: totalCount ?? rows.length }) }}</span>
    </div>

    <div v-if="loading" class="admin-table-empty">{{ t('adminBankLibrary.states.loading') }}</div>

    <template v-else>
      <div v-if="rows.length === 0" class="admin-table-empty">
        {{ t('adminBankLibrary.states.empty') }}
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>{{ t('adminBankLibrary.columns.bank') }}</th>
              <th>{{ t('adminBankLibrary.columns.code') }}</th>
              <th>{{ t('adminBankLibrary.columns.shortName') }}</th>
              <th>{{ t('adminBankLibrary.columns.linkedAgents') }}</th>
              <th>{{ t('adminBankLibrary.columns.status') }}</th>
              <th>{{ t('adminBankLibrary.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.name }}</strong>
                  <small>{{ row.creator_name || t('adminBankLibrary.states.system') }}</small>
                </div>
              </td>
              <td>{{ row.code || t('adminBankLibrary.states.emptyValue') }}</td>
              <td>{{ row.short_name || t('adminBankLibrary.states.emptyValue') }}</td>
              <td>{{ row.agents_count }}</td>
              <td>
                <span class="admin-status-pill" :class="row.is_active ? 'is-success' : 'is-muted'">
                  {{ row.is_active ? t('adminBankLibrary.states.active') : t('adminBankLibrary.states.inactive') }}
                </span>
              </td>
              <td>
                <div class="admin-table-actions">
                  <button type="button" class="admin-inline-link" @click="$emit('edit', row)">{{ t('adminBankLibrary.actions.edit') }}</button>
                  <button type="button" class="admin-inline-link" @click="$emit('toggle', row)">
                    {{ row.is_active ? t('adminBankLibrary.actions.deactivate') : t('adminBankLibrary.actions.activate') }}
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
