<template>
  <div class="flex flex-col h-full bg-muted/20">
    <!-- Scrollable task list -->
    <div class="flex-1 p-3 overflow-y-auto">
      <draggable
        v-model="localTasks"
        :group="{ name: 'tasks', pull: true, put: true }"
        item-key="id"
        class="space-y-2 min-h-[200px]"
        handle=".drag-handle"
        :force-fallback="true"
        :touch-start-threshold="3"
        fallback-class="dragging-ghost"
        @start="emit('drag-start')"
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

    <!-- Add task button -->
    <div class="shrink-0 p-3 border-t border-border bg-background">
      <button
        class="w-full flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors py-1"
        @click="$emit('add-task', column)"
      >
        <Plus class="w-4 h-4" />
        Добавить задачу
      </button>
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
  'drag-start': []
  'drag-end': []
  'drop-on-zone': [payload: { taskId: number; direction: 'left' | 'right' }]
}>()

const api    = useApi()
const ZONE_W = 0.22

const localTasks = ref<any[]>([...(props.column.tasks || [])])
watch(() => props.column.tasks, (t) => { localTasks.value = [...(t || [])] }, { deep: true })

const onDragEnd = async (event: any) => {
  emit('drag-end')

  const taskEl = event.item
  const taskId = parseInt(taskEl.dataset.id || '0')
  if (!taskId) return

  // Get final touch X from the native event
  const orig   = event.originalEvent
  const finalX = orig?.changedTouches?.[0]?.clientX ?? orig?.clientX ?? -1
  const w      = window.innerWidth

  // Dropped on left zone → move to previous column
  if (finalX >= 0 && finalX < w * ZONE_W) {
    localTasks.value = [...(props.column.tasks || [])]
    emit('drop-on-zone', { taskId, direction: 'left' })
    return
  }

  // Dropped on right zone → move to next column
  if (finalX >= 0 && finalX > w * (1 - ZONE_W)) {
    localTasks.value = [...(props.column.tasks || [])]
    emit('drop-on-zone', { taskId, direction: 'right' })
    return
  }

  // Normal within-column reorder
  const newPos = event.newIndex ?? 0
  try {
    await api.moveTask(taskId, { column_id: props.column.id, position: newPos })
  } catch (e) {
    console.error('Move failed', e)
  }
  emit('task-moved', event)
}
</script>

<style>
/* The card being dragged (SortableJS fallback clone) */
.dragging-ghost {
  opacity: 0.9;
  transform: scale(1.03) rotate(1deg);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  pointer-events: none;
}

/* The placeholder left in the original position */
.sortable-ghost {
  opacity: 0 !important;
}

/* Prevent iOS callout and text selection on handle */
.drag-handle {
  -webkit-touch-callout: none;
  -webkit-user-select: none;
  user-select: none;
}
</style>
