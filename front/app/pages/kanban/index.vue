<template>
  <div class="space-y-4">
    <Card class="space-y-8 p-5">
      <!-- Board selector + actions -->
      <div class="flex items-center gap-3 flex-wrap">
        <Select v-model="activeBoardId" @update:model-value="loadBoard">
          <SelectTrigger class="w-48">
            <SelectValue :placeholder="$t('kanban.selectBoard')" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="b in boards"
              :key="b.id"
              :value="String(b.id)"
            >
              {{ b.name }}{{ b.is_default ? ' ★' : '' }}
            </SelectItem>
          </SelectContent>
        </Select>

        <Button variant="outline" size="sm" @click="showNewBoard = true">
          <Plus class="w-4 h-4 mr-1" />
          {{ $t('kanban.newBoard') }}
        </Button>

        <Button variant="outline" size="sm" @click="handleArchiveDone" :disabled="archiving">
          <Archive class="w-4 h-4 mr-1" />
          {{ archiving ? $t('kanban.archiving') : $t('kanban.archiveDone') }}
        </Button>

        <NuxtLink to="/kanban/archive">
          <Button variant="ghost" size="sm">
            <ArchiveRestore class="w-4 h-4 mr-1" />
            {{ $t('kanban.archive') }}
          </Button>
        </NuxtLink>
      </div>


      <!-- Kanban columns -->
      <div v-if="board" class="flex gap-4 overflow-x-auto pb-4">
        <KanbanColumn
          v-for="col in board.columns"
          :key="col.id"
          :column="col"
          :data-column-id="col.id"
          @add-task="openAddTask"
          @edit-task="openEditTask"
          @delete-task="confirmDeleteTask"
          @task-moved="refreshBoard"
        />
      </div>

      <div v-else-if="loading" class="flex items-center justify-center py-20">
        <Loader2 class="w-6 h-6 animate-spin text-muted-foreground" />
      </div>
    </Card>

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
import { Plus, Archive, ArchiveRestore, Loader2 } from 'lucide-vue-next'
import type { FormField } from '~/components/DynamicForm.vue'

definePageMeta({ middleware: 'auth' })

const api     = useApi()
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

const boardFields = computed((): FormField[] => [
  {
    key:         'name',
    label:       $t('kanban.title'),
    type:        'text',
    required:    true,
    placeholder: $t('kanban.newBoardName'),
  },
])

onMounted(async () => {
  await loadBoards()
})

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
  try {
    board.value = await api.getBoard(parseInt(activeBoardId.value))
  } finally {
    loading.value = false
  }
}

const refreshBoard = () => loadBoard()

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
