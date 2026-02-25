<template>
  <div class="space-y-5">

    <!-- Status Card -->
    <Card>
      <CardContent class="pt-6">
        <div class="flex flex-col sm:flex-row items-center gap-6">

          <!-- Indicator -->
          <div class="flex flex-col items-center gap-2">
            <div
              class="w-20 h-20 rounded-full flex items-center justify-center text-3xl shadow-lg transition-colors duration-300"
              :class="isCheckedIn
                ? 'bg-green-500/15 ring-4 ring-green-500/40'
                : 'bg-slate-700/40 ring-4 ring-slate-600/30'"
            >
              <BriefcaseBusiness
                class="w-9 h-9 transition-colors duration-300"
                :class="isCheckedIn ? 'text-green-400' : 'text-slate-500'"
              />
            </div>
            <span
              class="text-sm font-semibold"
              :class="isCheckedIn ? 'text-green-400' : 'text-slate-500'"
            >
              {{ isCheckedIn ? $t('work.atWork') : $t('work.notAtWork') }}
            </span>
          </div>

          <!-- Timer -->
          <div class="flex-1 flex flex-col items-center sm:items-start gap-3">
            <div class="text-4xl font-mono font-bold tabular-nums" :class="isCheckedIn ? 'text-black' : 'text-slate-600'">
              {{ timerDisplay }}
            </div>

            <!-- iOS Shortcut registration badge -->
            <div
              class="flex items-center gap-1.5 text-xs px-3 py-1 rounded-full"
              :class="shortcutRegistered
                ? 'text-indigo-400 bg-indigo-400/10'
                : 'text-slate-500 bg-slate-700/30'"
            >
              <Smartphone class="w-3.5 h-3.5" />
              <span v-if="shortcutRegistered">
                {{ $t('work.shortcutConnected') }}
                <span class="text-slate-500 ml-1">{{ shortcutRegisteredAt }}</span>
              </span>
              <span v-else>{{ $t('work.shortcutNotConnected') }}</span>
            </div>

            <!-- Warning badge -->
            <div
              v-if="isCheckedIn && elapsedSeconds > 16 * 3600"
              class="flex items-center gap-1.5 text-xs text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full"
            >
              <AlertTriangle class="w-3.5 h-3.5" />
              {{ $t('work.overHours') }}
            </div>

            <!-- Check-in time -->
            <p v-if="isCheckedIn && currentSession" class="text-sm text-muted-foreground">
              {{ $t('work.start') }}: {{ fmtTime(currentSession.checked_in_at) }}
            </p>
          </div>

          <!-- Action button -->
          <Button
            :variant="isCheckedIn ? 'destructive' : 'default'"
            size="lg"
            class="min-w-36"
            :disabled="actionLoading"
            @click="toggleWork"
          >
            <Loader2 v-if="actionLoading" class="w-4 h-4 mr-2 animate-spin" />
            <LogIn  v-else-if="!isCheckedIn" class="w-4 h-4 mr-2" />
            <LogOut v-else class="w-4 h-4 mr-2" />
            {{ isCheckedIn ? $t('work.checkOut') : $t('work.checkIn') }}
          </Button>

        </div>
      </CardContent>
    </Card>

    <!-- Today Card -->
    <Card v-if="todaySession">
      <CardHeader class="pb-2">
        <CardTitle class="text-base">{{ $t('work.today') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-3 gap-4 text-center">
          <div>
            <p class="text-xs text-muted-foreground mb-1">{{ $t('work.arrival') }}</p>
            <p class="font-semibold">{{ fmtTime(todaySession.checked_in_at) }}</p>
          </div>
          <div>
            <p class="text-xs text-muted-foreground mb-1">{{ $t('work.departure') }}</p>
            <p class="font-semibold">{{ todaySession.checked_out_at ? fmtTime(todaySession.checked_out_at) : '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-muted-foreground mb-1">{{ $t('work.total') }}</p>
            <p class="font-semibold">
              {{ todaySession.duration_minutes != null
                ? fmtDuration(todaySession.duration_minutes)
                : (isCheckedIn ? fmtDuration(Math.floor(elapsedSeconds / 60)) : '—') }}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <Card>
        <CardContent class="pt-5 pb-5">
          <p class="text-xs text-muted-foreground mb-1">{{ $t('work.thisWeek') }}</p>
          <p class="text-2xl font-bold">{{ fmtHours(stats.week_minutes) }}</p>
          <p class="text-xs text-muted-foreground mt-0.5">{{ $t('work.hours') }}</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent class="pt-5 pb-5">
          <p class="text-xs text-muted-foreground mb-1">{{ $t('work.thisMonth') }}</p>
          <p class="text-2xl font-bold">{{ fmtHours(stats.month_minutes) }}</p>
          <p class="text-xs text-muted-foreground mt-0.5">{{ $t('work.hours') }}</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent class="pt-5 pb-5">
          <p class="text-xs text-muted-foreground mb-1">{{ $t('work.avgShift') }}</p>
          <p class="text-2xl font-bold">{{ fmtDuration(stats.avg_minutes) }}</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent class="pt-5 pb-5">
          <p class="text-xs text-muted-foreground mb-1">{{ $t('work.totalShifts') }}</p>
          <p class="text-2xl font-bold">{{ stats.total_shifts }}</p>
        </CardContent>
      </Card>
    </div>

    <!-- History Card -->
    <Card>
      <CardHeader class="pb-3">
        <div class="flex items-center justify-between flex-wrap gap-2">
          <CardTitle class="text-base">{{ $t('work.history') }}</CardTitle>
          <!-- Filter tabs -->
          <div class="flex gap-1 bg-muted/40 p-0.5 rounded-lg">
            <button
              v-for="f in filters"
              :key="f.value"
              class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
              :class="activeFilter === f.value
                ? 'bg-background text-foreground shadow-sm'
                : 'text-muted-foreground hover:text-foreground'"
              @click="setFilter(f.value)"
            >
              {{ f.label }}
            </button>
          </div>
        </div>
      </CardHeader>
      <CardContent class="px-0 pb-0">
        <div v-if="sessionsLoading" class="flex justify-center py-8">
          <Loader2 class="w-6 h-6 animate-spin text-muted-foreground" />
        </div>

        <div v-else-if="sessions.length === 0" class="text-center text-sm text-muted-foreground py-8">
          {{ $t('work.noSessions') }}
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border text-xs text-muted-foreground">
                <th class="text-left px-4 py-2 font-medium">{{ $t('work.date') }}</th>
                <th class="text-left px-4 py-2 font-medium">{{ $t('work.arrival') }}</th>
                <th class="text-left px-4 py-2 font-medium">{{ $t('work.departure') }}</th>
                <th class="text-left px-4 py-2 font-medium">{{ $t('work.duration') }}</th>
                <th class="text-left px-4 py-2 font-medium">{{ $t('work.note') }}</th>
                <th class="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="s in sessions"
                :key="s.id"
                class="border-b border-border/50 hover:bg-muted/20 transition-colors group"
              >
                <td class="px-4 py-2.5 font-medium">{{ fmtDate(s.checked_in_at) }}</td>
                <td class="px-4 py-2.5 tabular-nums">{{ fmtTime(s.checked_in_at) }}</td>
                <td class="px-4 py-2.5 tabular-nums">{{ s.checked_out_at ? fmtTime(s.checked_out_at) : '—' }}</td>
                <td class="px-4 py-2.5">{{ s.duration_minutes != null ? fmtDuration(s.duration_minutes) : '—' }}</td>
                <td class="px-4 py-2.5 text-muted-foreground max-w-[180px] truncate">{{ s.note || '—' }}</td>
                <td class="px-4 py-2.5">
                  <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity justify-end">
                    <button
                      class="p-1.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                      @click="openEdit(s)"
                    >
                      <Pencil class="w-3.5 h-3.5" />
                    </button>
                    <button
                      class="p-1.5 rounded text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
                      @click="deleteSession(s)"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>

    <!-- Edit Dialog -->
    <Dialog v-model:open="editOpen">
      <DialogContent class="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>{{ $t('work.editShift') }}</DialogTitle>
        </DialogHeader>
        <div class="space-y-3 pt-1">
          <div>
            <label class="text-sm font-medium mb-1.5 block">{{ $t('work.arrival') }}</label>
            <input
              v-model="editForm.checked_in_at"
              type="datetime-local"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
          </div>
          <div>
            <label class="text-sm font-medium mb-1.5 block">{{ $t('work.departure') }}</label>
            <input
              v-model="editForm.checked_out_at"
              type="datetime-local"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
          </div>
          <div>
            <label class="text-sm font-medium mb-1.5 block">{{ $t('work.note') }}</label>
            <textarea
              v-model="editForm.note"
              rows="2"
              :placeholder="$t('work.noteOptional')"
              class="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
          </div>
          <div class="flex gap-2 justify-end pt-1">
            <Button variant="outline" @click="editOpen = false">{{ $t('common.cancel') }}</Button>
            <Button :disabled="editSaving" @click="saveEdit">
              <Loader2 v-if="editSaving" class="w-4 h-4 mr-2 animate-spin" />
              {{ $t('common.save') }}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>

  </div>
</template>

<script setup lang="ts">
import {
  BriefcaseBusiness,
  LogIn,
  LogOut,
  Loader2,
  Pencil,
  Trash2,
  AlertTriangle,
  Smartphone,
} from 'lucide-vue-next'

definePageMeta({ middleware: 'auth' })

const api            = useApi()
const { $t, locale } = useLocale()

// ── Types ─────────────────────────────────────────────────────────────────────

type Session = {
  id: number
  checked_in_at: string
  checked_out_at: string | null
  duration_minutes: number | null
  note: string | null
}

type Stats = {
  week_minutes: number
  month_minutes: number
  avg_minutes: number
  total_shifts: number
}

// ── State ─────────────────────────────────────────────────────────────────────

const isCheckedIn         = ref(false)
const currentSession      = ref<Session | null>(null)
const elapsedSeconds      = ref(0)
const actionLoading       = ref(false)
const shortcutRegistered  = ref(false)
const shortcutRegisteredAt = ref('')

const sessions        = ref<Session[]>([])
const sessionsLoading = ref(false)
const activeFilter    = ref<'week' | 'month' | 'all'>('week')

const stats = ref<Stats>({ week_minutes: 0, month_minutes: 0, avg_minutes: 0, total_shifts: 0 })

const filters = computed(() => [
  { label: $t('work.filterWeek'),  value: 'week'  as const },
  { label: $t('work.filterMonth'), value: 'month' as const },
  { label: $t('work.filterAll'),   value: 'all'   as const },
])

// ── Live timer ────────────────────────────────────────────────────────────────

let timerInterval: ReturnType<typeof setInterval> | null = null

const startTimer = () => {
  if (timerInterval) clearInterval(timerInterval)
  timerInterval = setInterval(() => {
    if (isCheckedIn.value) elapsedSeconds.value++
  }, 1000)
}

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval)
})

const timerDisplay = computed(() => {
  if (!isCheckedIn.value) return locale.value === 'en' ? '0h 00m 00s' : '0ч 00м 00с'
  const s = elapsedSeconds.value
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  const sec = s % 60
  return locale.value === 'en'
    ? `${h}h ${String(m).padStart(2, '0')}m ${String(sec).padStart(2, '0')}s`
    : `${h}ч ${String(m).padStart(2, '0')}м ${String(sec).padStart(2, '0')}с`
})

// ── Today's session ───────────────────────────────────────────────────────────

const todaySession = computed<Session | null>(() => {
  const today = new Date().toLocaleDateString('sv') // YYYY-MM-DD
  if (currentSession.value) return currentSession.value
  return sessions.value.find(s => s.checked_in_at.startsWith(today)) ?? null
})

// ── Load data ─────────────────────────────────────────────────────────────────

const loadStatus = async () => {
  const data = await api.getWorkStatus() as any
  isCheckedIn.value    = data.is_checked_in
  currentSession.value = data.session
  elapsedSeconds.value = (data.elapsed_minutes ?? 0) * 60
  shortcutRegistered.value   = data.shortcut_registered ?? false
  shortcutRegisteredAt.value = data.shortcut_registered_at
    ? new Date(data.shortcut_registered_at).toLocaleDateString(locale.value === 'en' ? 'en-US' : 'ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' })
    : ''
  if (isCheckedIn.value) startTimer()
}

