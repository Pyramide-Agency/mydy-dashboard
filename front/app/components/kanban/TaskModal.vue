<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ task ? $t('kanban.editTask') : $t('kanban.newTask') }}</DialogTitle>
      </DialogHeader>

      <DynamicForm
        v-model="form"
        :fields="taskFields"
        hide-submit
        ref="formRef"
      />

      <div class="flex gap-2 justify-end pt-2">
        <Button type="button" variant="outline" @click="$emit('update:open', false)">
          {{ $t('common.cancel') }}
        </Button>
        <Button @click="handleSubmit" :disabled="loading">
          <Loader2 v-if="loading" class="w-4 h-4 mr-2 animate-spin" />
          {{ task ? $t('kanban.save') : $t('kanban.create') }}
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'
import type { FormField } from '~/components/DynamicForm.vue'

const props = defineProps<{
  open: boolean
  task?: any | null
  columnId?: number
  boardId?: number
}>()

const emit = defineEmits<{
  'update:open': [val: boolean]
  saved: [task: any]
}>()

const api     = useApi()
const { $t } = useLocale()
const loading = ref(false)
const formRef = ref()

// datetime-local works in local time, but backend stores UTC.
// Convert UTC ISO string → "YYYY-MM-DDTHH:mm" in local time for the input.
const utcToLocal = (iso: string): string => {
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

// Convert "YYYY-MM-DDTHH:mm" local time string → UTC ISO string for the backend.
const localToUtc = (local: string): string => new Date(local).toISOString()

const form = ref({
  title:       '',
  description: '',
  priority:    'medium',
  deadline:    '',
})

const taskFields = computed((): FormField[] => [
  {
    key:         'title',
    label:       $t('kanban.title'),
    type:        'text',
    required:    true,
    placeholder: $t('kanban.titlePlaceholder'),
  },
  {
    key:         'description',
    label:       $t('kanban.description'),
    type:        'textarea',
    placeholder: $t('kanban.descriptionPlaceholder'),
    rows:        3,
  },
  {
    key:     'priority',
    label:   $t('kanban.priority'),
    type:    'select',
    options: [
      { label: $t('kanban.low'),    value: 'low'    },
      { label: $t('kanban.medium'), value: 'medium' },
      { label: $t('kanban.high'),   value: 'high'   },
    ],
  },
  {
    key:   'deadline',
    label: $t('kanban.deadline'),
    type:  'datetime',
  },
])

watch(() => props.open, (val) => {
  if (val) {
    form.value = {
      title:       props.task?.title       || '',
      description: props.task?.description || '',
      priority:    props.task?.priority    || 'medium',
      deadline:    props.task?.deadline    ? utcToLocal(props.task.deadline) : '',
    }
    formRef.value?.reset()
  }
})

const handleSubmit = async () => {
  if (!formRef.value?.validate()) return

  loading.value = true
  try {
    let saved
    const deadline = form.value.deadline ? localToUtc(form.value.deadline) : null
    if (props.task) {
      saved = await api.updateTask(props.task.id, {
        title:       form.value.title,
        description: form.value.description || null,
        priority:    form.value.priority,
        deadline,
      })
    } else {
      saved = await api.createTask({
        board_id:    props.boardId,
        column_id:   props.columnId,
        title:       form.value.title,
        description: form.value.description || null,
        priority:    form.value.priority,
        deadline,
      })
    }
    emit('saved', saved)
    emit('update:open', false)
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}
</script>
