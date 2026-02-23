<template>
  <div class="p-4 space-y-4">

    <!-- Back + header -->
    <div class="flex items-center gap-2">
      <NuxtLink to="/tma/finance">
        <Button variant="ghost" size="sm" class="px-2">
          <ChevronLeft class="w-5 h-5" />
        </Button>
      </NuxtLink>
      <h1 class="text-base font-semibold text-foreground flex-1">История</h1>
      <div v-if="!loading && entries.length > 0" class="flex items-center gap-2 text-xs text-muted-foreground">
        <span v-if="totalExpense > 0" class="text-red-500 font-medium">-{{ currency }}{{ formatMoney(totalExpense) }}</span>
        <span v-if="totalIncome > 0" class="text-emerald-600 font-medium">+{{ currency }}{{ formatMoney(totalIncome) }}</span>
      </div>
    </div>

    <!-- Period tabs -->
    <div class="flex gap-1 bg-muted rounded-lg p-1">
      <button
        v-for="p in periods"
        :key="p.value"
        class="flex-1 py-1.5 rounded-md text-xs font-medium transition-all duration-150"
        :class="period === p.value ? 'bg-white text-foreground shadow-sm' : 'text-muted-foreground'"
        @click="switchPeriod(p.value)"
      >
        {{ p.label }}
      </button>
    </div>

    <!-- Entries list -->
    <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
      <div v-if="loading && entries.length === 0" class="divide-y divide-border">
        <div v-for="i in 6" :key="i" class="flex items-center justify-between px-4 py-3.5">
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

      <div v-else-if="!loading && entries.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
        <Receipt class="w-8 h-8 text-muted-foreground/30 mb-2" />
        <p class="text-sm font-medium text-foreground">Нет записей</p>
      </div>

      <div v-else class="divide-y divide-border">
        <div
          v-for="entry in entries"
          :key="entry.id"
          class="flex items-center justify-between px-4 py-3"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div v-if="entry.type === 'income'" class="w-2.5 h-2.5 rounded-full shrink-0 bg-emerald-400" />
            <span v-else class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: entry.category?.color || '#9ca3af' }" />
            <div class="min-w-0">
              <p class="text-sm font-medium text-foreground truncate">
                {{ entry.description || (entry.type === 'income' ? 'Доход' : 'Без описания') }}
              </p>
              <p class="text-xs text-muted-foreground">
                <span v-if="entry.type === 'income'" class="text-emerald-600 font-medium">Доход</span>
                <span v-else>{{ entry.category?.name || 'Без категории' }}</span>
                · {{ formatDate(entry.date) }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-1 ml-3 shrink-0">
            <span class="text-sm font-semibold" :class="entry.type === 'income' ? 'text-emerald-600' : 'text-foreground'">
              {{ entry.type === 'income' ? '+' : '-' }}{{ currency }}{{ formatMoney(entry.amount) }}
            </span>
            <button class="p-1.5 rounded-md text-muted-foreground hover:text-foreground" @click="openEdit(entry)">
              <Pencil class="w-3.5 h-3.5" />
            </button>
            <button
              class="p-1.5 rounded-md text-muted-foreground hover:text-destructive disabled:opacity-50"
              :disabled="deletingId === entry.id"
              @click="deleteEntry(entry)"
            >
              <Loader2 v-if="deletingId === entry.id" class="w-3.5 h-3.5 animate-spin" />
              <Trash2 v-else class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <div v-if="hasMore" class="px-4 py-4 flex justify-center">
          <button
            class="text-sm text-muted-foreground hover:text-foreground font-medium flex items-center gap-1.5 disabled:opacity-50"
            :disabled="loadingMore"
            @click="loadMore"
          >
            <Loader2 v-if="loadingMore" class="w-3.5 h-3.5 animate-spin" />
            <span>{{ loadingMore ? 'Загрузка...' : `Ещё (${entries.length} из ${totalCount})` }}</span>
          </button>
        </div>
      </div>
    </div>

  </div>

  <!-- Edit dialog -->
  <Dialog v-model:open="editOpen">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Редактировать</DialogTitle>
      </DialogHeader>
      <div class="space-y-3 py-1">
        <div class="flex gap-1 bg-muted rounded-lg p-1">
          <button
            type="button"
            class="flex-1 py-1.5 rounded-md text-sm font-medium transition-all flex items-center justify-center gap-1.5"
            :class="editForm.type === 'expense' ? 'bg-white text-red-600 shadow-sm' : 'text-muted-foreground'"
            @click="editForm.type = 'expense'"
          >
            <TrendingDown class="w-3.5 h-3.5" /> Расход
          </button>
          <button
            type="button"
            class="flex-1 py-1.5 rounded-md text-sm font-medium transition-all flex items-center justify-center gap-1.5"
            :class="editForm.type === 'income' ? 'bg-white text-emerald-600 shadow-sm' : 'text-muted-foreground'"
            @click="editForm.type = 'income'"
          >
            <TrendingUp class="w-3.5 h-3.5" /> Доход
          </button>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-muted-foreground mb-1 block">Сумма *</label>
            <Input v-model="editForm.amount" type="number" step="0.01" min="0.01" placeholder="0.00" />
          </div>
          <div>
            <label class="text-xs text-muted-foreground mb-1 block">Дата *</label>
            <Input v-model="editForm.date" type="date" />
          </div>
        </div>
        <div v-if="editForm.type === 'expense'">
          <label class="text-xs text-muted-foreground mb-1 block">Категория</label>
          <Select v-model="editForm.category_id">
            <SelectTrigger><SelectValue placeholder="Без категории" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="none">Без категории</SelectItem>
              <SelectItem v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <label class="text-xs text-muted-foreground mb-1 block">Описание</label>
          <Input v-model="editForm.description" :placeholder="editForm.type === 'income' ? 'Источник дохода...' : 'На что потрачено...'" />
        </div>
      </div>
      <DialogFooter>
        <button class="px-4 py-2 text-sm text-muted-foreground" @click="editOpen = false">Отмена</button>
        <Button :disabled="editSaving" @click="saveEdit">
          <Loader2 v-if="editSaving" class="w-3.5 h-3.5 mr-2 animate-spin" />
          {{ editSaving ? 'Сохранение...' : 'Сохранить' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { Receipt, Trash2, Loader2, Pencil, TrendingDown, TrendingUp, ChevronLeft } from 'lucide-vue-next'

definePageMeta({ layout: 'telegram', middleware: 'tma-auth' })

const api = useApi()
const { showBackButton, hideBackButton } = useTelegram()

const periods = [
  { value: 'today',     label: 'Сегодня' },
  { value: 'yesterday', label: 'Вчера' },
  { value: 'dayBefore', label: 'Поза' },
  { value: 'all',       label: 'Все' },
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

const editOpen    = ref(false)
const editSaving  = ref(false)
const editId      = ref<number | null>(null)
const categories  = ref<any[]>([])
const editForm    = reactive({
  amount: '', description: '', category_id: 'none', date: '',
  type: 'expense' as 'expense' | 'income',
})

const localDateStr = (offset: number) => {
  const d = new Date()
  d.setDate(d.getDate() - offset)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const dateParams = computed<Record<string, string>>(() => {
  switch (period.value) {
    case 'today':     return { from: localDateStr(0), to: localDateStr(0) }
    case 'yesterday': return { from: localDateStr(1), to: localDateStr(1) }
    case 'dayBefore': return { from: localDateStr(2), to: localDateStr(2) }
    default:          return {}
  }
})

const totalExpense = computed(() => entries.value.filter(e => e.type !== 'income').reduce((s, e) => s + parseFloat(e.amount ?? 0), 0))
const totalIncome  = computed(() => entries.value.filter(e => e.type === 'income').reduce((s, e) => s + parseFloat(e.amount ?? 0), 0))

const loadEntries = async (reset = true) => {
  if (reset) { loading.value = true; entries.value = []; page.value = 1 }
  else loadingMore.value = true
  try {
    const res = await api.getEntries({ ...dateParams.value, page: page.value }) as any
    const data: any[] = res.data ?? []
    entries.value = reset ? data : [...entries.value, ...data]
    totalCount.value = res.meta?.total ?? data.length
    hasMore.value = (res.meta?.current_page ?? 1) < (res.meta?.last_page ?? 1)
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

const loadMore = async () => { page.value++; await loadEntries(false) }
const switchPeriod = (p: string) => { period.value = p; loadEntries() }

const openEdit = (entry: any) => {
  editId.value         = entry.id
  editForm.type        = entry.type === 'income' ? 'income' : 'expense'
  editForm.amount      = String(entry.amount)
  editForm.description = entry.description || ''
  editForm.date        = entry.date
  editForm.category_id = entry.category_id ? String(entry.category_id) : 'none'
  editOpen.value       = true
}

const saveEdit = async () => {
  if (!editId.value) return
  const amountNum = parseFloat(editForm.amount)
  if (!editForm.amount || isNaN(amountNum) || amountNum <= 0 || !editForm.date) return
  editSaving.value = true
  try {
    const updated = await api.updateEntry(editId.value, {
      amount:      parseFloat(editForm.amount),
      description: editForm.description || null,
      category_id: editForm.type === 'expense' && editForm.category_id !== 'none' ? parseInt(editForm.category_id) : null,
      date:        editForm.date,
      type:        editForm.type,
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
  if (!confirm(`Удалить "${label}"?`)) return
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
  return isNaN(num) ? '0.00' : new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
}

const formatDate = (d: string) => new Date(d).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })

onMounted(async () => {
  showBackButton(() => navigateTo('/tma/finance'))
  const [, settings] = await Promise.all([loadEntries(), api.getSettings() as Promise<any>])
  currency.value = (settings as any).currency_symbol || '$'
  try { categories.value = (await api.getCategories()) as any[] } catch {}
})

onUnmounted(() => hideBackButton())
</script>
