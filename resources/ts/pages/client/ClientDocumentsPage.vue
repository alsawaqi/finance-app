<script setup lang="ts">
const files = [
  { title: 'Bank Statements.pdf', meta: 'Uploaded for REQ-2026-014 · 2.3 MB' },
  { title: 'Signed Contract Draft.pdf', meta: 'Uploaded for REQ-2026-011 · 1.1 MB' },
  { title: 'Financial Profile.xlsx', meta: 'Uploaded for REQ-2026-009 · 850 KB' },
]
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">Documents</span>
      <h1 class="client-hero-title">A dedicated documents area for uploads, approvals, and client file history.</h1>
      <p class="client-hero-text">
        Keeping this as its own route makes the client portal easier to navigate and gives you space later for upload zones,
        required documents, version history, and download actions.
      </p>
    </section>

    <section class="client-upload-grid client-reveal-left">
      <article class="client-upload-card">
        <h3>Upload New File</h3>
        <p class="client-muted">Drag and drop area, file rules, and request-linked uploads can be added here next.</p>
        <div class="client-inline-actions">
          <button type="button" class="client-btn-primary">Choose Files</button>
        </div>
      </article>

      <article class="client-upload-card">
        <h3>Pending Verification</h3>
        <p class="client-muted">Show files waiting for internal review or missing mandatory documents.</p>
        <span class="client-badge client-badge--amber">03 Pending</span>
      </article>

      <article class="client-upload-card">
        <h3>Approved Library</h3>
        <p class="client-muted">Later this can become the reusable verified-document library for future requests.</p>
        <span class="client-badge client-badge--green">12 Approved</span>
      </article>
    </section>

    <section class="client-card-grid client-reveal-up">
      <article class="client-content-card client-content-card--full">
        <div class="client-card-head">
          <div>
            <h3>Recent Files</h3>
            <p class="client-subtext">Document cards stay in one focused view instead of competing with the dashboard.</p>
          </div>
        </div>
        <div class="client-file-list">
          <div v-for="file in files" :key="file.title" class="client-file-item">
            <div>
              <strong>{{ file.title }}</strong>
              <p class="client-meta">{{ file.meta }}</p>
            </div>
            <button type="button" class="client-btn-secondary">View File</button>
          </div>
        </div>
      </article>
    </section>
  </div>
</template>
