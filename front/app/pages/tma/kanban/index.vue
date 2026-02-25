<template>
  <div
    class="flex flex-col bg-background"
    style="height: calc(100dvh - 60px - env(safe-area-inset-bottom, 0px)); overflow: hidden;"
  >
    <!-- Top: board selector + archive -->
    <div class="shrink-0 px-4 pt-3 pb-3 space-y-2 bg-white border-b border-border">
      <div class="flex items-center gap-2">
        <Select v-model="activeBoardId" @update:model-value="loadBoard" class="flex-1">
          <SelectTrigger>
            <SelectValue :placeholder="$t('kanban.selectBoard')" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="b in boards" :key="b.id" :value="String(b.id)">
              {{ b.name }}{{ b.is_default ? ' ★' : '' }}
            </SelectItem>
          </SelectContent>
        </Select>
        <Button variant="outline" size="sm" @click="showNewBoard = true">
          <Plus class="w-4 h-4" />
        </Button>
        <NuxtLink to="/tma/kanban/archive">
          <Button variant="ghost" size="sm">
            <ArchiveRestore class="w-4 h-4" />
          </Button>
        </NuxtLink>
      </div>
      <Button variant="outline" size="sm" class="w-full" @click="handleArchiveDone" :disabled="archiving">
        <Archive class="w-4 h-4 mr-1" />
        {{ archiving ? $t('kanban.archiving') : $t('kanban.archiveDone') }}
      </Button>
    </div>

    <!-- Column navigation header -->
    <div v-if="board" class="shrink-0 flex items-center justify-between px-1 h-11 bg-white border-b border-border">
      <Button variant="ghost" size="icon" :disabled="activeIndex === 0" @click="goLeft">
        <ChevronLeft class="w-5 h-5" />
      </Button>
      <div class="flex items-center gap-2 min-w-0">
        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: currentColumnColor }" />
        <span class="text-sm font-semibold truncate">{{ currentColumn?.name }}</span>
        <Badge variant="secondary" class="text-xs font-normal shrink-0">
          {{ currentColumn?.tasks?.length ?? 0 }}
        </Badge>
      </div>
      <Button variant="ghost" size="icon" :disabled="activeIndex === (board.columns.length - 1)" @click="goRight">
        <ChevronRight class="w-5 h-5" />
      </Button>
    </div>

    <!-- Snap-scrolling columns -->
    <div
      v-if="board"
      ref="scrollContainer"
      class="snap-columns flex-1 flex overflow-x-auto overflow-y-hidden"
      style="scroll-snap-type: x mandatory; scrollbar-width: none; -webkit-overflow-scrolling: touch;"
      @scroll.passive="onScroll"
    >
      <div
        v-for="col in board.columns"
        :key="col.id"
        :data-column-id="col.id"
        class="shrink-0 h-full"
        style="width: 100%; scroll-snap-align: start;"
      >
        <KanbanColumn
          :column="col"
          @add-task="openAddTask"
          @edit-task="openEditTask"
          @delete-task="confirmDeleteTask"
          @task-moved="refreshBoard"
          @drag-start="startDrag"
          @drag-end="stopDrag"
          @drop-on-zone="handleDropOnZone"
        />
      </div>
    </div>

    <!-- Loading state -->
    <div v-else-if="loading" class="flex-1 flex items-center justify-center">
      <Loader2 class="w-6 h-6 animate-spin text-muted-foreground" />
    </div>

    <!-- Dot indicators -->
    <div v-if="board" class="shrink-0 flex items-center justify-center gap-2 py-2.5 bg-white border-t border-border">
      <button
        v-for="(col, i) in board.columns"
        :key="col.id"
        class="rounded-full transition-all duration-200"
        :class="i === activeIndex ? 'w-5 h-2 bg-primary' : 'w-2 h-2 bg-muted-foreground/30'"
        @click="scrollToColumn(Number(i))"
      />
    </div>

    <!-- Drag zone overlays (shown while dragging) -->
    <template v-if="isDragging && board">
      <Transition name="zone">
        <div
          v-if="activeIndex > 0"
          class="fixed inset-y-0 left-0 w-[22%] z-50 flex flex-col items-center justify-center gap-2 pointer-events-none transition-colors duration-150"
          :class="isInLeftZone ? 'bg-primary/35' : 'bg-black/18'"
        >
          <ChevronLeft class="w-7 h-7 text-white" style="filter: drop-shadow(0 1px 2px rgba(0,0,0,.4))" />
          <span class="text-[10px] font-semibold text-white text-center px-1 leading-tight"
                style="filter: drop-shadow(0 1px 2px rgba(0,0,0,.4)); word-break: break-word;">
            {{ prevColData?.name }}
          </span>
        </div>
      </Transition>
      <Transition name="zone">
        <div
          v-if="activeIndex < board.columns.length - 1"
          class="fixed inset-y-0 right-0 w-[22%] z-50 flex flex-col items-center justify-center gap-2 pointer-events-none transition-colors duration-150"
          :class="isInRightZone ? 'bg-primary/35' : 'bg-black/18'"
        >
          <span class="text-[10px] font-semibold text-white text-center px-1 leading-tight"
                style="filter: drop-shadow(0 1px 2px rgba(0,0,0,.4)); word-break: break-word;">
            {{ nextColData?.name }}
          </span>
          <ChevronRight class="w-7 h-7 text-white" style="filter: drop-shadow(0 1px 2px rgba(0,0,0,.4))" />
        </div>
      </Transition>
    </template>

    <!-- Task modal -->
    <TaskModal
      v-model:open="taskModalOpen"
      :task="selectedTask"
      :column-id="selectedColumnId"
      :board-id="activeBoardId ? parseInt(activeBoardId) : undefined"
      @saved="onTaskSaved"
    />

    <!-- New board dialog -->
    <Dialog v-model:open="showNewBoard">
      <DialogContent class="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>{{ $t('kanban.newBoard') }}</DialogTitle>
        </DialogHeader>
        <DynamicForm
          v-model="newBoardForm"
          :fields="boardFields"
          :submit-label="$t('kanban.create')"
          @submit="createBoard"
        />
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { Plus, Archive, ArchiveRestore, Loader2, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import type { FormField } from '~/components/DynamicForm.vue'

definePageMeta({ layout: 'telegram', middleware: 'tma-auth' })

const api = useApi()
const { $t } = useLocale()

const boards           = ref<any[]>([])
const board            = ref<any>(null)
const activeBoardId    = ref<string>('')
const loading          = ref(false)
const archiving        = ref(false)
const showNewBoard     = ref(false)
const newBoardForm     = ref<Record<string, any>>({ name: '' })
const taskModalOpen    = ref(false)
const selectedTask     = ref<any>(null)
const selectedColumnId = ref<number | undefined>()

// ── Snap-scroll ────────────────────────────────────────────────────────
const scrollContainer = ref<HTMLElement | null>(null)
const activeIndex     = ref(0)

const statusColors: Record<string, string> = {
  created:     '#6b7280',
  in_progress: '#f59e0b',
  done:        '#10b981',
}

const currentColumn      = computed(() => board.value?.columns?.[activeIndex.value])
const currentColumnColor = computed(() => statusColors[currentColumn.value?.status_key] || '#6b7280')
const prevColData        = computed(() => board.value?.columns?.[activeIndex.value - 1])
const nextColData        = computed(() => board.value?.columns?.[activeIndex.value + 1])

const onScroll = () => {
  const c = scrollContainer.value
  if (!c) return
  activeIndex.value = Math.round(c.scrollLeft / c.clientWidth)
}

const scrollToColumn = (index: number) => {
  const c = scrollContainer.value
  if (!c) return
  c.scrollTo({ left: index * c.clientWidth, behavior: 'smooth' })
}

const goLeft  = () => { if (activeIndex.value > 0) scrollToColumn(activeIndex.value - 1) }
const goRight = () => {
  if (board.value && activeIndex.value < board.value.columns.length - 1)
    scrollToColumn(activeIndex.value + 1)
}

// ── Drag zone tracking ─────────────────────────────────────────────────
const ZONE_W      = 0.22
const isDragging  = ref(false)
const pointerX    = ref(-1)

const isInLeftZone  = computed(() =>
  isDragging.value && pointerX.value >= 0 && pointerX.value < window.innerWidth * ZONE_W
)
const isInRightZone = computed(() =>
  isDragging.value && pointerX.value > window.innerWidth * (1 - ZONE_W)
)

const trackPointer = (e: TouchEvent) => {
  if (e.touches[0]) pointerX.value = e.touches[0].clientX
}

const startDrag = () => {
  isDragging.value = true
  pointerX.value   = -1
  window.addEventListener('touchmove', trackPointer, { passive: true })
}

const stopDrag = () => {
  isDragging.value = false
  pointerX.value   = -1
  window.removeEventListener('touchmove', trackPointer)
}

const handleDropOnZone = async ({ taskId, direction }: { taskId: number; direction: 'left' | 'right' }) => {
  const targetCol = direction === 'left' ? prevColData.value : nextColData.value
  if (!targetCol) return
  const targetIdx = direction === 'left' ? activeIndex.value - 1 : activeIndex.value + 1
  try {
    await api.moveTask(taskId, { column_id: targetCol.id, position: 0 })
    await refreshBoard()
    scrollToColumn(targetIdx)
  } catch (e) {
    console.error('Zone move failed', e)
  }
}

onUnmounted(() => stopDrag())

// ── Board data ─────────────────────────────────────────────────────────
const boardFields = computed((): FormField[] => [
  { key: 'name', label: $t('kanban.boardName'), type: 'text', required: true, placeholder: $t('kanban.newBoardName') },
])

onMounted(async () => { await loadBoards() })

const loadBoards = async () => {
  const res: any = await api.getBoards()
  boards.value = res
  const def = res.find((b: any) => b.is_default) || res[0]
  if (def) {
    activeBoardId.value = String(def.id)
    await loadBoard()
  }
}

const loadBoard = async () => {
  if (!activeBoardId.value) return
  loading.value = true
  activeIndex.value = 0
  try {
    board.value = await api.getBoard(parseInt(activeBoardId.value))
    await nextTick()
    scrollContainer.value?.scrollTo({ left: 0, behavior: 'instant' as ScrollBehavior })
  } finally {
    loading.value = false
  }
}

const refreshBoard = async () => {
  if (!activeBoardId.value) return
  const idx = activeIndex.value
  loading.value = true
  try {
    board.value = await api.getBoard(parseInt(activeBoardId.value))
    await nextTick()
    if (scrollContainer.value) {
      scrollContainer.value.scrollTo({
        left: idx * scrollContainer.value.clientWidth,
        behavior: 'instant' as ScrollBehavior,
      })
    }
    activeIndex.value = idx
  } finally {
    loading.value = false
  }
}

const openAddTask = (col: any) => {
  selectedTask.value     = null
  selectedColumnId.value = col.id
  taskModalOpen.value    = true
}

const openEditTask = (task: any) => {
  selectedTask.value     = task
  selectedColumnId.value = task.column_id
  taskModalOpen.value    = true
}

const confirmDeleteTask = async (task: any) => {
  if (!confirm(`${$t('kanban.deleteTask')} "${task.title}"?`)) return
  await api.deleteTask(task.id)
  await refreshBoard()
}

const onTaskSaved = () => refreshBoard()

const handleArchiveDone = async () => {
  archiving.value = true
  try {
    const res: any = await api.archiveDone(parseInt(activeBoardId.value))
    alert(`${$t('kanban.archivedCount')} ${res.archived_count}`)
    await refreshBoard()
  } finally {
    archiving.value = false
  }
}

const createBoard = async (data: Record<string, any>) => {
  await api.createBoard({ name: data.name })
  newBoardForm.value = { name: '' }
  showNewBoard.value = false
  await loadBoards()
}
</script>

<style>
.snap-columns::-webkit-scrollbar {
  display: none;
}
.zone-enter-active,
.zone-leave-active {
  transition: opacity 0.15s ease;
}
.zone-enter-from,
.zone-leave-to {
  opacity: 0;
}
</style>
