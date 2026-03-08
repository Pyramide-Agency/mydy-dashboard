<template>
  <div class="space-y-5">

    <!-- Month navigation -->
    <div class="flex items-center justify-between">
      <button
        class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
        @click="prevMonth"
      >
        <ChevronLeft class="w-4 h-4" />
      </button>
      <h2 class="text-sm font-semibold text-foreground capitalize">{{ monthTitle }}</h2>
      <button
        class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
        @click="nextMonth"
      >
        <ChevronRight class="w-4 h-4" />
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="grid grid-cols-7 gap-px">
      <div v-for="i in 35" :key="i" class="h-20 bg-muted animate-pulse rounded" />
    </div>

    <template v-else>
      <!-- Calendar grid -->
      <div class="grid grid-cols-7 gap-px bg-border rounded-xl overflow-hidden border border-border">
        <!-- Day headers -->
        <div
          v-for="day in dayHeaders"
          :key="day"
          class="bg-muted py-2 text-center text-xs font-semibold text-muted-foreground uppercase"
        >
          {{ day }}
        </div>

        <!-- Days -->
        <div
          v-for="cell in cells"
          :key="cell.key"
          class="bg-background min-h-[80px] p-1.5"
          :class="cell.isToday ? 'bg-indigo-50/60' : ''"
        >
          <span
            class="text-xs font-medium w-5 h-5 flex items-center justify-center rounded-full"
            :class="[
              cell.isCurrentMonth ? 'text-foreground' : 'text-muted-foreground/40',
              cell.isToday ? 'bg-indigo-600 text-white' : '',
            ]"
          >
            {{ cell.day }}
          </span>
          <div class="mt-0.5 space-y-0.5">
            <div
              v-for="event in cell.events.slice(0, 3)"
              :key="event.id"
              class="text-xs px-1 py-0.5 rounded truncate cursor-pointer hover:opacity-80 transition-opacity font-medium"
              :style="{ backgroundColor: (event.color || '#6366f1') + '20', color: event.color || '#4f46e5' }"
              :title="event.title"
              @click="selectedEvent = event"
            >
              {{ event.title }}
            </div>
            <div v-if="cell.events.length > 3" class="text-xs text-muted-foreground px-1">
              +{{ cell.events.length - 3 }} {{ locale === 'ru' ? 'ещё' : 'more' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Upcoming events list -->
      <div class="border-t border-border pt-4">
        <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">
          {{ $t('lms.thisMonth') }}
        </h3>

        <div v-if="!allEvents.length" class="text-sm text-muted-foreground text-center py-6">
          {{ $t('lms.noEvents') }}
        </div>

        <div v-else class="space-y-1.5">
          <div
            v-for="event in allEvents"
            :key="`ev-${event.type}-${event.id}`"
            class="flex items-center gap-3 p-2.5 rounded-lg bg-card border border-border hover:shadow-sm transition-shadow"
          >
            <div class="w-1 self-stretch rounded-full shrink-0" :style="{ backgroundColor: event.color || '#6366f1' }" />
            <div class="flex-1 min-w-0">
              <p class="text-xs font-medium text-foreground truncate">{{ event.title }}</p>
              <p v-if="event.courseName" class="text-xs text-muted-foreground truncate">{{ event.courseName }}</p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-xs text-muted-foreground">{{ formatEventDate(event.dateAt) }}</p>
              <p v-if="event.isDeadline" class="text-xs text-orange-500 font-medium">{{ $t('lms.dueAt') }}</p>
            </div>
            <a
              v-if="event.html_url"
              :href="event.html_url"
              target="_blank"
              class="text-muted-foreground hover:text-indigo-500 transition-colors shrink-0"
            >
              <ExternalLink class="w-3.5 h-3.5" />
            </a>
          </div>
        </div>
      </div>
    </template>

    <!-- Event detail modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="selectedEvent"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
          @click.self="selectedEvent = null"
        >
          <div class="bg-background border border-border rounded-xl p-5 max-w-sm w-full shadow-lg space-y-3">
            <div class="flex items-start justify-between gap-2">
              <h3 class="text-sm font-semibold text-foreground">{{ selectedEvent.title }}</h3>
              <button class="text-muted-foreground hover:text-foreground shrink-0" @click="selectedEvent = null">
                <X class="w-4 h-4" />
              </button>
            </div>
            <p v-if="selectedEvent.description" class="text-xs text-muted-foreground">{{ selectedEvent.description }}</p>
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <CalendarDays class="w-3.5 h-3.5" />
              <span>{{ formatEventDate(selectedEvent.dateAt) }}</span>
            </div>
            <a
              v-if="selectedEvent.html_url"
              :href="selectedEvent.html_url"
              target="_blank"
              class="flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-700"
            >
              <ExternalLink class="w-3.5 h-3.5" />
              {{ $t('lms.openInCanvas') }}
            </a>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ChevronLeft, ChevronRight, ExternalLink, X, CalendarDays } from 'lucide-vue-next'
import { format, parseISO, startOfMonth, startOfWeek, addDays, isSameMonth, isToday, isSameDay } from 'date-fns'
import { ru, enUS } from 'date-fns/locale'

const api    = useApi()
const { $t, locale } = useLocale()
const dfns   = computed(() => locale.value === 'ru' ? ru : enUS)

const now           = new Date()
const currentYear   = ref(now.getFullYear())
const currentMonth  = ref(now.getMonth() + 1)
const loading       = ref(false)
const calendarData  = ref<any>(null)
const selectedEvent = ref<any>(null)

const dayHeaders = computed(() => locale.value === 'ru'
  ? ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']
  : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])