const loadSessions = async () => {
  sessionsLoading.value = true
  try {
    sessions.value = (await api.getWorkSessions(activeFilter.value) as any) ?? []
  } finally {
    sessionsLoading.value = false
  }
}

const loadStats = async () => {
  stats.value = (await api.getWorkStats() as any) ?? stats.value
}

onMounted(async () => {
  await Promise.all([loadStatus(), loadSessions(), loadStats()])
})

// ── Filter ────────────────────────────────────────────────────────────────────

const setFilter = async (f: 'week' | 'month' | 'all') => {
  activeFilter.value = f
  await loadSessions()
}

// ── Toggle action ─────────────────────────────────────────────────────────────

const toggleWork = async () => {
  actionLoading.value = true
  try {
    if (isCheckedIn.value) {
      const s = await api.workCheckOut() as any
      isCheckedIn.value    = false
      currentSession.value = null
      elapsedSeconds.value = 0
      if (timerInterval) { clearInterval(timerInterval); timerInterval = null }
      // Update in list
      const idx = sessions.value.findIndex(x => x.id === s?.id)
      if (idx !== -1) sessions.value[idx] = s
    } else {
      const s = await api.workCheckIn() as any
      isCheckedIn.value    = true
      currentSession.value = s
      elapsedSeconds.value = 0
      startTimer()
      sessions.value.unshift(s)
    }
    await Promise.all([loadStats(), loadSessions()])
  } finally {
    actionLoading.value = false
  }
}

