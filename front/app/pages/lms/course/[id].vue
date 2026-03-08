<template>
  <div v-if="loading" class="space-y-4">
    <div class="h-24 bg-muted rounded-xl animate-pulse" />
    <div v-for="i in 6" :key="i" class="h-14 bg-muted rounded-lg animate-pulse" />
  </div>

  <div v-else-if="data" class="space-y-5">

    <!-- Course header card -->
    <div
      class="relative overflow-hidden rounded-xl p-5 border border-border bg-card shadow-sm"
      :style="{ borderLeftWidth: '4px', borderLeftColor: data.course.color }"
    >
      <div class="flex items-start gap-4">
        <div
          class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
          :style="{ backgroundColor: data.course.color + '18' }"
        >
          <GraduationCap class="w-6 h-6" :style="{ color: data.course.color }" />
        </div>
        <div class="flex-1 min-w-0">
          <h1 class="text-base font-bold text-foreground">{{ data.course.name }}</h1>
          <p class="text-xs text-muted-foreground mt-0.5">{{ data.course.course_code }}</p>
          <p v-if="data.course.instructor" class="text-xs text-muted-foreground mt-1">{{ data.course.instructor }}</p>
        </div>
        <div v-if="data.course.grade?.current_score != null" class="text-right shrink-0">
          <p class="text-2xl font-bold" :style="{ color: data.course.color }">
            {{ data.course.grade.current_score }}%
          </p>
          <p class="text-xs text-muted-foreground">{{ data.course.grade.current_grade || $t('lms.currentScore') }}</p>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 p-1 bg-muted rounded-lg w-fit">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors flex items-center gap-1.5"
        :class="activeTab === tab.key ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
        @click="activeTab = tab.key"
      >
        {{ $t(`lms.${tab.label}`) }}
        <span v-if="tab.count > 0" class="text-xs bg-muted rounded-full px-1.5 py-0.5 leading-none">{{ tab.count }}</span>
      </button>
    </div>

    <!-- Timeline tab -->
    <div v-if="activeTab === 'timeline'">
      <div v-if="!timelineItems.length" class="text-center py-10 text-muted-foreground text-sm">
        {{ $t('lms.noEvents') }}
      </div>
      <div v-else class="space-y-0">
        <div
          v-for="(item, index) in timelineItems"
          :key="`tl-${item.type}-${item.id}`"
          class="relative flex gap-4 pb-4"
        >
          <!-- Timeline line -->
          <div class="flex flex-col items-center">
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 z-10 border border-border bg-card"
              :style="{ borderLeftColor: data.course.color }"
            >
              <component :is="item.icon" class="w-4 h-4" :style="{ color: data.course.color || '#6366f1' }" />
            </div>
            <div v-if="index < timelineItems.length - 1" class="w-0.5 flex-1 mt-1 bg-border" />
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0 pt-1 pb-2">
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-foreground">{{ item.title }}</p>
                <p v-if="item.location" class="text-xs text-muted-foreground mt-0.5">📍 {{ item.location }}</p>
                <p v-if="item.description" class="text-xs text-muted-foreground mt-0.5 line-clamp-2">{{ item.description }}</p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-xs text-muted-foreground">{{ formatDate(item.dateAt) }}</p>
                <p v-if="item.isDeadline" class="text-xs text-orange-500 font-medium">{{ $t('lms.dueAt') }}</p>
                <span
                  v-if="item.submission"
                  class="text-xs px-1.5 py-0.5 rounded-full mt-1 inline-block"
                  :class="submissionClass(item.submission)"
                >
                  {{ submissionLabel(item.submission) }}
                </span>
              </div>
            </div>
            <a
              v-if="item.html_url"
              :href="item.html_url"
              target="_blank"
              class="text-xs text-indigo-600 hover:text-indigo-700 flex items-center gap-1 mt-1"
            >
              <ExternalLink class="w-3 h-3" />
              {{ $t('lms.openInCanvas') }}
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Assignments tab -->
    <div v-if="activeTab === 'assignments'" class="space-y-2">
      <div v-if="!data.assignments.length" class="text-center py-10 text-muted-foreground text-sm">
        {{ $t('lms.noAssignments') }}
      </div>
      <div
        v-for="a in data.assignments"
        :key="a.id"
        class="group flex items-center gap-3 p-3 bg-card border border-border rounded-lg hover:shadow-sm transition-shadow"
      >
        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :style="{ backgroundColor: (data.course.color || '#6366f1') + '18' }">
          <component :is="typeIcon(a.assignment_type)" class="w-4 h-4" :style="{ color: data.course.color || '#6366f1' }" />
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-foreground truncate">{{ a.name }}</p>
          <p v-if="a.description" class="text-xs text-muted-foreground truncate mt-0.5">{{ a.description }}</p>
        </div>
        <span v-if="a.points_possible" class="text-xs text-muted-foreground shrink-0">{{ a.points_possible }} {{ $t('lms.points') }}</span>
        <span v-if="a.submission" class="text-xs px-2 py-0.5 rounded-full shrink-0" :class="submissionClass(a.submission)">
          {{ submissionLabel(a.submission) }}
        </span>
        <div v-if="a.due_at" class="text-right shrink-0">
          <p class="text-xs font-medium" :class="urgencyClass(a.due_at)">{{ formatDue(a.due_at) }}</p>
        </div>
        <a v-if="a.html_url" :href="a.html_url" target="_blank" class="text-muted-foreground hover:text-indigo-500 transition-colors opacity-0 group-hover:opacity-100 shrink-0">
          <ExternalLink class="w-3.5 h-3.5" />
        </a>
      </div>
    </div>

    <!-- Announcements tab -->
    <div v-if="activeTab === 'announcements'" class="space-y-3">
      <div v-if="!data.announcements.length" class="text-center py-10 text-muted-foreground text-sm">
        {{ $t('lms.noAnnouncements') }}
      </div>
      <div
        v-for="ann in data.announcements"
        :key="ann.id"
        class="p-4 bg-card border border-border rounded-lg shadow-sm"
        :class="!ann.read ? 'border-l-4' : ''"
        :style="!ann.read ? { borderLeftColor: data.course.color || '#6366f1' } : {}"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1">
            <p class="text-sm font-semibold text-foreground">{{ ann.title }}</p>
            <p v-if="ann.author" class="text-xs text-muted-foreground mt-0.5">{{ ann.author }}</p>
            <p v-if="ann.message" class="text-xs text-muted-foreground mt-2 line-clamp-3">{{ ann.message }}</p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-xs text-muted-foreground">{{ formatDate(ann.posted_at) }}</p>
          </div>
        </div>
        <div class="flex items-center gap-3 mt-3">
          <a v-if="ann.html_url" :href="ann.html_url" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
            <ExternalLink class="w-3 h-3" />
            {{ $t('lms.openInCanvas') }}
          </a>
          <button v-if="!ann.read" class="text-xs text-muted-foreground hover:text-foreground ml-auto" @click="markRead(ann)">
            {{ $t('lms.markAsRead') }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { GraduationCap, ExternalLink, FileText, MessageSquare, ClipboardCheck, Calendar } from 'lucide-vue-next'
import { format, parseISO, formatDistanceToNow } from 'date-fns'
import { ru, enUS } from 'date-fns/locale'

const api    = useApi()
const route  = useRoute()
const { $t, locale } = useLocale()
const dfns   = computed(() => locale.value === 'ru' ? ru : enUS)

const data      = ref<any>(null)
const loading   = ref(true)
const activeTab = ref<string>('timeline')
const courseId  = computed(() => Number(route.params.id))

const tabs = computed(() => [
  { key: 'timeline',      label: 'timeline',      count: timelineItems.value.length },
  { key: 'assignments',   label: 'assignments',   count: data.value?.assignments?.length ?? 0 },
  { key: 'announcements', label: 'announcements', count: data.value?.announcements?.filter((a: any) => !a.read).length ?? 0 },
])

const timelineItems = computed(() => {
  if (!data.value) return []
  const items: any[] = []
  for (const e of data.value.events || []) {
    items.push({ id: e.id, type: 'event', title: e.title, dateAt: e.start_at, description: e.description, location: e.location, html_url: e.html_url, icon: Calendar, isDeadline: false, submission: null })
  }
  for (const a of data.value.assignments || []) {
    if (!a.due_at) continue
    items.push({ id: a.id, type: 'assignment', title: a.name, dateAt: a.due_at, description: a.description, html_url: a.html_url, icon: typeIcon(a.assignment_type), isDeadline: true, submission: a.submission })
  }
  return items.sort((a, b) => new Date(a.dateAt).getTime() - new Date(b.dateAt).getTime())
})

const fetchData = async () => {
  loading.value = true
  try { data.value = await api.getLmsCourseTimeline(courseId.value) }
  finally { loading.value = false }
}
const markRead = async (ann: any) => { await api.markAnnouncementRead(ann.id); ann.read = true }
onMounted(fetchData)

const typeIcon = (type: string) => {
  if (type === 'quiz') return ClipboardCheck
  if (type === 'discussion') return MessageSquare
  return FileText
}
const formatDate = (d: string) => !d ? '' : format(parseISO(d), 'dd MMM, HH:mm', { locale: dfns.value })
const formatDue  = (d: string) => !d ? '' : formatDistanceToNow(parseISO(d), { addSuffix: true, locale: dfns.value })
const urgencyClass = (dueAt: string) => {
  const h = (new Date(dueAt).getTime() - Date.now()) / 3600000
  if (h < 0)  return 'text-red-500'
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
  if (sub?.state === 'graded')    return `${$t('lms.graded')} ${sub.grade ? `· ${sub.grade}` : ''}`
  if (sub?.state === 'submitted') return $t('lms.submitted')
  if (sub?.missing)               return $t('lms.missing')
  if (sub?.late)                  return $t('lms.late')
  return $t('lms.notSubmitted')
}
</script>

<style scoped>
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>