const monthTitle = computed(() => {
  const d = new Date(currentYear.value, currentMonth.value - 1, 1)
  return format(d, 'LLLL yyyy', { locale: dfns.value })
})

const allEventsForMonth = computed(() => {
  if (!calendarData.value) return []
  const events: any[] = []
  for (const e of calendarData.value.events || []) {
    events.push({ id: e.id, type: 'event', title: e.title, dateAt: e.start_at, description: e.description, color: e.course?.color, courseName: e.course?.name, html_url: e.html_url, isDeadline: false })
  }
  for (const a of calendarData.value.assignments || []) {
    events.push({ id: a.id, type: 'assignment', title: a.name, dateAt: a.due_at, description: a.description, color: a.course?.color || '#f59e0b', courseName: a.course?.name, html_url: a.html_url, isDeadline: true })
  }
  return events.sort((a, b) => new Date(a.dateAt).getTime() - new Date(b.dateAt).getTime())
})

const allEvents = allEventsForMonth

const cells = computed(() => {
  const year  = currentYear.value
  const month = currentMonth.value - 1
  const first = startOfMonth(new Date(year, month, 1))
  const weekStart = startOfWeek(first, { weekStartsOn: 1 })
  const result = []
  for (let i = 0; i < 42; i++) {
    const d = addDays(weekStart, i)
    const dayEvents = allEventsForMonth.value.filter(e => e.dateAt && isSameDay(parseISO(e.dateAt), d))
    result.push({ key: d.toISOString(), day: d.getDate(), isCurrentMonth: isSameMonth(d, new Date(year, month)), isToday: isToday(d), events: dayEvents })
  }
  while (result.length > 7 && result.slice(-7).every(c => !c.isCurrentMonth)) result.splice(-7)
  return result
})

const fetchCalendar = async () => {
  loading.value = true
  try { calendarData.value = await api.getLmsCalendar(currentMonth.value, currentYear.value) }
  finally { loading.value = false }
}
const prevMonth = () => {
  if (currentMonth.value === 1) { currentMonth.value = 12; currentYear.value-- } else currentMonth.value--
  fetchCalendar()
}
const nextMonth = () => {
  if (currentMonth.value === 12) { currentMonth.value = 1; currentYear.value++ } else currentMonth.value++
  fetchCalendar()
}
const formatEventDate = (d: string) => !d ? '' : format(parseISO(d), 'dd MMM, HH:mm', { locale: dfns.value })

onMounted(fetchCalendar)
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
