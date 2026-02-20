<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ task ? 'Редактировать задачу' : 'Новая задача' }}</DialogTitle>
      </DialogHeader>

      <DynamicForm
        v-model="form"
        :fields="taskFields"
        hide-submit
        ref="formRef"
      />

      <div class="flex gap-2 justify-end pt-2">
        <Button type="button" variant="outline" @click="$emit('update:open', false)">
          Отмена
        </Button>
        <Button @click="handleSubmit" :disabled="loading">
          <Loader2 v-if="loading" class="w-4 h-4 mr-2 animate-spin" />
          {{ task ? 'Сохранить' : 'Создать' }}
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
const loading = ref(false)
const formRef = ref()

const form = ref({
  title:       '',
  description: '',
  priority:    'medium',
})

const taskFields: FormField[] = [
  {
    key:         'title',
    label:       'Название',
    type:        'text',
    required:    true,
    placeholder: 'Название задачи',
  },
  {
    key:         'description',
    label:       'Описание',
    type:        'textarea',
    placeholder: 'Опишите задачу...',
    rows:        3,
  },
  {
    key:     'priority',
    label:   'Приоритет',
    type:    'select',
    options: [
      { label: 'Низкий',  value: 'low'    },
      { label: 'Средний', value: 'medium' },
      { label: 'Высокий', value: 'high'   },
    ],
  },
]

watch(() => props.open, (val) => {
  if (val) {
    form.value = {
      title:       props.task?.title       || '',
      description: props.task?.description || '',
      priority:    props.task?.priority    || 'medium',
    }
    formRef.value?.reset()
  }
})

const handleSubmit = async () => {
  if (!formRef.value?.validate()) return

  loading.value = true
  try {
    let saved
    if (props.task) {
      saved = await api.updateTask(props.task.id, {
        title:       form.value.title,
        description: form.value.description || null,
        priority:    form.value.priority,
      })
    } else {
      saved = await api.createTask({
        board_id:    props.boardId,
        column_id:   props.columnId,
        title:       form.value.title,
        description: form.value.description || null,
        priority:    form.value.priority,
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
