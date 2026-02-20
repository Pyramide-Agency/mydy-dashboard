<template>
  <DynamicForm
    v-model="form"
    :fields="expenseFields"
    :submit-label="submitLabel"
    :submit-class="form.type === 'income' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : ''"
    :loading="loading"
    @submit="handleSubmit"
  />
</template>

<script setup lang="ts">
import type { FormField } from '~/components/DynamicForm.vue'

const emit = defineEmits<{ submitted: [] }>()

const api        = useApi()
const categories = ref<any[]>([])
const loading    = ref(false)

const today = new Date().toISOString().split('T')[0]

const form = ref<Record<string, any>>({
  type:        'expense',
  amount:      '',
  date:        today,
  category_id: 'none',
  description: '',
})

const expenseFields = computed((): FormField[] => [
  {
    key:     'type',
    label:   'Тип',
    type:    'toggle',
    options: [
      { label: 'Расход', value: 'expense', activeClass: 'bg-white text-red-600 shadow-sm'     },
      { label: 'Доход',  value: 'income',  activeClass: 'bg-white text-emerald-600 shadow-sm' },
    ],
  },
  {
    key:         'amount',
    label:       'Сумма',
    type:        'number',
    required:    true,
    placeholder: '0.00',
    min:         0.01,
    colSpan:     1,
  },
  {
    key:          'date',
    label:        'Дата',
    type:         'date',
    required:     true,
    defaultValue: today,
    colSpan:      1,
  },
  {
    key:       'category_id',
    label:     'Категория',
    type:      'select',
    condition: (data) => data.type === 'expense',
    options:   [
      { label: 'Без категории', value: 'none' },
      ...categories.value.map(c => ({ label: c.name, value: String(c.id) })),
    ],
  },
  {
    key:         'description',
    label:       'Описание',
    type:        'textarea',
    placeholder: form.value.type === 'income' ? 'Источник дохода...' : 'На что потрачено...',
    rows:        2,
  },
])

const submitLabel = computed(() =>
  form.value.type === 'income' ? 'Добавить доход' : 'Добавить расход'
)

onMounted(async () => {
  try {
    categories.value = (await api.getCategories()) as any[]
  } catch {}
})

const handleSubmit = async (data: Record<string, any>) => {
  loading.value = true
  try {
    await api.createEntry({
      amount:      parseFloat(data.amount),
      description: data.description || null,
      category_id: data.type === 'expense' && data.category_id !== 'none'
        ? parseInt(data.category_id)
        : null,
      date: data.date,
      type: data.type,
    })
    form.value = { type: 'expense', amount: '', date: today, category_id: 'none', description: '' }
    emit('submitted')
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}
</script>
