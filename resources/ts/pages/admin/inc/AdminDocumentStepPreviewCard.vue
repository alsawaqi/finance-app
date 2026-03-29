<script setup lang="ts">
defineProps<{
  name: string
  description: string
  allowedFileTypes: string[]
  maxFileSizeMb: number | null
  isRequired: boolean
  isActive: boolean
}>()
</script>

<template>
  <section class="document-step-panel">
    <div class="document-step-panel__head">
      <div>
        <span class="document-step-panel__eyebrow">Client preview</span>
        <h2>How this upload step will look</h2>
      </div>
    </div>

    <div class="document-step-preview-card">
      <div class="document-step-preview-card__badges">
        <span class="document-step-pill" :class="isRequired ? 'is-required' : 'is-optional'">
          {{ isRequired ? 'Required' : 'Optional' }}
        </span>
        <span class="document-step-pill" :class="isActive ? 'is-active' : 'is-inactive'">
          {{ isActive ? 'Active' : 'Inactive' }}
        </span>
      </div>

      <h3>{{ name || 'Document name will appear here' }}</h3>
      <p>
        {{ description || 'A short description will guide the client on what file to upload for this step.' }}
      </p>

      <div class="document-step-preview-meta">
        <div>
          <strong>Allowed types</strong>
          <div class="document-step-chip-row">
            <span v-for="item in allowedFileTypes" :key="item" class="document-step-chip">{{ item }}</span>
            <span v-if="!allowedFileTypes.length" class="document-step-chip is-muted">Any file type</span>
          </div>
        </div>

        <div>
          <strong>Max size</strong>
          <span class="document-step-meta-text">{{ maxFileSizeMb ? `${maxFileSizeMb} MB` : 'Use default size limit' }}</span>
        </div>
      </div>

      <div class="document-step-upload-box">
        <span>Upload area preview</span>
        <button type="button" class="document-step-secondary-btn" disabled>Select file</button>
      </div>
    </div>
  </section>
</template>
