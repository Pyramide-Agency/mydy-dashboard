<template>
  <div
    :data-id="task.id"
    class="bg-card border border-border rounded-lg p-3 cursor-pointer hover:shadow-md transition-all hover:border-primary/30 group"
    @click="$emit('click', task)"
  >
    <div class="flex items-start justify-between gap-2 mb-2">
      <h3 class="text-sm font-medium text-foreground leading-snug line-clamp-2">{{ task.title }}</h3>
      <Badge :variant="priorityVariant" class="text-xs shrink-0 capitalize">
        {{ priorityLabel }}
      </Badge>
    </div>
    <p v-if="task.description" class="text-xs text-muted-foreground line-clamp-2 mb-2">
      {{ task.description }}
    </p>
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="text-xs text-muted-foreground">
          {{ formatDate(task.created_at) }}
        </span>
        <span
          v-if="task.deadline"
          class="flex items-center gap-1 text-xs font-medium px-1.5 py-0.5 rounded-md"
          :class="deadlineClass"
        >
          <CalendarClock class="w-3 h-3" />
          {{ formatDeadline(task.deadline) }}
        </span>
      </div>
      <button
        class="opacity-0 group-hover:opacity-100 transition-opacity text-muted-foreground hover:text-destructive"
        @click.stop="$emit('delete', task)"
      >
        <Trash2 class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Trash2, CalendarClock } from 'lucide-vue-next'

const props = defineProps<{ task: any }>()
defineEmits<{ click: [task: any]; delete: [task: any] }>()

const { $t } = useLocale()

const priorityMap: Record<string, { variant: any }> = {
  low:    { variant: 'secondary'   },
  medium: { variant: 'outline'     },
  high:   { variant: 'destructive' },
}

const priorityLabelMap = computed(() => ({
  low:    $t('kanban.low'),
  medium: $t('kanban.medium'),
  high:   $t('kanban.high'),
}))

const priorityLabel   = computed(() => priorityLabelMap.value[props.task.priority as keyof typeof priorityLabelMap.value] || $t('kanban.medium'))
const priorityVariant = computed(() => priorityMap[props.task.priority]?.variant || 'outline')

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('ru-RU', { day: '2-digit', month: 'short' })

const formatDeadline = (d: string) => {
  const date = new Date(d)
  const now = new Date()
  const isToday = date.toDateString() === now.toDateString()
  const tomorrow = new Date(now); tomorrow.setDate(tomorrow.getDate() + 1)
  const isTomorrow = date.toDateString() === tomorrow.toDateString()

  const timeStr = date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })

  if (isToday) return `сегодня ${timeStr}`
  if (isTomorrow) return `завтра ${timeStr}`
  return date.toLocaleDateString('ru-RU', { day: '2-digit', month: 'short' }) + ' ' + timeStr
}

const deadlineClass = computed(() => {
  if (!props.task.deadline) return ''
  const now = new Date()
  const deadline = new Date(props.task.deadline)
  const diffMs = deadline.getTime() - now.getTime()
  const diffHours = diffMs / (1000 * 60 * 60)

  if (diffHours < 0) return 'bg-destructive/10 text-destructive'
  if (diffHours <= 1) return 'bg-destructive/10 text-destructive'
  if (diffHours <= 3) return 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400'
  if (diffHours <= 12) return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
  return 'bg-muted text-muted-foreground'
})
</script>
