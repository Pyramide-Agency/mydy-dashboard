<template>
  <div class="flex flex-col bg-muted/40 rounded-xl w-72 shrink-0">
    <!-- Column header -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-border">
      <div class="flex items-center gap-2">
        <span
          class="w-2.5 h-2.5 rounded-full"
          :style="{ backgroundColor: statusColor }"
        />
        <h3 class="font-medium text-sm text-foreground">{{ column.name }}</h3>
        <Badge variant="secondary" class="text-xs font-normal ml-1">{{ column.tasks?.length || 0 }}</Badge>
      </div>
      <button
        class="text-muted-foreground hover:text-foreground transition-colors"
        @click="$emit('add-task', column)"
      >
        <Plus class="w-4 h-4" />
      </button>
    </div>

    <!-- Tasks list (draggable) -->
    <div class="flex-1 p-3 overflow-y-auto max-h-[calc(100vh-300px)]">
      <draggable
        v-model="localTasks"
        :group="{ name: 'tasks', pull: true, put: true }"
        item-key="id"
        class="min-h-16 space-y-2"
        handle=".drag-handle"
        :force-fallback="true"
        :delay="150"
        :delay-on-touch-only="true"
        :touch-start-threshold="3"
        fallback-class="dragging-ghost"
        @end="onDragEnd"
      >
        <template #item="{ element }">
          <TaskCard
            :task="element"
            @click="$emit('edit-task', element)"
            @delete="$emit('delete-task', element)"
          />
        </template>
      </draggable>
    </div>
  </div>
</template>

<script setup lang="ts">
import draggable from 'vuedraggable'
import { Plus } from 'lucide-vue-next'

const props = defineProps<{
  column: { id: number; name: string; status_key: string; tasks: any[] }
}>()

const emit = defineEmits<{
  'add-task': [col: any]
  'edit-task': [task: any]
  'delete-task': [task: any]
  'task-moved': [event: any]
}>()

const api = useApi()

const localTasks = ref<any[]>([...(props.column.tasks || [])])
watch(() => props.column.tasks, (t) => { localTasks.value = [...(t || [])] }, { deep: true })

const statusColors: Record<string, string> = {
  created:     '#6b7280',
  in_progress: '#f59e0b',
  done:        '#10b981',
}
const statusColor = computed(() => statusColors[props.column.status_key] || '#6b7280')

const onDragEnd = async (event: any) => {
  // event.from is the source column's draggable container
  // event.to is the target column's draggable container
  const taskEl = event.item
  const taskId = parseInt(taskEl.dataset.id || '0')
  if (!taskId) return

  const newCol = parseInt(event.to.closest('[data-column-id]')?.dataset?.columnId || String(props.column.id))
  const newPos = event.newIndex ?? 0

  try {
    await api.moveTask(taskId, {
      column_id: newCol || props.column.id,
      position:  newPos,
    })
  } catch (e) {
    console.error('Move failed', e)
  }

  emit('task-moved', event)
}
</script>

<style>
.dragging-ghost {
  opacity: 0.8;
  transform: rotate(2deg) scale(1.02);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}
</style>
