<template>
  <div
    :data-id="task.id"
    class="bg-card border border-border rounded-lg p-3 hover:shadow-md transition-all hover:border-primary/30 group flex gap-2 select-none"
  >
    <!-- Drag handle -->
    <div
      class="drag-handle flex items-center shrink-0 cursor-grab active:cursor-grabbing touch-none select-none self-stretch -ml-1 pr-1"
      @click.stop
    >
      <GripVertical class="w-4 h-4 text-muted-foreground/40 group-hover:text-muted-foreground transition-colors" />
    </div>

    <!-- Card content -->
    <div class="flex-1 min-w-0 cursor-pointer" @click="$emit('click', task)">
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
      <span class="text-xs text-muted-foreground">
        {{ formatDate(task.created_at) }}
      </span>
      <button
        class="opacity-0 group-hover:opacity-100 transition-opacity text-muted-foreground hover:text-destructive"
        @click.stop="$emit('delete', task)"
      >
        <Trash2 class="w-3.5 h-3.5" />
      </button>
    </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Trash2, GripVertical } from 'lucide-vue-next'

const props = defineProps<{ task: any }>()
defineEmits<{ click: [task: any]; delete: [task: any] }>()

const priorityMap: Record<string, { label: string; variant: any }> = {
  low:    { label: 'Низкий',    variant: 'secondary' },
  medium: { label: 'Средний',   variant: 'outline'   },
  high:   { label: 'Высокий',   variant: 'destructive' },
}

const priorityLabel   = computed(() => priorityMap[props.task.priority]?.label || 'Средний')
const priorityVariant = computed(() => priorityMap[props.task.priority]?.variant || 'outline')

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('ru-RU', { day: '2-digit', month: 'short' })
</script>
