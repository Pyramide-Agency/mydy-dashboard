<template>
  <div class="p-4 space-y-4 pb-6">

    <!-- Check-in / Check-out button -->
    <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
      <div class="p-5 flex flex-col items-center gap-4">

        <!-- Status indicator -->
        <div class="flex items-center gap-2">
          <span
            class="w-2.5 h-2.5 rounded-full"
            :class="isCheckedIn ? 'bg-green-400 animate-pulse' : 'bg-slate-300'"
          />
          <span class="text-sm font-medium text-foreground">
            {{ isCheckedIn ? $t('work.atWork') : $t('work.notAtWork') }}
          </span>
        </div>

        <!-- Elapsed time -->
        <div v-if="isCheckedIn" class="text-center">
          <p class="text-4xl font-bold font-mono text-foreground tabular-nums">{{ elapsedDisplay }}</p>
          <p class="text-xs text-muted-foreground mt-1">{{ $t('work.arrival') }}: {{ checkedInTime }}</p>
        </div>

        <!-- Action button -->
        <button
          class="w-full py-4 rounded-xl text-white font-semibold text-base transition-all active:scale-95"
          :class="isCheckedIn
            ? 'bg-red-500 active:bg-red-600'
            : 'bg-indigo-500 active:bg-indigo-600'"
          :disabled="actionLoading"
          @click="toggleCheckIn"
        >
          <span v-if="actionLoading" class="flex items-center justify-center gap-2">
            <Loader2 class="w-5 h-5 animate-spin" />
            {{ isCheckedIn ? $t('tma.checkingOut') : $t('tma.checkingIn') }}
          </span>
          <span v-else>{{ isCheckedIn ? $t('work.checkOut') : $t('work.checkIn') }}</span>
        </button>

      </div>
    </div>

    <!-- Stats -->
    <div v-if="!statsLoading" class="grid grid-cols-3 gap-3">
      <div class="bg-white rounded-xl p-3 shadow-sm border border-border text-center">
        <p class="text-lg font-bold text-foreground">{{ formatHours(stats.week_minutes) }}</p>
        <p class="text-[10px] text-muted-foreground mt-0.5">{{ $t('work.thisWeek') }}</p>
      </div>
      <div class="bg-white rounded-xl p-3 shadow-sm border border-border text-center">
        <p class="text-lg font-bold text-foreground">{{ formatHours(stats.month_minutes) }}</p>
        <p class="text-[10px] text-muted-foreground mt-0.5">{{ $t('work.thisMonth') }}</p>
      </div>
      <div class="bg-white rounded-xl p-3 shadow-sm border border-border text-center">
        <p class="text-lg font-bold text-foreground">{{ formatHours(stats.avg_minutes) }}</p>
        <p class="text-[10px] text-muted-foreground mt-0.5">{{ $t('work.avgShift') }}</p>
      </div>
    </div>
    <div v-else class="grid grid-cols-3 gap-3">
      <div v-for="i in 3" :key="i" class="bg-white rounded-xl p-3 shadow-sm border border-border">
        <div class="skeleton h-6 w-12 mx-auto mb-1" />
        <div class="skeleton h-3 w-14 mx-auto" />
      </div>
    </div>

    <!-- Sessions -->
    <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
      <div class="px-4 py-3 border-b border-border flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Clock class="w-4 h-4 text-muted-foreground" />
          <h2 class="text-sm font-semibold text-foreground">{{ $t('tma.shiftHistory') }}</h2>
        </div>
        <div class="flex gap-1">
          <button
            v-for="f in filters"
            :key="f.key"
            class="text-xs px-2 py-1 rounded-md transition-colors"
            :class="filter === f.key ? 'bg-indigo-100 text-indigo-600 font-medium' : 'text-muted-foreground'"
            @click="setFilter(f.key)"
          >
            {{ f.label }}
          </button>
        </div>
      </div>

      <div v-if="sessionsLoading" class="p-4 space-y-3">
        <div v-for="i in 4" :key="i" class="flex items-center justify-between">
          <div class="skeleton h-3 w-28" />
          <div class="skeleton h-3 w-16" />
        </div>
      </div>

      <div v-else-if="sessions.length === 0" class="py-8 text-center">
        <BriefcaseBusiness class="w-8 h-8 text-muted-foreground/30 mx-auto mb-2" />
        <p class="text-sm text-muted-foreground">{{ $t('tma.noShifts') }}</p>
      </div>

      <div v-else class="divide-y divide-border">
        <div
          v-for="s in sessions"
          :key="s.id"
          class="flex items-center justify-between px-4 py-3"
        >
          <div>
            <p class="text-sm font-medium text-foreground">{{ formatDate(s.checked_in_at) }}</p>
            <p class="text-xs text-muted-foreground">
              {{ formatTime(s.checked_in_at) }}
              <span v-if="s.checked_out_at"> — {{ formatTime(s.checked_out_at) }}</span>
              <span v-else class="text-green-500"> — {{ $t('tma.now') }}</span>
            </p>
          </div>
          <span
            class="text-sm font-semibold"
            :class="s.checked_out_at ? 'text-foreground' : 'text-green-500'"
          >
            {{ s.duration_minutes ? formatHours(s.duration_minutes) : '—' }}
          </span>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { Loader2, Clock, BriefcaseBusiness } from 'lucide-vue-next'

