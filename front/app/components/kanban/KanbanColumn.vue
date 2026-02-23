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
}>()

const api = useApi()

const localTasks = ref<any[]>([...(props.column.tasks || [])])
watch(() => props.column.tasks, (t) => { localTasks.value = [...(t || [])] }, { deep: true })

const onDragEnd = async (event: any) => {
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
  emit('drag-end')
}
</script>

<style>
.dragging-ghost {
  opacity: 0.8;
  transform: rotate(2deg) scale(1.02);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

/* Prevent iOS long-press callout and text selection on drag handle */
.drag-handle {
  -webkit-touch-callout: none;
  -webkit-user-select: none;
  user-select: none;
}
</style>
