<template>
  <div class="space-y-5">

    <!-- Initial load skeleton — shown until first fetch completes -->
    <div v-if="initializing" class="space-y-3">
      <div class="h-8 bg-muted rounded-lg animate-pulse w-48" />
      <div v-for="i in 5" :key="i" class="h-16 bg-muted rounded-lg animate-pulse" />
    </div>

    <!-- Not configured banner -->
    <div v-else-if="!status?.configured" class="flex flex-col items-center justify-center py-20 text-center gap-4">
      <div class="w-16 h-16 rounded-2xl bg-indigo-50 flex items-center justify-center">
        <GraduationCap class="w-8 h-8 text-indigo-500" />
      </div>
      <div>
        <p class="text-foreground font-semibold text-lg">{{ $t('lms.notConfigured') }}</p>
        <p class="text-muted-foreground text-sm mt-1 max-w-sm">{{ $t('lms.notConfiguredDesc') }}</p>
      </div>
      <NuxtLink to="/settings">
        <Button variant="outline" size="sm">{{ $t('lms.goToSettings') }}</Button>
      </NuxtLink>
    </div>

    <template v-else-if="status?.configured">

      <!-- Header row: sync + last sync -->
      <div class="flex items-center justify-between">
        <p class="text-xs text-muted-foreground">
          {{ status?.last_sync ? formatSyncTime(status.last_sync) : $t('lms.neverSynced') }}
        </p>
        <Button size="sm" variant="outline" :disabled="syncing" @click="handleSync" class="gap-1.5">
          <RefreshCw class="w-3.5 h-3.5" :class="syncing ? 'animate-spin' : ''" />
          {{ syncing ? $t('lms.syncing') : $t('lms.sync') }}
        </Button>
      </div>

      <!-- Period tabs -->
      <div class="flex gap-1 p-1 bg-muted rounded-lg w-fit">
        <button
          v-for="p in periods"
          :key="p.key"
          class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
          :class="period === p.key ? 'bg-white text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
          @click="period = p.key"
        >
          {{ $t(`lms.${p.label}`) }}
        </button>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 5" :key="i" class="h-16 bg-muted rounded-lg animate-pulse" />
      </div>

      <!-- Empty state -->
      <div v-else-if="!deadlines.length" class="flex flex-col items-center py-16 gap-3 text-center">
        <CheckCircle2 class="w-10 h-10 text-green-500/60" />
        <p class="text-muted-foreground text-sm">{{ $t('lms.noDeadlines') }}</p>
      </div>

      <!-- Deadlines list -->
      <div v-else class="space-y-2">
        <div
          v-for="item in deadlines"
          :key="item.id"
          class="group flex items-center gap-3 p-3 bg-white border border-border rounded-lg hover:shadow-sm transition-shadow"
        >
          <!-- Type icon -->
          <div
            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
            :style="{ backgroundColor: (item.course?.color || '#6366f1') + '18' }"
          >
            <component :is="typeIcon(item.assignment_type)" class="w-4 h-4" :style="{ color: item.course?.color || '#6366f1' }" />
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-foreground truncate">{{ item.name }}</p>
            <div class="flex items-center gap-2 mt-0.5">
              <span class="text-xs text-muted-foreground truncate">{{ item.course?.name }}</span>
              <span v-if="item.points_possible" class="text-xs text-muted-foreground">·</span>
              <span v-if="item.points_possible" class="text-xs text-muted-foreground">{{ item.points_possible }} {{ $t('lms.points') }}</span>
            </div>
          </div>

          <!-- Submission status -->
          <div v-if="item.submission" class="shrink-0">
            <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="submissionClass(item.submission)">
              {{ submissionLabel(item.submission) }}
            </span>
          </div>

          <!-- Due date -->
          <div class="text-right shrink-0">
            <p class="text-xs font-medium" :class="urgencyClass(item.due_at)">
              {{ formatDue(item.due_at) }}
            </p>
            <p class="text-xs text-muted-foreground mt-0.5">{{ formatDate(item.due_at) }}</p>
          </div>

          <!-- Link -->
          <a
            v-if="item.html_url"
            :href="item.html_url"
            target="_blank"
            class="shrink-0 text-muted-foreground hover:text-indigo-500 transition-colors opacity-0 group-hover:opacity-100"
          >
            <ExternalLink class="w-3.5 h-3.5" />
          </a>
        </div>
      </div>

      <!-- Courses overview -->
      <div class="pt-2 border-t border-border">
        <h2 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">{{ $t('lms.courses') }}</h2>

        <div v-if="coursesLoading" class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <div v-for="i in 4" :key="i" class="h-20 bg-muted rounded-lg animate-pulse" />
        </div>

        <div v-else-if="!courses.length" class="text-sm text-muted-foreground text-center py-6">
          {{ $t('lms.noCourses') }}
        </div>

        <div v-else class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <NuxtLink
            v-for="course in courses"
            :key="course.id"
            :to="`/lms/course/${course.id}`"
            class="p-3 bg-white border border-border rounded-lg hover:shadow-sm transition-shadow group"
          >
            <div class="h-1 rounded-full mb-2" :style="{ backgroundColor: course.color }" />
            <p class="text-sm font-medium text-foreground truncate group-hover:text-indigo-600 transition-colors">
              {{ course.name }}
            </p>
            <p class="text-xs text-muted-foreground mt-0.5 truncate">{{ course.course_code }}</p>
            <div v-if="course.grade" class="mt-2 flex items-center gap-1.5">
              <span class="text-xs text-muted-foreground">{{ $t('lms.score') }}:</span>
              <span class="text-xs font-semibold text-indigo-600">{{ course.grade.current_score }}%</span>
              <span v-if="course.grade.current_grade" class="text-xs text-muted-foreground">({{ course.grade.current_grade }})</span>
            </div>
          </NuxtLink>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup lang="ts">