definePageMeta({ layout: 'telegram', middleware: 'tma-auth' })

const api = useApi()
const { hapticFeedback } = useTelegram()
const { $t, locale } = useLocale()

const isCheckedIn   = ref(false)
const actionLoading = ref(false)
const statsLoading  = ref(true)
const sessionsLoading = ref(true)
const currentSession  = ref<any>(null)
const elapsedMinutes  = ref(0)
const checkedInTime   = ref('')
const filter          = ref<'week' | 'month'>('week')
const sessions        = ref<any[]>([])
const stats           = ref({ week_minutes: 0, month_minutes: 0, avg_minutes: 0, total_shifts: 0 })

const filters = computed(() => [
  { key: 'week',  label: $t('work.filterWeek') },
  { key: 'month', label: $t('work.filterMonth') },
])

let tickInterval: ReturnType<typeof setInterval> | null = null

const elapsedDisplay = computed(() => {
  const h = Math.floor(elapsedMinutes.value / 60)
  const m = elapsedMinutes.value % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
})

const formatHours = (minutes: number) => {
  if (!minutes) return locale.value === 'en' ? '0h' : '0ч'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return locale.value === 'en'
    ? (m > 0 ? `${h}h ${m}m` : `${h}h`)
    : (m > 0 ? `${h}ч ${m}м` : `${h}ч`)
}

const formatDate = (iso: string) => {
  return new Date(iso).toLocaleDateString(locale.value === 'en' ? 'en-US' : 'ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

const formatTime = (iso: string) => {
  return new Date(iso).toLocaleTimeString(locale.value === 'en' ? 'en-US' : 'ru-RU', { hour: '2-digit', minute: '2-digit' })
}

const startTick = () => {
  if (tickInterval) return
  tickInterval = setInterval(() => { elapsedMinutes.value++ }, 60_000)
}

const stopTick = () => {
  if (tickInterval) { clearInterval(tickInterval); tickInterval = null }
}

const loadStatus = async () => {
  const data = await api.getWorkStatus() as any
  isCheckedIn.value   = data?.is_checked_in ?? false
  elapsedMinutes.value = data?.elapsed_minutes ?? 0
  currentSession.value = data?.session ?? null
  if (isCheckedIn.value && currentSession.value?.checked_in_at) {
    checkedInTime.value = formatTime(currentSession.value.checked_in_at)
    startTick()
  } else {
    stopTick()
  }
}

const loadSessions = async () => {
  sessionsLoading.value = true
  try {
    sessions.value = (await api.getWorkSessions(filter.value)) as any[]
  } finally {
    sessionsLoading.value = false
  }
}

const loadStats = async () => {
  statsLoading.value = true
  try {
    stats.value = (await api.getWorkStats()) as any
  } finally {
    statsLoading.value = false
  }
}

const setFilter = (f: 'week' | 'month') => {
  filter.value = f
  loadSessions()
}

const toggleCheckIn = async () => {
  hapticFeedback('medium')
  actionLoading.value = true
  try {
    if (isCheckedIn.value) {
      await api.workCheckOut()
      isCheckedIn.value = false
      stopTick()
      elapsedMinutes.value = 0
      checkedInTime.value  = ''
      currentSession.value = null
      hapticFeedback('heavy')
    } else {
      const data = await api.workCheckIn() as any
      isCheckedIn.value    = true
      currentSession.value = data
      elapsedMinutes.value = 0
      checkedInTime.value  = formatTime(data?.checked_in_at || new Date().toISOString())
      startTick()
      hapticFeedback('heavy')
    }
    await Promise.all([loadSessions(), loadStats()])
  } catch {
    hapticFeedback('medium')
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadStatus(), loadSessions(), loadStats()])
})

onUnmounted(() => stopTick())
</script>
