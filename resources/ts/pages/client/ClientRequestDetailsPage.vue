<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const requestId = computed(() => Number(route.params.id))

const requestMap = {
  101: {
    id: 101,
    title: 'SME Working Capital Support',
    code: 'REQ-2026-101',
    stage: 'Waiting for Signature',
    badgeClass: 'client-badge--purple',
    progress: '82%',
    adminNote: 'Your request was approved. Please review and sign the contract to continue.',
    answers: [
      ['Request Type', 'Working Capital Support'],
      ['Requested Amount', 'OMR 25,000'],
      ['Priority', 'High'],
      ['Submitted On', 'March 26, 2026'],
    ],
    timeline: [
      'Client submitted the request.',
      'Admin reviewed and approved the request.',
      'Contract is ready and awaiting signature.',
    ],
    canSign: true,
    canUploadDocuments: false,
  },
  102: {
    id: 102,
    title: 'Personal Finance Restructuring',
    code: 'REQ-2026-102',
    stage: 'Waiting for Documents',
    badgeClass: 'client-badge--green',
    progress: '88%',
    adminNote: 'Your contract has already been signed. Please upload the requested documents to continue.',
    answers: [
      ['Request Type', 'Finance Restructuring'],
      ['Requested Amount', 'OMR 12,000'],
      ['Priority', 'Medium'],
      ['Submitted On', 'March 24, 2026'],
    ],
    timeline: [
      'Client submitted the request.',
      'Admin approved the request.',
      'Client signed the contract.',
      'Document upload stage opened.',
    ],
    canSign: false,
    canUploadDocuments: true,
  },
  103: {
    id: 103,
    title: 'Vehicle Financing Request',
    code: 'REQ-2026-103',
    stage: 'Under Review',
    badgeClass: 'client-badge--amber',
    progress: '48%',
    adminNote: 'The admin is still reviewing the request. No client action is required yet.',
    answers: [
      ['Request Type', 'Vehicle Financing'],
      ['Requested Amount', 'OMR 8,500'],
      ['Priority', 'Normal'],
      ['Submitted On', 'March 27, 2026'],
    ],
    timeline: [
      'Client submitted the request.',
      'Admin opened the request for review.',
    ],
    canSign: false,
    canUploadDocuments: false,
  },
  104: {
    id: 104,
    title: 'Contract Amendment Request',
    code: 'REQ-2026-104',
    stage: 'Completed',
    badgeClass: 'client-badge--blue',
    progress: '100%',
    adminNote: 'This request is complete. You can view the summary and keep the final document for your records.',
    answers: [
      ['Request Type', 'Contract Amendment'],
      ['Requested Amount', 'N/A'],
      ['Priority', 'Normal'],
      ['Submitted On', 'March 20, 2026'],
    ],
    timeline: [
      'Client submitted the amendment request.',
      'Admin approved the revised contract terms.',
      'Client completed final confirmation.',
      'Request marked as completed.',
    ],
    canSign: false,
    canUploadDocuments: false,
  },
} as const

const request = computed(() => requestMap[requestId.value as keyof typeof requestMap] ?? requestMap[103])
</script>

<template>
  <div class="client-page-grid">
    <section class="client-status-banner client-reveal-up">
      <div class="client-card-head">
        <div>
          <span class="client-eyebrow">Request Details</span>
          <h1 class="client-hero-title">{{ request.title }}</h1>
          <p class="client-hero-text">{{ request.code }} · Keep track of the exact stage and only act when the workflow asks you to.</p>
        </div>
        <span class="client-badge" :class="request.badgeClass">{{ request.stage }}</span>
      </div>

      <div class="client-progress">
        <span :style="{ width: request.progress }"></span>
      </div>

      <div class="client-empty-note">{{ request.adminNote }}</div>

      <div class="client-inline-actions">
        <RouterLink v-if="request.canSign" :to="{ name: 'client-request-sign', params: { id: request.id } }" class="client-btn-primary">Sign Contract</RouterLink>
        <RouterLink v-if="request.canUploadDocuments" :to="{ name: 'client-request-documents', params: { id: request.id } }" class="client-btn-primary">Upload Documents</RouterLink>
        <RouterLink :to="{ name: 'client-requests' }" class="client-btn-secondary">Back to Requests</RouterLink>
      </div>
    </section>

    <section class="client-details-grid client-reveal-left">
      <article class="client-detail-card">
        <div class="client-card-head">
          <div>
            <h3>Request Summary</h3>
            <p class="client-subtext">Main information submitted by the client.</p>
          </div>
        </div>
        <div class="client-summary-list">
          <div v-for="answer in request.answers" :key="answer[0]" class="client-summary-item">
            <span>{{ answer[0] }}</span>
            <strong>{{ answer[1] }}</strong>
          </div>
        </div>
      </article>

      <article class="client-detail-card">
        <div class="client-card-head">
          <div>
            <h3>Workflow Timeline</h3>
            <p class="client-subtext">A clear timeline helps the client understand where the request is.</p>
          </div>
        </div>
        <div class="client-check-list">
          <div v-for="item in request.timeline" :key="item" class="client-check-item">
            <div>
              <strong>{{ item }}</strong>
            </div>
          </div>
        </div>
      </article>
    </section>
  </div>
</template>