import { GraduationCap, RefreshCw, CheckCircle2, ExternalLink, FileText, MessageSquare, ClipboardCheck } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { formatDistanceToNow, parseISO, format } from 'date-fns'
import { ru, enUS } from 'date-fns/locale'

const api    = useApi()
const { $t, locale } = useLocale()
const dfns   = computed(() => locale.value === 'ru' ? ru : enUS)

const status         = ref<any>(null)
const deadlines      = ref<any[]>([])
const courses        = ref<any[]>([])
const loading        = ref(false)
const coursesLoading = ref(false)
const syncing        = ref(false)
const period         = ref<string>('week')
const initializing   = ref(true) // hides all content until first fetch

const periods = [
  { key: 'tomorrow', label: 'tomorrow' },
  { key: 'week',     label: 'thisWeek' },
  { key: 'month',    label: 'thisMonth' },
]

const fetchStatus   = async () => { status.value = await api.getLmsStatus() }
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
  try { await api.lmsSync(); await Promise.all([fetchDeadlines(), fetchCourses(), fetchStatus()]) }
  finally { syncing.value = false }
}

watch(period, fetchDeadlines)
onMounted(async () => {
  try {
    await fetchStatus()
    if (status.value?.configured) await Promise.all([fetchDeadlines(), fetchCourses()])
  } finally {
    initializing.value = false
  }
})

const typeIcon = (type: string) => {
  if (type === 'quiz') return ClipboardCheck
  if (type === 'discussion') return MessageSquare
  return FileText
}
const formatDue  = (d: string) => !d ? '' : formatDistanceToNow(parseISO(d), { addSuffix: true, locale: dfns.value })
const formatDate = (d: string) => !d ? '' : format(parseISO(d), 'dd MMM, HH:mm', { locale: dfns.value })
const formatSyncTime = (d: string) => formatDistanceToNow(parseISO(d), { addSuffix: true, locale: dfns.value })

const urgencyClass = (dueAt: string) => {
  if (!dueAt) return 'text-muted-foreground'
  const h = (new Date(dueAt).getTime() - Date.now()) / 3600000
  if (h < 3)  return 'text-red-500'
  if (h < 24) return 'text-orange-500'
  if (h < 72) return 'text-amber-500'
  return 'text-muted-foreground'
}
const submissionClass = (sub: any) => {
  if (sub.state === 'graded')    return 'bg-green-100 text-green-700'
  if (sub.state === 'submitted') return 'bg-blue-100 text-blue-700'
  if (sub.missing)               return 'bg-red-100 text-red-700'
  if (sub.late)                  return 'bg-orange-100 text-orange-700'
  return 'bg-muted text-muted-foreground'
}
const submissionLabel = (sub: any) => {
  if (sub.state === 'graded')    return `${$t('lms.graded')} ${sub.grade ? `· ${sub.grade}` : ''}`
  if (sub.state === 'submitted') return $t('lms.submitted')
  if (sub.missing)               return $t('lms.missing')
  if (sub.late)                  return $t('lms.late')
  return $t('lms.notSubmitted')
}
</script>
