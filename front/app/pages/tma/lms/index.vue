<template>
  <div class="p-4 space-y-4 pb-6">

    <!-- Skeleton -->
    <div v-if="initializing" class="space-y-3">
      <div v-for="i in 5" :key="i" class="h-16 bg-muted rounded-xl animate-pulse" />
    </div>

    <!-- Not configured -->
    <div v-else-if="!configured" class="flex flex-col items-center justify-center py-20 text-center gap-4">
      <div class="w-16 h-16 rounded-2xl bg-indigo-50 flex items-center justify-center">
        <GraduationCap class="w-8 h-8 text-indigo-500" />
      </div>
      <div>
        <p class="text-foreground font-semibold">{{ $t('lms.notConfigured') }}</p>
        <p class="text-muted-foreground text-sm mt-1 max-w-xs">{{ $t('lms.notConfiguredDesc') }}</p>
      </div>
    </div>

    <template v-else>

      <!-- Period tabs + Sync -->
      <div class="flex items-center justify-between gap-3">
        <div class="flex gap-1 p-1 bg-muted rounded-lg flex-1">
          <button
            v-for="p in periods"
            :key="p.key"
            class="flex-1 py-1.5 text-xs font-medium rounded-md transition-colors"
            :class="period === p.key ? 'bg-white text-foreground shadow-sm' : 'text-muted-foreground'"
            @click="period = p.key"
          >
            {{ $t(`lms.${p.label}`) }}
          </button>
        </div>
        <button
          class="p-2 rounded-lg border border-border bg-white text-muted-foreground transition-colors active:bg-muted"
          :disabled="syncing"
          @click="handleSync"
        >
          <RefreshCw class="w-4 h-4" :class="syncing ? 'animate-spin' : ''" />
        </button>
      </div>

      <!-- Deadlines loading -->
      <div v-if="loading" class="space-y-2">
        <div v-for="i in 4" :key="i" class="h-16 bg-muted rounded-xl animate-pulse" />
      </div>

      <!-- No deadlines -->
      <div v-else-if="!deadlines.length" class="flex flex-col items-center py-12 gap-3 text-center">
        <CheckCircle2 class="w-10 h-10 text-green-500/60" />
        <p class="text-muted-foreground text-sm">{{ $t('lms.noDeadlines') }}</p>
      </div>

      <!-- Deadlines list -->
      <div v-else class="space-y-2">
        <div
          v-for="item in deadlines"
          :key="item.id"
          class="bg-white rounded-xl border border-border p-3 flex items-center gap-3 shadow-sm"
        >
          <div
            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
            :style="{ backgroundColor: (item.course?.color || '#6366f1') + '18' }"
          >
            <component :is="typeIcon(item.assignment_type)" class="w-4 h-4" :style="{ color: item.course?.color || '#6366f1' }" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-foreground truncate">{{ item.name }}</p>
            <p class="text-xs text-muted-foreground truncate">{{ item.course?.name }}</p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-xs font-medium" :class="urgencyClass(item.due_at)">{{ formatDue(item.due_at) }}</p>
            <span v-if="item.submission" class="text-xs px-1.5 py-0.5 rounded-full" :class="submissionClass(item.submission)">
              {{ submissionLabel(item.submission) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Courses -->
      <div class="pt-2 border-t border-border">
        <h2 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">{{ $t('lms.courses') }}</h2>

        <div v-if="coursesLoading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-16 bg-muted rounded-xl animate-pulse" />
        </div>

        <div v-else-if="!courses.length" class="text-sm text-muted-foreground text-center py-6">
          {{ $t('lms.noCourses') }}
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="course in courses"
            :key="course.id"
            class="bg-white rounded-xl border border-border p-3 shadow-sm flex items-center gap-3"
          >
            <div class="w-1 self-stretch rounded-full shrink-0" :style="{ backgroundColor: course.color || '#6366f1' }" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-foreground truncate">{{ course.name }}</p>
              <p class="text-xs text-muted-foreground truncate">{{ course.course_code }}</p>
            </div>
            <div v-if="course.grade?.current_score != null" class="text-right shrink-0">
              <p class="text-sm font-bold" :style="{ color: course.color || '#6366f1' }">{{ course.grade.current_score }}%</p>
              <p v-if="course.grade.current_grade" class="text-xs text-muted-foreground">{{ course.grade.current_grade }}</p>
            </div>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup lang="ts">
import { GraduationCap, RefreshCw, CheckCircle2, FileText, MessageSquare, ClipboardCheck } from 'lucide-vue-next'
import { formatDistanceToNow, parseISO } from 'date-fns'
import { ru, enUS } from 'date-fns/locale'

definePageMeta({ layout: 'telegram', middleware: 'tma-auth' })

const api = useApi()
const { $t, locale } = useLocale()
const dfns = computed(() => locale.value === 'ru' ? ru : enUS)

const configured    = ref(false)
const deadlines     = ref<any[]>([])
const courses       = ref<any[]>([])
const loading       = ref(false)
const coursesLoading = ref(false)
const syncing       = ref(false)
const initializing  = ref(true)
const period        = ref<string>('week')

const periods = [
  { key: 'tomorrow', label: 'tomorrow' },
  { key: 'week',     label: 'thisWeek' },
  { key: 'month',    label: 'thisMonth' },
]

const fetchDeadlines = async () => {
  loading.value = true
  try { deadlines.value = await api.getLmsDeadlines(period.value) as any[] }
  finally { loading.value = false }
}

const fetchCourses = async () => {
  coursesLoading.value = true
  try { courses.value = await api.getLmsCourses() as any[] }
  finally { coursesLoading.value = false }
}

const handleSync = async () => {
  syncing.value = true
  try { await api.lmsSync(); await Promise.all([fetchDeadlines(), fetchCourses()]) }
  finally { syncing.value = false }
}

watch(period, fetchDeadlines)

onMounted(async () => {
  try {
    const status = await api.getLmsStatus() as any
    configured.value = status?.configured ?? false
    if (configured.value) await Promise.all([fetchDeadlines(), fetchCourses()])
  } finally {
    initializing.value = false
  }
})

const typeIcon = (type: string) => {
  if (type === 'quiz') return ClipboardCheck
  if (type === 'discussion') return MessageSquare
  return FileText
}

const formatDue = (d: string) => !d ? '' : formatDistanceToNow(parseISO(d), { addSuffix: true, locale: dfns.value })

const urgencyClass = (dueAt: string) => {
  if (!dueAt) return 'text-muted-foreground'
  const h = (new Date(dueAt).getTime() - Date.now()) / 3600000
  if (h < 3)  return 'text-red-500'
  if (h < 24) return 'text-orange-500'
  if (h < 72) return 'text-amber-500'
  return 'text-muted-foreground'
}

const submissionClass = (sub: any) => {
  if (sub?.state === 'graded')    return 'bg-green-100 text-green-700'
  if (sub?.state === 'submitted') return 'bg-blue-100 text-blue-700'
  if (sub?.missing)               return 'bg-red-100 text-red-700'
  if (sub?.late)                  return 'bg-orange-100 text-orange-700'
  return 'bg-muted text-muted-foreground'
}

const submissionLabel = (sub: any) => {
  if (sub?.state === 'graded')    return $t('lms.graded')
  if (sub?.state === 'submitted') return $t('lms.submitted')
  if (sub?.missing)               return $t('lms.missing')
  if (sub?.late)                  return $t('lms.late')
  return ''
}
</script>
