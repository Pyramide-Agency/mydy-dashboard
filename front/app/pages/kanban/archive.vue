<template>
  <div class="space-y-4">
    <div v-if="loading" class="flex items-center justify-center py-20">
      <Loader2 class="w-6 h-6 animate-spin text-muted-foreground" />
    </div>

    <div v-else-if="groups.length === 0" class="text-center py-20 text-muted-foreground">
      <ArchiveRestore class="w-12 h-12 mx-auto mb-3 opacity-30" />
      <p>{{ $t('kanban.archiveEmpty') }}</p>
    </div>

    <div v-else class="space-y-6">
      <div v-for="group in groups" :key="group.date">
        <div class="flex items-center gap-2 mb-3">
          <span class="text-sm font-semibold text-foreground">{{ formatDate(group.date) }}</span>
          <Badge variant="secondary">{{ group.tasks.length }}</Badge>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          <div
            v-for="task in group.tasks"
            :key="task.id"
            class="bg-card border border-border rounded-lg p-3 opacity-75"
          >
            <div class="flex items-start justify-between gap-2 mb-1">
              <h3 class="text-sm font-medium text-foreground">{{ task.title }}</h3>
              <Badge variant="secondary" class="text-xs capitalize shrink-0">
                {{ priorityLabel(task.priority) }}
              </Badge>
            </div>
            <p v-if="task.description" class="text-xs text-muted-foreground line-clamp-2">
              {{ task.description }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ArchiveRestore, Loader2 } from 'lucide-vue-next'

definePageMeta({ middleware: 'auth' })

const api          = useApi()
const { $t, locale } = useLocale()
const groups  = ref<any[]>([])
const loading = ref(true)

const priorityLabel = (p: string) => ({
  low: $t('kanban.low'), medium: $t('kanban.medium'), high: $t('kanban.high'),
} as Record<string, string>)[p] || p

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString(locale.value === 'en' ? 'en-US' : 'ru-RU', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

onMounted(async () => {
  try {
    groups.value = (await api.getArchived()) as any[]
  } finally {
    loading.value = false
  }
})
</script>
