<script setup lang="ts">
import ClientPageShell from '../public/inc/ClientPageShell.vue'

type RequestItem = {
  id: string
  title: string
  status: 'In Review' | 'Documents Needed' | 'Approved'
  statusClass: 'info' | 'warning' | 'success'
  priority: string
  updated: string
  progress: number
  stage: string
  owner: string
}

type TimelineItem = {
  icon: string
  title: string
  description: string
  meta: string
}

type TaskItem = {
  title: string
  description: string
  tag: string
}

type DocumentItem = {
  title: string
  description: string
  tag: string
}

type ActivityItem = {
  title: string
  description: string
  tag: string
}

const requestItems: RequestItem[] = [
  {
    id: 'REQ-24018',
    title: 'Working Capital Finance Request',
    status: 'In Review',
    statusClass: 'info',
    priority: 'High Priority',
    updated: 'Updated 2 hours ago',
    progress: 68,
    stage: 'Financial assessment stage',
    owner: 'Assigned to client success manager',
  },
  {
    id: 'REQ-23991',
    title: 'Equipment Leasing Application',
    status: 'Documents Needed',
    statusClass: 'warning',
    priority: 'Action Required',
    updated: 'Updated today',
    progress: 42,
    stage: 'Waiting for signed quotation upload',
    owner: 'Pending your document submission',
  },
  {
    id: 'REQ-23874',
    title: 'Business Expansion Support',
    status: 'Approved',
    statusClass: 'success',
    priority: 'Completed',
    updated: 'Updated yesterday',
    progress: 100,
    stage: 'Ready for final agreement',
    owner: 'Contract preparation completed',
  },
]

const timelineItems: TimelineItem[] = [
  {
    icon: 'fas fa-file-signature',
    title: 'Application submitted successfully',
    description: 'Your latest finance request entered the validation queue and passed the first intake review.',
    meta: 'Today · 09:30 AM',
  },
  {
    icon: 'fas fa-user-tie',
    title: 'Advisor assigned to your case',
    description: 'A relationship officer has been assigned and is reviewing your submitted business details.',
    meta: 'Yesterday · 04:10 PM',
  },
  {
    icon: 'fas fa-folder-open',
    title: 'Documents reviewed',
    description: 'Core company documents were reviewed and one additional financial attachment is requested.',
    meta: 'Yesterday · 11:45 AM',
  },
]

const tasks: TaskItem[] = [
  {
    title: 'Upload latest bank statement',
    description: 'Attach a signed statement for the last 6 months to move the request into credit review.',
    tag: 'Due today',
  },
  {
    title: 'Review contract draft',
    description: 'Check the generated contract summary before the final approval meeting is scheduled.',
    tag: 'Pending review',
  },
  {
    title: 'Confirm company profile details',
    description: 'Validate company address, trade licence, and authorized signatory information.',
    tag: 'Quick update',
  },
]

const documents: DocumentItem[] = [
  {
    title: 'Draft Facility Agreement.pdf',
    description: 'Latest generated agreement version uploaded by the finance team for your review.',
    tag: 'New',
  },
  {
    title: 'KYC Checklist.docx',
    description: 'Structured onboarding checklist showing which items are complete and what remains pending.',
    tag: 'Checklist',
  },
  {
    title: 'Company Profile 2026.pdf',
    description: 'Your previously submitted profile stays visible here for fast re-use in future requests.',
    tag: 'Saved',
  },
]

const activities: ActivityItem[] = [
  {
    title: 'Comment from your advisor',
    description: 'Please attach the signed quotation so we can advance your leasing request to the next stage.',
    tag: '2 hours ago',
  },
  {
    title: 'Workflow moved forward',
    description: 'Your working capital request moved from intake to internal assessment.',
    tag: 'Today',
  },
  {
    title: 'Reminder scheduled',
    description: 'A reminder was created for the remaining onboarding documents required this week.',
    tag: 'This week',
  },
]
</script>