// ── Edit Dialog ───────────────────────────────────────────────────────────────

const editOpen   = ref(false)
const editSaving = ref(false)
const editTarget = ref<Session | null>(null)
const editForm   = reactive({ checked_in_at: '', checked_out_at: '', note: '' })

const toLocalDT = (iso: string | null | undefined) => {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const openEdit = (s: Session) => {
  editTarget.value         = s
  editForm.checked_in_at   = toLocalDT(s.checked_in_at)
  editForm.checked_out_at  = toLocalDT(s.checked_out_at)
  editForm.note            = s.note ?? ''
  editOpen.value           = true
}

const saveEdit = async () => {
  if (!editTarget.value) return
  editSaving.value = true
  try {
    const payload: any = {
      checked_in_at: editForm.checked_in_at || undefined,
      note:          editForm.note || null,
    }
    if (editForm.checked_out_at) {
      payload.checked_out_at = editForm.checked_out_at
    } else {
      payload.checked_out_at = null
    }
    const updated = await api.updateWorkSession(editTarget.value.id, payload) as any
    const idx = sessions.value.findIndex(s => s.id === editTarget.value!.id)
    if (idx !== -1) sessions.value[idx] = updated
    editOpen.value = false
    await loadStats()
  } finally {
    editSaving.value = false
  }
}

// ── Delete ────────────────────────────────────────────────────────────────────

const deleteSession = async (s: Session) => {
  if (!confirm($t('work.deleteShift'))) return
  await api.deleteWorkSession(s.id)
  sessions.value = sessions.value.filter(x => x.id !== s.id)
  await loadStats()
}

// ── Formatters ────────────────────────────────────────────────────────────────

const fmtDuration = (min: number | null) => {
  if (min == null) return '—'
  return locale.value === 'en'
    ? `${Math.floor(min / 60)}h ${min % 60}m`
    : `${Math.floor(min / 60)}ч ${min % 60}м`
}

const fmtHours = (min: number) => (min / 60).toFixed(1)

const fmtTime = (iso: string | null | undefined) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString(locale.value === 'en' ? 'en-US' : 'ru-RU', { hour: '2-digit', minute: '2-digit' })
}

const fmtDate = (iso: string | null | undefined) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(locale.value === 'en' ? 'en-US' : 'ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
</script>
