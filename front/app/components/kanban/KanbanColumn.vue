<template>
  <div
    class="flex flex-col rounded-xl"
    :class="tma ? 'bg-background h-full' : 'bg-muted/40 w-72 shrink-0'"
  >
    <!-- Column header -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-border shrink-0">
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
    <div class="flex-1 p-3 overflow-y-auto" :class="tma ? '' : 'max-h-[calc(100vh-300px)]'">
      <draggable
        v-model="localTasks"
        :group="{ name: 'tasks', pull: true, put: true }"
        item-key="id"
        class="min-h-16 space-y-2"
        @start="onDragStart"
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
  tma?: boolean
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

const api = useApi()

const localTasks = ref<any[]>([...(props.column.tasks || [])])
watch(() => props.column.tasks, (t) => { localTasks.value = [...(t || [])] }, { deep: true })

const statusColors: Record<string, string> = {
  created:     '#6b7280',
  in_progress: '#f59e0b',
  done:        '#10b981',
}
const statusColor = computed(() => statusColors[props.column.status_key] || '#6b7280')

// Track pointer position during drag for zone detection
let dragTaskId = 0
const ZONE_W = 0.22

const onDragStart = (event: any) => {
  const taskEl = event.item as HTMLElement
  dragTaskId = parseInt(taskEl.dataset.id || '0')
  emit('drag-start')

  if (props.tma) {
    window.addEventListener('touchend', onTouchEnd, { once: true })
    window.addEventListener('mouseup', onMouseUp, { once: true })
  }
}

const checkZoneDrop = (clientX: number) => {
  if (!props.tma || !dragTaskId) return false
  const w = window.innerWidth
  if (clientX < w * ZONE_W) {
    emit('drop-on-zone', { taskId: dragTaskId, direction: 'left' })
    return true
  }
  if (clientX > w * (1 - ZONE_W)) {
    emit('drop-on-zone', { taskId: dragTaskId, direction: 'right' })
    return true
  }
  return false
}

const onTouchEnd = (e: TouchEvent) => {
  const touch = e.changedTouches[0]
  if (touch) checkZoneDrop(touch.clientX)
}

const onMouseUp = (e: MouseEvent) => {
  checkZoneDrop(e.clientX)
}

const onDragEnd = async (event: any) => {
  emit('drag-end')

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
