<template>
  <div class="space-y-5 animate-fade-in">

    <!-- Header + period tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-border p-5 space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <History class="w-4 h-4 text-muted-foreground" />
          <h1 class="text-base font-semibold text-foreground">История записей</h1>
        </div>

        <!-- Stats badge -->
        <div v-if="!loading && entries.length > 0" class="flex items-center gap-3 text-xs text-muted-foreground">
          <span class="bg-muted rounded-full px-2.5 py-0.5">
            {{ totalCount }} {{ pluralEntries(totalCount) }}
          </span>
          <span v-if="totalExpense > 0" class="text-red-500 font-medium">
            -{{ currency }}{{ formatMoney(totalExpense) }}
          </span>
          <span v-if="totalIncome > 0" class="text-emerald-600 font-medium">
            +{{ currency }}{{ formatMoney(totalIncome) }}
          </span>
        </div>
      </div>

      <!-- Period tabs -->
      <div class="flex gap-1 bg-muted rounded-lg p-1 w-fit">
        <button
          v-for="p in periods"
          :key="p.value"
          class="px-4 py-1.5 rounded-md text-sm font-medium transition-all duration-150"
          :class="period === p.value
            ? 'bg-white text-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground'"
          @click="switchPeriod(p.value)"
        >
          {{ p.label }}
        </button>
      </div>
    </div>

    <!-- Entries list -->
    <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">

      <!-- Loading skeleton -->
      <div v-if="loading && entries.length === 0" class="divide-y divide-border">
        <div v-for="i in 6" :key="i" class="flex items-center justify-between px-5 py-3.5">
          <div class="flex items-center gap-3">
            <div class="skeleton w-2.5 h-2.5 rounded-full" />
            <div>
              <div class="skeleton h-3 w-36 mb-1.5" />
              <div class="skeleton h-2.5 w-24" />
            </div>
          </div>
          <div class="skeleton h-3.5 w-16" />
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="!loading && entries.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
        <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center mb-3">
          <Receipt class="w-5 h-5 text-muted-foreground" />
        </div>
        <p class="text-sm font-medium text-foreground">Нет записей</p>
        <p class="text-xs text-muted-foreground mt-1">За этот период нет расходов или доходов</p>
      </div>

      <!-- List -->
      <div v-else class="divide-y divide-border">
        <div
          v-for="entry in entries"
          :key="entry.id"
          class="flex items-center justify-between px-5 py-3.5 hover:bg-muted/30 transition-colors group"
        >
          <div class="flex items-center gap-3 min-w-0">
            <!-- Color indicator -->
            <div
              v-if="entry.type === 'income'"
              class="w-2.5 h-2.5 rounded-full shrink-0 bg-emerald-400"
            />
            <span
              v-else
              class="w-2.5 h-2.5 rounded-full shrink-0"
              :style="{ background: entry.category?.color || '#9ca3af' }"
            />

            <div class="min-w-0">
              <p class="text-sm font-medium text-foreground truncate">
                {{ entry.description || (entry.type === 'income' ? 'Доход' : 'Без описания') }}
              </p>
              <p class="text-xs text-muted-foreground flex items-center gap-1">
                <span v-if="entry.type === 'income'" class="text-emerald-600 font-medium">Доход</span>
                <span v-else>{{ entry.category?.name || 'Без категории' }}</span>
                <span class="text-muted-foreground/50">·</span>
                <span>{{ formatDate(entry.date) }}</span>
                <span v-if="entry.source === 'telegram'" class="text-slate-400 flex items-center gap-0.5">
                  <span class="text-muted-foreground/50">·</span> Telegram
                </span>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 ml-3 shrink-0">
            <span
              class="text-sm font-semibold"
              :class="entry.type === 'income' ? 'text-emerald-600' : 'text-foreground'"
            >
              {{ entry.type === 'income' ? '+' : '-' }}{{ currency }}{{ formatMoney(entry.amount) }}
            </span>
            <button
              class="text-muted-foreground hover:text-foreground transition-all duration-150 p-1.5 rounded-md hover:bg-muted"
              title="Редактировать"
              @click="openEdit(entry)"
            >
              <Pencil class="w-3.5 h-3.5" />
            </button>
            <button
              class="text-muted-foreground hover:text-destructive transition-all duration-150 p-1.5 rounded-md hover:bg-destructive/10 disabled:opacity-50"
              title="Удалить"
              :disabled="deletingId === entry.id"
              @click="deleteEntry(entry)"
            >
              <Loader2 v-if="deletingId === entry.id" class="w-3.5 h-3.5 animate-spin" />
              <Trash2 v-else class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Load more (only for "Все" tab) -->
        <div v-if="hasMore" class="px-5 py-4 flex justify-center border-t border-border">
          <button
            class="text-sm text-muted-foreground hover:text-foreground font-medium flex items-center gap-1.5 transition-colors disabled:opacity-50"
            :disabled="loadingMore"
            @click="loadMore"
          >
            <Loader2 v-if="loadingMore" class="w-3.5 h-3.5 animate-spin" />
            <span>{{ loadingMore ? 'Загрузка...' : `Показать ещё (загружено ${entries.length} из ${totalCount})` }}</span>
          </button>
        </div>
      </div>
    </div>

  </div>

  <!-- Edit dialog -->
  <Dialog v-model:open="editOpen">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Редактировать запись</DialogTitle>
      </DialogHeader>

      <div class="space-y-3 py-1">
        <!-- Type toggle -->
        <div class="flex gap-1 bg-muted rounded-lg p-1">
          <button
            type="button"
            class="flex-1 py-1.5 rounded-md text-sm font-medium transition-all duration-150 flex items-center justify-center gap-1.5"
            :class="editForm.type === 'expense' ? 'bg-white text-red-600 shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            @click="editForm.type = 'expense'"
          >
            <TrendingDown class="w-3.5 h-3.5" />
            Расход
          </button>
          <button
            type="button"
            class="flex-1 py-1.5 rounded-md text-sm font-medium transition-all duration-150 flex items-center justify-center gap-1.5"
            :class="editForm.type === 'income' ? 'bg-white text-emerald-600 shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            @click="editForm.type = 'income'"
          >
            <TrendingUp class="w-3.5 h-3.5" />
            Доход
          </button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-muted-foreground mb-1 block">Сумма *</label>
            <Input v-model="editForm.amount" type="number" step="0.01" min="0.01" placeholder="0.00" />
            <p v-if="editErrors.amount" class="text-xs text-destructive mt-1">{{ editErrors.amount }}</p>
          </div>
          <div>
            <label class="text-xs text-muted-foreground mb-1 block">Дата *</label>
            <Input v-model="editForm.date" type="date" />
            <p v-if="editErrors.date" class="text-xs text-destructive mt-1">{{ editErrors.date }}</p>
          </div>
        </div>

        <div v-if="editForm.type === 'expense'">
          <label class="text-xs text-muted-foreground mb-1 block">Категория</label>
          <Select v-model="editForm.category_id">
            <SelectTrigger>
              <SelectValue placeholder="Без категории" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Без категории</SelectItem>
              <SelectItem v-for="cat in categories" :key="cat.id" :value="String(cat.id)">
                {{ cat.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div>
          <label class="text-xs text-muted-foreground mb-1 block">Описание</label>
          <Input
            v-model="editForm.description"
            :placeholder="editForm.type === 'income' ? 'Источник дохода...' : 'На что потрачено...'"
          />
        </div>
      </div>

      <DialogFooter>
        <button
          class="px-4 py-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
          @click="editOpen = false"
        >
          Отмена
        </button>
        <Button :disabled="editSaving" @click="saveEdit">
          <Loader2 v-if="editSaving" class="w-3.5 h-3.5 mr-2 animate-spin" />
          {{ editSaving ? 'Сохранение...' : 'Сохранить' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

</template>

<script setup lang="ts">
import { History, Receipt, Trash2, Loader2, Pencil, TrendingDown, TrendingUp } from 'lucide-vue-next'

definePageMeta({ middleware: 'auth' })

const api = useApi()

const periods = [
  { value: 'today',     label: 'Сегодня' },
  { value: 'yesterday', label: 'Вчера' },
  { value: 'dayBefore', label: 'Позавчера' },
  { value: 'all',       label: 'Все расходы' },
]

const period      = ref('today')
const entries     = ref<any[]>([])
const loading     = ref(false)
const loadingMore = ref(false)
const page        = ref(1)
const hasMore     = ref(false)
const totalCount  = ref(0)
const currency    = ref('$')
const deletingId  = ref<number | null>(null)

// Edit dialog
const editOpen    = ref(false)
const editSaving  = ref(false)
const editId      = ref<number | null>(null)
const categories  = ref<any[]>([])
const editErrors  = reactive<{ amount: string; date: string }>({ amount: '', date: '' })
const editForm    = reactive({
  amount:      '',
  description: '',
  category_id: 'none',
  date:        '',
  type:        'expense' as 'expense' | 'income',
})

// Build local date string (avoids UTC offset issues)
const localDateStr = (offset: number) => {
  const d = new Date()
  d.setDate(d.getDate() - offset)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const dateParams = computed<Record<string, string>>(() => {
  switch (period.value) {
    case 'today':     return { from: localDateStr(0), to: localDateStr(0) }
    case 'yesterday': return { from: localDateStr(1), to: localDateStr(1) }
    case 'dayBefore': return { from: localDateStr(2), to: localDateStr(2) }
    default:          return {}
  }
})

const totalExpense = computed(() =>
  entries.value
    .filter(e => e.type !== 'income')
    .reduce((s, e) => s + parseFloat(e.amount ?? 0), 0)
)

const totalIncome = computed(() =>
  entries.value
    .filter(e => e.type === 'income')
    .reduce((s, e) => s + parseFloat(e.amount ?? 0), 0)
)

const loadEntries = async (reset = true) => {
  if (reset) {
    loading.value = true
    entries.value = []
    page.value = 1
  } else {
    loadingMore.value = true
  }

  try {
    const params: Record<string, any> = { ...dateParams.value, page: page.value }
    const res = await api.getEntries(params) as any
    const data: any[] = res.data ?? []

    entries.value = reset ? data : [...entries.value, ...data]
    totalCount.value = res.meta?.total ?? data.length
    hasMore.value = (res.meta?.current_page ?? 1) < (res.meta?.last_page ?? 1)
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

const loadMore = async () => {
  page.value++
  await loadEntries(false)
}

const switchPeriod = (p: string) => {
  period.value = p
  loadEntries()
}

const openEdit = (entry: any) => {
  editId.value          = entry.id
  editErrors.amount     = ''
  editErrors.date       = ''
  editForm.type         = entry.type === 'income' ? 'income' : 'expense'
  editForm.amount       = String(entry.amount)
  editForm.description  = entry.description || ''
  editForm.date         = entry.date
  editForm.category_id  = entry.category_id ? String(entry.category_id) : 'none'
  editOpen.value        = true
}

const saveEdit = async () => {
  if (!editId.value) return
  editErrors.amount = ''
  editErrors.date = ''
  const amountNum = parseFloat(editForm.amount)
  if (!editForm.amount || Number.isNaN(amountNum) || amountNum <= 0) {
    editErrors.amount = 'Введите сумму больше 0'
  }
  if (!editForm.date) {
    editErrors.date = 'Укажите дату'
  }
  if (editErrors.amount || editErrors.date) return

  editSaving.value = true
  try {
    const updated = await api.updateEntry(editId.value, {
      amount:      parseFloat(editForm.amount),
      description: editForm.description || null,
      category_id: editForm.type === 'expense' && editForm.category_id !== 'none'
        ? parseInt(editForm.category_id)
        : null,
      date: editForm.date,
      type: editForm.type,
    }) as any
    const idx = entries.value.findIndex(e => e.id === editId.value)
    if (idx !== -1) entries.value[idx] = updated
    editOpen.value = false
  } finally {
    editSaving.value = false
  }
}

const deleteEntry = async (entry: any) => {
  const label = entry.description || (entry.type === 'income' ? 'Доход' : 'Расход')
  if (!confirm(`Удалить запись "${label}"?`)) return

  deletingId.value = entry.id
  try {
    await api.deleteEntry(entry.id)
    entries.value = entries.value.filter(e => e.id !== entry.id)
    totalCount.value = Math.max(0, totalCount.value - 1)
  } finally {
    deletingId.value = null
  }
}

const formatMoney = (value: any) => {
  const num = parseFloat(String(value ?? 0).replace(',', '.'))
  if (isNaN(num)) return '0.00'
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num)
}

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })

const pluralEntries = (n: number) => {
  if (n % 10 === 1 && n % 100 !== 11) return 'запись'
  if ([2, 3, 4].includes(n % 10) && ![12, 13, 14].includes(n % 100)) return 'записи'
  return 'записей'
}

onMounted(async () => {
  const [, settings] = await Promise.all([
    loadEntries(),
    api.getSettings() as Promise<any>,
  ])
  currency.value = (settings as any).currency_symbol || '$'
  try { categories.value = (await api.getCategories()) as any[] } catch {}
})
</script>
