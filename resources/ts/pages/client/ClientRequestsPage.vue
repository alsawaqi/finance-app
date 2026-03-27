<script setup lang="ts">
const requests = [
  {
    id: 101,
    title: 'SME Working Capital Support',
    code: 'REQ-2026-101',
    stage: 'Waiting for Signature',
    status: 'Approved by admin and waiting for client signature.',
    badgeClass: 'client-badge--purple',
    progress: '82%',
    primaryLabel: 'Sign Contract',
    primaryRoute: { name: 'client-request-sign', params: { id: 101 } },
  },
  {
    id: 102,
    title: 'Personal Finance Restructuring',
    code: 'REQ-2026-102',
    stage: 'Waiting for Documents',
    status: 'Contract signed. The admin has now requested supporting documents.',
    badgeClass: 'client-badge--green',
    progress: '88%',
    primaryLabel: 'Upload Documents',
    primaryRoute: { name: 'client-request-documents', params: { id: 102 } },
  },
  {
    id: 103,
    title: 'Vehicle Financing Request',
    code: 'REQ-2026-103',
    stage: 'Under Review',
    status: 'Your request is currently being checked by the admin team.',
    badgeClass: 'client-badge--amber',
    progress: '48%',
    primaryLabel: 'View Details',
    primaryRoute: { name: 'client-request-details', params: { id: 103 } },
  },
  {
    id: 104,
    title: 'Contract Amendment Request',
    code: 'REQ-2026-104',
    stage: 'Completed',
    status: 'The request has completed all required client and admin actions.',
    badgeClass: 'client-badge--blue',
    progress: '100%',
    primaryLabel: 'View Summary',
    primaryRoute: { name: 'client-request-details', params: { id: 104 } },
  },
]
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">My Requests</span>
      <h1 class="client-hero-title">Open any request to follow its status and complete only the action required at that stage.</h1>
      <p class="client-hero-text">
        Documents are no longer treated as a separate generic area. Everything now revolves around the request itself, which is the correct client experience for your process.
      </p>
      <div class="client-filter-bar">
        <span class="client-filter-chip">All Requests</span>
        <span class="client-filter-chip">Under Review</span>
        <span class="client-filter-chip">Waiting for Signature</span>
        <span class="client-filter-chip">Waiting for Documents</span>
      </div>
    </section>

    <section class="client-request-grid client-reveal-left">
      <article v-for="item in requests" :key="item.id" class="client-request-card">
        <div class="client-card-head">
          <div>
            <h3>{{ item.title }}</h3>
            <p class="client-meta">{{ item.code }}</p>
          </div>
          <span class="client-badge" :class="item.badgeClass">{{ item.stage }}</span>
        </div>

        <p class="client-muted">{{ item.status }}</p>

        <div class="client-progress">
          <span :style="{ width: item.progress }"></span>
        </div>

        <div class="client-inline-actions">
          <RouterLink :to="{ name: 'client-request-details', params: { id: item.id } }" class="client-btn-secondary">View Details</RouterLink>
          <RouterLink :to="item.primaryRoute" class="client-btn-primary">{{ item.primaryLabel }}</RouterLink>
        </div>
      </article>
    </section>
  </div>
</template>
