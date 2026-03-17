<template>
  <div class="space-y-6">

    <!-- Active Timer Banner -->
    <div
      v-if="activeSession"
      class="rounded-xl border overflow-hidden animate-slide-up"
      style="background: rgb(99 102 241 / 0.08); border-color: rgb(99 102 241 / 0.3);"
    >
      <div class="px-5 py-4 flex items-center gap-4 flex-wrap">
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <Timer class="w-4 h-4 text-indigo-400 shrink-0 animate-pulse" />
            <span class="text-sm font-medium text-muted-foreground">{{ $t('freelance.activeTimer') }}</span>
          </div>
          <span
            class="w-2.5 h-2.5 rounded-full shrink-0"
            :style="{ backgroundColor: activeSession.project_color }"
          />
          <span class="text-sm font-semibold text-foreground truncate">{{ activeSession.project_name }}</span>
          <span
            v-if="activeSession.is_paused"
            class="text-xs px-2 py-0.5 rounded-full"
            style="background: rgb(245 158 11 / 0.15); color: #f59e0b;"
          >
            {{ $t('freelance.timerPaused') }}
          </span>
        </div>

        <!-- Clock display -->
        <div class="font-mono text-3xl font-bold text-foreground tracking-wider">
          {{ formatSeconds(elapsedSeconds) }}
        </div>

        <!-- Controls -->
        <div class="flex items-center gap-2">
          <!-- Pause/Resume -->
          <button
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
            style="background: rgb(245 158 11 / 0.12); color: #f59e0b;"
            @click="togglePause"
            :disabled="timerActionLoading"
          >
            <Pause v-if="!activeSession.is_paused" class="w-3.5 h-3.5" />
            <Play v-else class="w-3.5 h-3.5" />
            {{ activeSession.is_paused ? $t('freelance.resume') : $t('freelance.pause') }}
          </button>

          <!-- Stop -->
          <div class="flex items-center gap-2">
            <input
              v-if="showNoteInput"
              v-model="stopNote"
              :placeholder="$t('freelance.addNote')"
              class="h-8 px-3 text-sm rounded-lg border border-border bg-background text-foreground w-48"
              @keydown.enter="doStop"
            />
            <button
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
              style="background: rgb(239 68 68 / 0.12); color: #ef4444;"
              @click="handleStop"
              :disabled="timerActionLoading"
            >
              <Square class="w-3.5 h-3.5" />
              {{ $t('freelance.stop') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Projects grid -->
    <div>
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-semibold text-foreground">{{ $t('freelance.projects') }}</h2>
        <button
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
          style="background: rgb(99 102 241 / 0.12); color: rgb(129 140 248);"
          @click="openNewProjectDialog"
        >
          <Plus class="w-3.5 h-3.5" />
          {{ $t('freelance.newProject') }}
        </button>
      </div>

      <div v-if="projectsLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="i in 3" :key="i" class="bg-card rounded-xl p-5 border border-border">
          <div class="skeleton h-4 w-32 mb-3" />
          <div class="skeleton h-3 w-20 mb-4" />
          <div class="skeleton h-8 w-full" />
        </div>
      </div>

      <div v-else-if="projects.length === 0" class="bg-card rounded-xl border border-border p-10 text-center">
        <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center mx-auto mb-3">
          <Briefcase class="w-6 h-6 text-muted-foreground" />
        </div>
        <p class="text-sm font-medium text-foreground">{{ $t('freelance.noProjects') }}</p>
        <p class="text-xs text-muted-foreground mt-1">{{ $t('freelance.createFirstProject') }}</p>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="p in projects"
          :key="p.id"
          class="bg-card rounded-xl p-5 border border-border card-lift animate-slide-up"
        >
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2.5 min-w-0">
              <span
                class="w-3 h-3 rounded-full shrink-0"
                :style="{ backgroundColor: p.color }"
              />
              <span class="text-sm font-semibold text-foreground truncate">{{ p.name }}</span>
            </div>
            <div class="flex items-center gap-1 ml-2 shrink-0">
              <button
                class="p-1.5 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                @click="openEditProject(p)"
              >
                <Pencil class="w-3.5 h-3.5" />
              </button>
              <button
                class="p-1.5 rounded-md text-muted-foreground hover:text-red-400 hover:bg-red-500/10 transition-colors"
                @click="confirmDeleteProject(p)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <div v-if="p.deadline" class="mb-3">
            <span class="text-xs px-2 py-0.5 rounded-full bg-muted text-muted-foreground">
              {{ $t('freelance.deadline') }}: {{ p.deadline }}
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2 mb-4">
            <div class="bg-muted/50 rounded-lg p-2 text-center">
              <p class="text-xs text-muted-foreground mb-0.5">{{ $t('freelance.thisWeek') }}</p>
              <p class="text-sm font-semibold text-foreground">{{ formatHours(p.total_seconds_this_week) }}</p>
            </div>
            <div class="bg-muted/50 rounded-lg p-2 text-center">
              <p class="text-xs text-muted-foreground mb-0.5">{{ $t('freelance.thisMonth') }}</p>
              <p class="text-sm font-semibold text-foreground">{{ formatHours(p.total_seconds_this_month) }}</p>
            </div>
          </div>

          <button
            class="w-full flex items-center justify-center gap-1.5 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="getStartButtonClass(p)"
            :disabled="!!activeSession && !p.has_active_session"
            @click="handleStart(p)"
          >
            <Play class="w-3.5 h-3.5" />
            {{ $t('freelance.start') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Stats section -->
    <div class="bg-card rounded-xl border border-border overflow-hidden">
      <div class="px-5 py-4 border-b border-border">
        <div class="flex items-center gap-3">
          <h2 class="text-sm font-semibold text-foreground">{{ $t('freelance.totalHours') }}</h2>
          <div class="flex gap-1">
            <button
              class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors"
              :class="statsFilter === 'week' ? 'bg-indigo-500/20 text-indigo-400' : 'text-muted-foreground hover:bg-muted'"
              @click="setStatsFilter('week')"
            >
              {{ $t('freelance.thisWeek') }}
            </button>
            <button
              class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors"
              :class="statsFilter === 'month' ? 'bg-indigo-500/20 text-indigo-400' : 'text-muted-foreground hover:bg-muted'"
              @click="setStatsFilter('month')"
            >
              {{ $t('freelance.thisMonth') }}
            </button>
          </div>
        </div>
      </div>

      <div class="p-5">
        <div v-if="statsLoading" class="space-y-3">
          <div v-for="i in 3" :key="i" class="h-8 bg-muted rounded animate-pulse" />
        </div>
        <div v-else-if="!statsData.projects?.length" class="text-center py-6 text-sm text-muted-foreground">
          {{ $t('freelance.noSessions') }}
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="row in statsData.projects"
            :key="row.project_id"
            class="flex items-center gap-3"
          >
            <div class="flex items-center gap-2 w-36 shrink-0 min-w-0">
              <span
                class="w-2.5 h-2.5 rounded-full shrink-0"
                :style="{ backgroundColor: row.project_color }"
              />
              <span class="text-xs text-foreground truncate">{{ row.project_name }}</span>
            </div>
            <div class="flex-1 bg-muted rounded-full h-2 overflow-hidden">
              <div
                class="h-2 rounded-full transition-all duration-500"
                :style="{
                  width: statsData.grand_total > 0 ? (row.total_seconds / statsData.grand_total * 100) + '%' : '0%',
                  backgroundColor: row.project_color,
                }"
              />
            </div>
            <span class="text-xs font-medium text-foreground w-14 text-right shrink-0">
              {{ formatHours(row.total_seconds) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Sessions history -->
    <div class="bg-card rounded-xl border border-border overflow-hidden">
      <div class="px-5 py-4 border-b border-border flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-3 flex-wrap">
          <h2 class="text-sm font-semibold text-foreground">{{ $t('freelance.sessions') }}</h2>
          <!-- Filter tabs -->
          <div class="flex gap-1">
            <button
              v-for="f in sessionFilters"
              :key="f.value"
              class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors"
              :class="sessionsFilter === f.value ? 'bg-indigo-500/20 text-indigo-400' : 'text-muted-foreground hover:bg-muted'"
              @click="setSessionsFilter(f.value)"
            >
              {{ f.label }}
            </button>
          </div>
          <!-- Project filter -->
          <select
            v-model="sessionsProjectFilter"
            class="h-7 px-2 text-xs rounded-md border border-border bg-background text-foreground"
            @change="loadSessions"
          >
            <option value="">{{ $t('common.all') }}</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <button
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
            @click="handleExportCSV"
          >
            <Download class="w-3.5 h-3.5" />
            {{ $t('freelance.exportCsv') }}
          </button>
          <button
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
            style="background: rgb(99 102 241 / 0.12); color: rgb(129 140 248);"
            @click="openManualEntryDialog"
          >
            <Plus class="w-3.5 h-3.5" />
            {{ $t('freelance.addManualEntry') }}
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <div v-if="sessionsLoading" class="p-5 space-y-3">
          <div v-for="i in 4" :key="i" class="h-10 bg-muted rounded animate-pulse" />
        </div>
        <div v-else-if="sessions.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
          <p class="text-sm text-muted-foreground">{{ $t('freelance.noSessions') }}</p>
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="px-5 py-3 text-left text-xs font-medium text-muted-foreground">{{ $t('freelance.date') }}</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-muted-foreground">{{ $t('freelance.project') }}</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-muted-foreground">{{ $t('freelance.startTime') }}</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-muted-foreground">{{ $t('freelance.endTime') }}</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-muted-foreground">{{ $t('freelance.duration') }}</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-muted-foreground">{{ $t('freelance.note') }}</th>
              <th class="px-5 py-3 text-right text-xs font-medium text-muted-foreground">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr
              v-for="s in sessions"
              :key="s.id"
              class="hover:bg-muted/20 transition-colors"
            >
              <td class="px-5 py-3 text-foreground whitespace-nowrap">{{ formatDate(s.started_at) }}</td>
              <td class="px-5 py-3">
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: s.project_color }" />
                  <span class="text-foreground">{{ s.project_name }}</span>
                  <span
                    v-if="s.is_active"
                    class="text-xs px-1.5 py-0.5 rounded-full"
                    style="background: rgb(99 102 241 / 0.15); color: rgb(129 140 248);"
                  >
                    live
                  </span>
                </div>
              </td>
              <td class="px-5 py-3 text-foreground whitespace-nowrap font-mono text-xs">{{ formatTime(s.started_at) }}</td>
              <td class="px-5 py-3 text-muted-foreground whitespace-nowrap font-mono text-xs">{{ s.ended_at ? formatTime(s.ended_at) : '—' }}</td>
              <td class="px-5 py-3 text-foreground whitespace-nowrap font-mono text-xs">{{ s.duration_seconds != null ? formatHours(s.duration_seconds) : '—' }}</td>
              <td class="px-5 py-3 text-muted-foreground max-w-xs truncate">{{ s.note || '—' }}</td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-1">
                  <button
                    class="p-1.5 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                    @click="openEditSession(s)"
                  >
                    <Pencil class="w-3.5 h-3.5" />
                  </button>
                  <button
                    class="p-1.5 rounded-md text-muted-foreground hover:text-red-400 hover:bg-red-500/10 transition-colors"
                    @click="confirmDeleteSession(s)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Project Dialog ────────────────────────────────────────────────── -->
    <div
      v-if="projectDialog.open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      style="background: rgba(0,0,0,0.6);"
      @click.self="projectDialog.open = false"
    >
      <div class="bg-card border border-border rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-base font-semibold text-foreground">
          {{ projectDialog.editing ? $t('freelance.editProject') : $t('freelance.newProject') }}
        </h3>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">{{ $t('freelance.projectName') }}</label>
            <input
              v-model="projectDialog.name"
              class="w-full h-9 px-3 text-sm rounded-lg border border-border bg-background text-foreground"
              :placeholder="$t('freelance.projectName')"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">{{ $t('freelance.color') }}</label>
            <div class="flex items-center gap-3">
              <input
                v-model="projectDialog.color"
                type="color"
                class="h-9 w-16 rounded-lg border border-border bg-background cursor-pointer"
              />
              <input
                v-model="projectDialog.color"
                class="flex-1 h-9 px-3 text-sm rounded-lg border border-border bg-background text-foreground font-mono"
                placeholder="#6366f1"
              />
            </div>
            <div class="flex gap-1.5 py-5 items-center">
              <button
                v-for="c in presetColors"
                :key="c"
                class="w-6 h-6 rounded-full border-2 transition-all"
                :class="projectDialog.color === c ? 'border-white scale-110' : 'border-transparent'"
                :style="{ backgroundColor: c }"
                @click="projectDialog.color = c"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">{{ $t('freelance.deadline') }}</label>
            <input
              v-model="projectDialog.deadline"
              type="date"
              class="w-full h-9 px-3 text-sm rounded-lg border border-border bg-background text-foreground"
            />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button
            class="px-4 py-2 text-sm rounded-lg border border-border text-muted-foreground hover:bg-muted transition-colors"
            @click="projectDialog.open = false"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            class="px-4 py-2 text-sm rounded-lg font-medium transition-colors"
            style="background: rgb(99 102 241); color: white;"
            :disabled="!projectDialog.name.trim() || projectDialog.saving"
            @click="saveProject"
          >
            {{ projectDialog.saving ? $t('common.loading') + '...' : $t('common.save') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Manual Entry / Edit Session Dialog ────────────────────────────── -->
    <div
      v-if="sessionDialog.open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      style="background: rgba(0,0,0,0.6);"
      @click.self="sessionDialog.open = false"
    >
      <div class="bg-card border border-border rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-base font-semibold text-foreground">
          {{ sessionDialog.editing ? $t('common.edit') : $t('freelance.addManualEntry') }}
        </h3>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">{{ $t('freelance.project') }}</label>
            <select
              v-model="sessionDialog.project_id"
              class="w-full h-9 px-3 text-sm rounded-lg border border-border bg-background text-foreground"
            >
              <option value="">— {{ $t('freelance.project') }} —</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-muted-foreground mb-1">{{ $t('freelance.date') }}</label>
              <input
                v-model="sessionDialog.date"
                type="date"
                class="w-full h-9 px-3 text-sm rounded-lg border border-border bg-background text-foreground"
              />
            </div>
            <div />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-muted-foreground mb-1">{{ $t('freelance.startTime') }}</label>
              <input
                v-model="sessionDialog.start_time"
                type="time"
                class="w-full h-9 px-3 text-sm rounded-lg border border-border bg-background text-foreground"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-muted-foreground mb-1">{{ $t('freelance.endTime') }}</label>
              <input
                v-model="sessionDialog.end_time"
                type="time"
                class="w-full h-9 px-3 text-sm rounded-lg border border-border bg-background text-foreground"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">{{ $t('freelance.note') }}</label>
            <input
              v-model="sessionDialog.note"
              class="w-full h-9 px-3 text-sm rounded-lg border border-border bg-background text-foreground"
              :placeholder="$t('freelance.addNote')"
            />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button
            class="px-4 py-2 text-sm rounded-lg border border-border text-muted-foreground hover:bg-muted transition-colors"
            @click="sessionDialog.open = false"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            class="px-4 py-2 text-sm rounded-lg font-medium transition-colors"
            style="background: rgb(99 102 241); color: white;"
            :disabled="!sessionDialog.project_id || !sessionDialog.date || !sessionDialog.start_time || !sessionDialog.end_time || sessionDialog.saving"
            @click="saveSession"
          >
            {{ sessionDialog.saving ? $t('common.loading') + '...' : $t('common.save') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Delete Confirm Dialog ─────────────────────────────────────────── -->
    <div
      v-if="deleteDialog.open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      style="background: rgba(0,0,0,0.6);"
      @click.self="deleteDialog.open = false"
    >
      <div class="bg-card border border-border rounded-xl shadow-xl w-full max-w-sm p-6 space-y-4">
        <p class="text-sm text-foreground">{{ deleteDialog.message }}</p>
        <div class="flex justify-end gap-2">
          <button
            class="px-4 py-2 text-sm rounded-lg border border-border text-muted-foreground hover:bg-muted transition-colors"
            @click="deleteDialog.open = false"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            class="px-4 py-2 text-sm rounded-lg font-medium transition-colors"
            style="background: rgb(239 68 68 / 0.9); color: white;"
            @click="deleteDialog.action && deleteDialog.action()"
          >
            {{ $t('common.delete') }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { Play, Pause, Square, Plus, Pencil, Trash2, Download, Timer, Briefcase } from 'lucide-vue-next'

definePageMeta({ middleware: 'auth' })

const freelance = useFreelance()
const { $t } = useLocale()

// ── State ─────────────────────────────────────────────────────────────────

const projects       = ref<any[]>([])
const projectsLoading = ref(true)

const activeSession      = ref<any>(null)
const elapsedSeconds     = ref(0)
const timerActionLoading = ref(false)
const showNoteInput      = ref(false)
const stopNote           = ref('')

const sessions        = ref<any[]>([])
const sessionsLoading = ref(false)
const sessionsFilter  = ref('week')
const sessionsProjectFilter = ref<number | ''>('')

const statsData    = ref<any>({ projects: [], grand_total: 0 })
const statsLoading = ref(false)
const statsFilter  = ref<'week' | 'month'>('week')

let timerInterval: ReturnType<typeof setInterval> | null = null

const sessionFilters = computed(() => [
  { value: 'week',  label: $t('work.filterWeek') },
  { value: 'month', label: $t('work.filterMonth') },
  { value: 'all',   label: $t('work.filterAll') },
])

const presetColors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6']

// ── Dialogs ───────────────────────────────────────────────────────────────

const projectDialog = reactive({
  open: false,
  editing: false,
  editId: null as number | null,
  name: '',
  color: '#6366f1',
  deadline: '',
  saving: false,
})

const sessionDialog = reactive({
  open: false,
  editing: false,
  editId: null as number | null,
  project_id: '' as number | '',
  date: '',
  start_time: '',
  end_time: '',
  note: '',
  saving: false,
})

const deleteDialog = reactive({
  open: false,
  message: '',
  action: null as (() => void) | null,
})

// ── Lifecycle ─────────────────────────────────────────────────────────────

onMounted(async () => {
  await Promise.all([loadProjects(), loadActive(), loadStats()])
  await loadSessions()
})

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval)
})

// ── Timer tick ────────────────────────────────────────────────────────────

const startTick = () => {
  if (timerInterval) clearInterval(timerInterval)
  timerInterval = setInterval(() => {
    if (activeSession.value && !activeSession.value.is_paused) {
      elapsedSeconds.value++
    }
  }, 1000)
}

// ── Load data ─────────────────────────────────────────────────────────────

const loadProjects = async () => {
  projectsLoading.value = true
  try {
    projects.value = (await freelance.getProjects()) as any[]
  } catch {} finally {
    projectsLoading.value = false
  }
}

const loadActive = async () => {
  try {
    const data: any = await freelance.getActiveSession()
    activeSession.value = data || null
    if (activeSession.value) {
      elapsedSeconds.value = activeSession.value.elapsed_seconds || 0
      startTick()
    }
  } catch {}
}

const loadSessions = async () => {
  sessionsLoading.value = true
  try {
    const params: any = { filter: sessionsFilter.value }
    if (sessionsProjectFilter.value) params.project_id = sessionsProjectFilter.value
    sessions.value = (await freelance.getSessions(params)) as any[]
  } catch {} finally {
    sessionsLoading.value = false
  }
}

const loadStats = async () => {
  statsLoading.value = true
  try {
    statsData.value = (await freelance.getStats(statsFilter.value)) as any
  } catch {} finally {
    statsLoading.value = false
  }
}

// ── Timer controls ────────────────────────────────────────────────────────

const handleStart = async (project: any) => {
  if (timerActionLoading.value) return
  timerActionLoading.value = true
  try {
    const data: any = await freelance.startTimer(project.id)
    activeSession.value  = data
    elapsedSeconds.value = 0
    startTick()
    await Promise.all([loadProjects(), loadSessions()])
  } catch {} finally {
    timerActionLoading.value = false
  }
}

const handleStop = () => {
  if (!showNoteInput.value) {
    showNoteInput.value = true
    return
  }
  doStop()
}

const doStop = async () => {
  if (timerActionLoading.value) return
  timerActionLoading.value = true
  try {
    await freelance.stopTimer(stopNote.value || undefined)
    if (timerInterval) clearInterval(timerInterval)
    activeSession.value  = null
    elapsedSeconds.value = 0
    showNoteInput.value  = false
    stopNote.value       = ''
    await Promise.all([loadProjects(), loadSessions()])
  } catch {} finally {
    timerActionLoading.value = false
  }
}

const togglePause = async () => {
  if (timerActionLoading.value || !activeSession.value) return
  timerActionLoading.value = true
  try {
    if (activeSession.value.is_paused) {
      const data: any = await freelance.resumeTimer()
      activeSession.value = data
    } else {
      const data: any = await freelance.pauseTimer()
      activeSession.value = data
    }
  } catch {} finally {
    timerActionLoading.value = false
  }
}

// ── Project dialog ────────────────────────────────────────────────────────

const openNewProjectDialog = () => {
  projectDialog.open    = true
  projectDialog.editing = false
  projectDialog.editId  = null
  projectDialog.name    = ''
  projectDialog.color   = '#6366f1'
  projectDialog.deadline = ''
}

const openEditProject = (p: any) => {
  projectDialog.open    = true
  projectDialog.editing = true
  projectDialog.editId  = p.id
  projectDialog.name    = p.name
  projectDialog.color   = p.color
  projectDialog.deadline = p.deadline || ''
}

const saveProject = async () => {
  if (!projectDialog.name.trim() || projectDialog.saving) return
  projectDialog.saving = true
  try {
    const data = {
      name:     projectDialog.name.trim(),
      color:    projectDialog.color,
      deadline: projectDialog.deadline || null,
    }
    if (projectDialog.editing && projectDialog.editId) {
      await freelance.updateProject(projectDialog.editId, data)
    } else {
      await freelance.createProject(data)
    }
    projectDialog.open = false
    await loadProjects()
  } catch {} finally {
    projectDialog.saving = false
  }
}

const confirmDeleteProject = (p: any) => {
  deleteDialog.message = $t('freelance.deleteProjectConfirm')
  deleteDialog.action  = async () => {
    deleteDialog.open = false
    await freelance.deleteProject(p.id)
    await loadProjects()
    await loadSessions()
    await loadStats()
  }
  deleteDialog.open = true
}

// ── Session dialog ────────────────────────────────────────────────────────

const openManualEntryDialog = () => {
  const now = new Date()
  sessionDialog.open      = true
  sessionDialog.editing   = false
  sessionDialog.editId    = null
  sessionDialog.project_id = ''
  sessionDialog.date      = now.toISOString().split('T')[0]
  sessionDialog.start_time = ''
  sessionDialog.end_time   = ''
  sessionDialog.note       = ''
}

const openEditSession = (s: any) => {
  const start = new Date(s.started_at)
  const end   = s.ended_at ? new Date(s.ended_at) : null
  sessionDialog.open      = true
  sessionDialog.editing   = true
  sessionDialog.editId    = s.id
  sessionDialog.project_id = s.project_id
  sessionDialog.date      = start.toISOString().split('T')[0]
  sessionDialog.start_time = start.toTimeString().slice(0, 5)
  sessionDialog.end_time   = end ? end.toTimeString().slice(0, 5) : ''
  sessionDialog.note       = s.note || ''
}

const saveSession = async () => {
  if (!sessionDialog.project_id || !sessionDialog.date || !sessionDialog.start_time || !sessionDialog.end_time || sessionDialog.saving) return
  sessionDialog.saving = true
  try {
    const startedAt = `${sessionDialog.date}T${sessionDialog.start_time}:00`
    const endedAt   = `${sessionDialog.date}T${sessionDialog.end_time}:00`
    const data: any = {
      project_id: Number(sessionDialog.project_id),
      started_at: startedAt,
      ended_at:   endedAt,
      note:       sessionDialog.note || null,
    }
    if (sessionDialog.editing && sessionDialog.editId) {
      await freelance.updateSession(sessionDialog.editId, data)
    } else {
      await freelance.createSessionManual(data)
    }
    sessionDialog.open = false
    await Promise.all([loadSessions(), loadProjects(), loadStats()])
  } catch {} finally {
    sessionDialog.saving = false
  }
}

const confirmDeleteSession = (s: any) => {
  deleteDialog.message = $t('freelance.deleteConfirm')
  deleteDialog.action  = async () => {
    deleteDialog.open = false
    await freelance.deleteSession(s.id)
    await Promise.all([loadSessions(), loadProjects(), loadStats()])
  }
  deleteDialog.open = true
}

// ── Filter changes ────────────────────────────────────────────────────────

const setSessionsFilter = async (f: string) => {
  sessionsFilter.value = f
  await loadSessions()
}

const setStatsFilter = async (f: 'week' | 'month') => {
  statsFilter.value = f
  await loadStats()
}

// ── CSV export ────────────────────────────────────────────────────────────

const handleExportCSV = () => {
  const params: any = {}
  if (sessionsProjectFilter.value) params.project_id = sessionsProjectFilter.value
  freelance.exportCSV(params)
}

// ── Helpers ───────────────────────────────────────────────────────────────

const formatSeconds = (secs: number) => {
  const h = Math.floor(secs / 3600)
  const m = Math.floor((secs % 3600) / 60)
  const s = secs % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

const formatHours = (secs: number) => {
  if (!secs) return '0h 0m'
  const h = Math.floor(secs / 3600)
  const m = Math.floor((secs % 3600) / 60)
  return h > 0 ? `${h}h ${m}m` : `${m}m`
}

const formatDate = (iso: string) => {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const formatTime = (iso: string) => {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

const getStartButtonClass = (p: any) => {
  if (p.has_active_session) {
    return 'opacity-50 cursor-not-allowed bg-muted text-muted-foreground'
  }
  if (activeSession.value && !p.has_active_session) {
    return 'opacity-40 cursor-not-allowed bg-muted text-muted-foreground'
  }
  return 'bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500/20'
}
</script>
