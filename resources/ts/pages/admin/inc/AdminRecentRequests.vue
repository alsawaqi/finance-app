<script setup lang="ts">
import { useI18n } from 'vue-i18n'

type RequestRow = {
  code: string
  client: string
  type: string
  status: string
  stage: string
}

defineProps<{
  rows: RequestRow[]
}>()

const { t } = useI18n()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ t('adminSharedWidgets.recentRequests.eyebrow') }}</span>
        <h2>{{ t('adminSharedWidgets.recentRequests.title') }}</h2>
      </div>
      <button type="button" class="admin-panel__action">{{ t('adminSharedWidgets.recentRequests.openAll') }}</button>
    </div>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>{{ t('adminSharedWidgets.recentRequests.columns.request') }}</th>
            <th>{{ t('adminSharedWidgets.recentRequests.columns.client') }}</th>
            <th>{{ t('adminSharedWidgets.recentRequests.columns.type') }}</th>
            <th>{{ t('adminSharedWidgets.recentRequests.columns.status') }}</th>
            <th>{{ t('adminSharedWidgets.recentRequests.columns.stage') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.code">
            <td><strong>{{ row.code }}</strong></td>
            <td>{{ row.client }}</td>
            <td>{{ row.type }}</td>
            <td><span class="admin-status-pill">{{ row.status }}</span></td>
            <td>{{ row.stage }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
