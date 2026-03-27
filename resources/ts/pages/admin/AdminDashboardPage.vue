<script setup lang="ts">
import AdminRequestPipeline from './inc/AdminRequestPipeline.vue'
import AdminRecentRequests from './inc/AdminRecentRequests.vue'
import AdminStatCard from './inc/AdminStatCard.vue'
import AdminTaskPanel from './inc/AdminTaskPanel.vue'

const stats = [
  {
    label: 'Active requests',
    value: '128',
    trend: '+12 this week',
    icon: 'fas fa-layer-group',
  },
  {
    label: 'Awaiting approval',
    value: '24',
    trend: '8 urgent',
    icon: 'fas fa-user-check',
  },
  {
    label: 'Pending signatures',
    value: '11',
    trend: '4 ready today',
    icon: 'fas fa-file-signature',
  },
  {
    label: 'Documents in review',
    value: '19',
    trend: '6 updated',
    icon: 'fas fa-folder-open',
  },
]

const pipeline = [
  {
    stage: 'New submissions',
    count: 18,
    helper: 'Fresh requests waiting for first admin review.',
    tone: 'violet',
  },
  {
    stage: 'Approved / waiting sign',
    count: 9,
    helper: 'Clients need to review and sign generated contracts.',
    tone: 'blue',
  },
  {
    stage: 'Waiting documents',
    count: 14,
    helper: 'Signed requests that now need supporting uploads.',
    tone: 'amber',
  },
  {
    stage: 'Processing',
    count: 21,
    helper: 'Requests moving through final internal checks.',
    tone: 'emerald',
  },
]

const rows = [
  {
    code: 'REQ-2026-0142',
    client: 'Abdullah Al Sawaqi',
    type: 'Personal Finance',
    status: 'Approved',
    stage: 'Waiting signature',
  },
  {
    code: 'REQ-2026-0138',
    client: 'Nizwa Trading LLC',
    type: 'Business Facility',
    status: 'Under review',
    stage: 'Admin review',
  },
  {
    code: 'REQ-2026-0133',
    client: 'Huda Al Riyami',
    type: 'Auto Finance',
    status: 'Signed',
    stage: 'Waiting documents',
  },
  {
    code: 'REQ-2026-0129',
    client: 'Muscat Retail Group',
    type: 'Working Capital',
    status: 'Processing',
    stage: 'Internal review',
  },
]

const tasks = [
  {
    title: 'Approve high-value requests',
    helper: 'Review flagged items that need decision today.',
    value: '6 due',
  },
  {
    title: 'Check pending client signatures',
    helper: 'Follow up on requests ready for signing.',
    value: '11 pending',
  },
  {
    title: 'Review missing documents',
    helper: 'Requests where the client uploaded new files.',
    value: '7 updates',
  },
]
</script>

<template>
  <div class="admin-dashboard-page">
    <section class="admin-hero admin-reveal-up">
      <div class="admin-hero__content">
        <span class="admin-hero__eyebrow">Operations summary</span>
        <h2>Keep approvals, signatures, and document collection moving smoothly.</h2>
        <p>
          A compact admin overview designed for real daily use, with full-width content, cleaner spacing,
          and room for your next request management pages under the same shell.
        </p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn">Review requests</button>
        <button type="button" class="admin-secondary-btn">Export summary</button>
      </div>
    </section>

    <section class="admin-stats-grid">
      <AdminStatCard
        v-for="stat in stats"
        :key="stat.label"
        :label="stat.label"
        :value="stat.value"
        :trend="stat.trend"
        :icon="stat.icon"
      />
    </section>

    <section class="admin-content-grid">
      <AdminRequestPipeline :items="pipeline" />
      <AdminTaskPanel :tasks="tasks" />
      <AdminRecentRequests :rows="rows" />
    </section>
  </div>
</template>