<template>
  <ClientPageShell>
    <section class="client-dashboard-section">
      <div class="container">
        <div class="client-hero-grid">
          <div class="client-hero-panel client-reveal-left">
            <span class="client-overline">
              <i class="fas fa-chart-line"></i>
              Client Finance Workspace
            </span>

            <h1 class="client-hero-title">A premium dashboard that still feels like your public website.</h1>
            <p class="client-hero-text">
              This client portal keeps the same visual identity as the homepage while giving the user a much more professional
              place to submit requests, follow progress, manage documents, and stay updated on every finance step.
            </p>

            <div class="client-hero-actions">
              <a href="#new-request" class="btn_style_one">Create New Request</a>
              <a href="#requests" class="client-secondary-btn">Track My Requests</a>
            </div>

            <div class="client-kpi-grid">
              <div class="client-kpi-card client-float-in">
                <h3>03</h3>
                <p>Active requests</p>
                <span>Across current workflow stages</span>
              </div>
              <div class="client-kpi-card client-float-in">
                <h3>02</h3>
                <p>Need attention</p>
                <span>Pending your documents or review</span>
              </div>
              <div class="client-kpi-card client-float-in">
                <h3>01</h3>
                <p>Approved file</p>
                <span>Ready for agreement finalization</span>
              </div>
              <div class="client-kpi-card client-float-in">
                <h3>94%</h3>
                <p>Profile completion</p>
                <span>Strong onboarding readiness</span>
              </div>
            </div>
          </div>

          <aside class="client-summary-panel client-reveal-up">
            <span class="client-card-label">Today’s Summary</span>
            <h3>Everything important in one clean panel.</h3>
            <p>
              This side panel gives the client an instant understanding of what is happening now,
              what needs attention next, and how healthy the current request pipeline looks.
            </p>

            <div class="client-summary-list">
              <div class="client-summary-item">
                <div>
                  <strong>Latest request stage</strong>
                  <span>Internal financial assessment</span>
                </div>
                <span class="client-badge client-badge--info">On Track</span>
              </div>

              <div class="client-summary-item">
                <div>
                  <strong>Required action</strong>
                  <span>Upload one signed quotation file</span>
                </div>
                <span class="client-badge client-badge--warning">Needs Action</span>
              </div>

              <div class="client-summary-item">
                <div>
                  <strong>Client success manager</strong>
                  <span>Dedicated support already assigned</span>
                </div>
                <span class="client-badge client-badge--success">Assigned</span>
              </div>
            </div>
          </aside>
        </div>

        <div class="client-grid-main">
          <div class="client-content-grid">
            <div id="requests" class="client-surface-card client-reveal-up">
              <div class="client-section-head">
                <div>
                  <span class="client-card-label">My Requests</span>
                  <h3>Track every finance request from one polished view.</h3>
                  <p>Each request card is designed to show status, progress, ownership, and next action clearly.</p>
                </div>
                <a href="#new-request" class="client-link-btn">Create another request</a>
              </div>

              <div class="client-request-list">
                <article v-for="item in requestItems" :key="item.id" class="client-request-card">
                  <div class="client-request-top">
                    <div>
                      <h4>{{ item.title }}</h4>
                      <span>{{ item.id }} · {{ item.updated }}</span>
                    </div>
                    <span class="client-badge" :class="`client-badge--${item.statusClass}`">{{ item.status }}</span>
                  </div>

                  <div class="client-meta-row">
                    <span class="client-meta-chip"><i class="fas fa-bolt"></i>{{ item.priority }}</span>
                    <span class="client-meta-chip"><i class="fas fa-layer-group"></i>{{ item.stage }}</span>
                    <span class="client-meta-chip"><i class="fas fa-user-check"></i>{{ item.owner }}</span>
                  </div>

                  <div class="client-progress-row">
                    <div class="client-progress-head">
                      <span>Workflow progress</span>
                      <strong>{{ item.progress }}%</strong>
                    </div>
                    <div class="client-progress-track">
                      <div class="client-progress-bar" :style="{ width: `${item.progress}%` }"></div>
                    </div>
                  </div>

                  <div class="client-request-footer">
                    <div class="client-inline-stats">
                      <span><strong>Next step:</strong> Review portal notes</span>
                      <span><strong>Channel:</strong> Client dashboard</span>
                    </div>
                    <a href="#support" class="client-link-btn">View details</a>
                  </div>
                </article>
              </div>
            </div>

            <div class="client-surface-card client-reveal-up">
              <div class="client-section-head">
                <div>
                  <span class="client-card-label">Workflow Timeline</span>
                  <h3>Live activity and milestone tracking.</h3>
                  <p>The client can immediately understand where the request stands without opening a separate admin-style table.</p>
                </div>
              </div>

              <div class="client-timeline">
                <article v-for="item in timelineItems" :key="item.title" class="client-timeline-item">
                  <div class="client-timeline-icon">
                    <i :class="item.icon"></i>
                  </div>
                  <div>
                    <h4>{{ item.title }}</h4>
                    <p>{{ item.description }}</p>
                    <span>{{ item.meta }}</span>
                  </div>
                </article>
              </div>
            </div>
          </div>

          <div class="client-side-grid">
            <div id="new-request" class="client-glass-card client-reveal-up">
              <span class="client-card-label">Quick Actions</span>
              <h3>Built for action, not confusion.</h3>
              <p>
                This block is intentionally designed as the future entry point for creating a request, uploading files,
                and starting new onboarding flows.
              </p>

              <div class="client-task-list">
                <article v-for="task in tasks" :key="task.title" class="client-task-item">
                  <div style="display:flex; align-items:center; gap:14px;">
                    <span class="client-check"><i class="fas fa-check"></i></span>
                    <div>
                      <h4>{{ task.title }}</h4>
                      <span>{{ task.description }}</span>
                    </div>
                  </div>
                  <span class="client-mini-tag">{{ task.tag }}</span>
                </article>
              </div>
            </div>

            <div id="documents" class="client-glass-card client-reveal-up">
              <span class="client-card-label">Documents</span>
              <h3>Visible, organized, and easy to follow.</h3>
              <div class="client-document-list">
                <article v-for="doc in documents" :key="doc.title" class="client-document-item">
                  <div style="display:flex; align-items:center; gap:14px;">
                    <span class="client-document-icon"><i class="fas fa-file-alt"></i></span>
                    <div>
                      <h4>{{ doc.title }}</h4>
                      <span>{{ doc.description }}</span>
                    </div>
                  </div>
                  <span class="client-mini-tag">{{ doc.tag }}</span>
                </article>
              </div>
            </div>

            <div id="support" class="client-glass-card client-reveal-up">
              <span class="client-card-label">Recent Activity</span>
              <h3>Advisor updates and client-side reminders.</h3>
              <div class="client-activity-list">
                <article v-for="activity in activities" :key="activity.title" class="client-activity-item">
                  <div style="display:flex; align-items:center; gap:14px;">
                    <span class="client-activity-icon"><i class="fas fa-bell"></i></span>
                    <div>
                      <h4>{{ activity.title }}</h4>
                      <span>{{ activity.description }}</span>
                    </div>
                  </div>
                  <span class="client-mini-tag">{{ activity.tag }}</span>
                </article>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </ClientPageShell>
</template>
