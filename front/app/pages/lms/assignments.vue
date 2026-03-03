<template>
  <div class="space-y-5">

    <!-- Filters row -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex gap-1 p-1 bg-muted rounded-lg">
        <button
          v-for="f in filters"
          :key="f.key"
          class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
          :class="filter === f.key ? 'bg-white text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
          @click="filter = f.key"
        >
          {{ $t(`lms.${f.label}`) }}
        </button>
      </div>

      <select
        v-model="selectedCourse"
        class="bg-background border border-input text-foreground text-xs rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-ring"
      >
        <option value="">{{ $t('lms.courses') }}</option>
        <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 8" :key="i" class="h-14 bg-muted rounded-lg animate-pulse" />
    </div>

    <!-- Empty -->
    <div v-else-if="!assignments.length" class="flex flex-col items-center py-16 gap-3">
      <ClipboardList class="w-10 h-10 text-muted-foreground/40" />
      <p class="text-muted-foreground text-sm">{{ $t('lms.noAssignments') }}</p>
    </div>

    <!-- List grouped by course -->
    <div v-else class="space-y-4">
      <template v-for="group in grouped" :key="group.courseId">
        <div>
          <!-- Course header -->
          <div class="flex items-center gap-2 mb-2">
            <div class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ backgroundColor: group.color }" />
            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">{{ group.courseName }}</span>
          </div>

          <div class="space-y-1.5">
            <div
              v-for="item in group.items"
              :key="item.id"
              class="group flex items-center gap-3 p-3 bg-white border border-border rounded-lg hover:shadow-sm transition-shadow"
            >
              <!-- Type icon -->
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                :style="{ backgroundColor: group.color + '18' }"
              >
                <component :is="typeIcon(item.assignment_type)" class="w-4 h-4" :style="{ color: group.color }" />
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p class="text-sm font-medium text-foreground truncate">{{ item.name }}</p>
                  <span class="text-xs px-1.5 py-0.5 rounded bg-muted text-muted-foreground shrink-0">
                    {{ $t(`lms.${item.assignment_type}`) }}
                  </span>
                </div>
                <p v-if="item.description" class="text-xs text-muted-foreground truncate mt-0.5">{{ item.description }}</p>
              </div>

              <span v-if="item.points_possible" class="text-xs text-muted-foreground shrink-0">
                {{ item.points_possible }} {{ $t('lms.points') }}
              </span>

              <span
                v-if="item.submission"
                class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0"
                :class="submissionClass(item.submission)"
              >
                {{ submissionLabel(item.submission) }}
              </span>

              <div v-if="item.due_at" class="text-right shrink-0">
                <p class="text-xs font-medium" :class="urgencyClass(item.due_at)">
                  {{ formatDue(item.due_at) }}
                </p>
              </div>

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
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ClipboardList, ExternalLink, FileText, MessageSquare, ClipboardCheck } from 'lucide-vue-next'
import { formatDistanceToNow, parseISO } from 'date-fns'
import { ru, enUS } from 'date-fns/locale'

const api    = useApi()
const { $t, locale } = useLocale()
const dfns   = computed(() => locale.value === 'ru' ? ru : enUS)

const assignments    = ref<any[]>([])
const courses        = ref<any[]>([])
const loading        = ref(false)
const filter         = ref('upcoming')
const selectedCourse = ref('')

const filters = [
  { key: 'upcoming', label: 'upcoming' },
  { key: 'past',     label: 'past' },
  { key: 'all',      label: 'all' },
]

const grouped = computed(() => {
  const map = new Map<string, any>()
  for (const a of assignments.value) {
    const key = a.course?.id ?? 'unknown'
    if (!map.has(key)) {
      map.set(key, { courseId: key, courseName: a.course?.name ?? 'Unknown', color: a.course?.color ?? '#6366f1', items: [] })
    }
    map.get(key).items.push(a)
  }
  return Array.from(map.values())
})

const fetchData = async () => {
  loading.value = true
  try {
    const params: any = { filter: filter.value }
    if (selectedCourse.value) params.course_id = selectedCourse.value
    assignments.value = await api.getLmsAssignments(params) as any[]
  } finally { loading.value = false }
}

watch([filter, selectedCourse], fetchData)
onMounted(async () => {
  courses.value = await api.getLmsCourses() as any[]
  await fetchData()
})

const typeIcon = (type: string) => {
  if (type === 'quiz') return ClipboardCheck
  if (type === 'discussion') return MessageSquare
  return FileText
}
const formatDue = (d: string) => !d ? '' : formatDistanceToNow(parseISO(d), { addSuffix: true, locale: dfns.value })
const urgencyClass = (dueAt: string) => {
  const h = (new Date(dueAt).getTime() - Date.now()) / 3600000
  if (h < 0)  return 'text-red-500'
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
