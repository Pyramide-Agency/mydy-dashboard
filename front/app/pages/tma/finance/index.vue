<template>
  <div class="p-4 space-y-4">

    <!-- Initial balance prompt -->
    <div
      v-if="showInitialBalancePrompt"
      class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-3"
    >
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
          <Wallet class="w-4 h-4 text-amber-600" />
        </div>
        <div>
          <p class="text-sm font-medium text-amber-900">Задайте начальный баланс</p>
          <p class="text-xs text-amber-700 mt-0.5">Укажите стартовую сумму для точного учёта</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <div class="relative flex-1">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">{{ currency }}</span>
          <Input
            v-model="initialBalanceInput"
            type="number"
            step="0.01"
            min="0"
            placeholder="0.00"
            class="pl-8"
            @keydown.enter="saveInitialBalance"
          />
        </div>
        <Button size="sm" @click="saveInitialBalance" :disabled="savingBalance">
          <Loader2 v-if="savingBalance" class="w-3.5 h-3.5 mr-1 animate-spin" />
          ОК
        </Button>
        <button class="text-muted-foreground p-1" @click="showInitialBalancePrompt = false">
          <X class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Period selector + stats -->
    <div class="bg-white rounded-xl shadow-sm border border-border p-4 space-y-4">
      <div class="flex gap-1 bg-muted rounded-lg p-1">
        <button
          v-for="p in periods"
          :key="p.value"
          class="flex-1 py-1.5 rounded-md text-sm font-medium transition-all duration-150"
          :class="period === p.value ? 'bg-white text-foreground shadow-sm' : 'text-muted-foreground'"
          @click="period = p.value; loadSummary()"
        >
          {{ p.label }}
        </button>
      </div>

      <div v-if="!loadingData" class="grid grid-cols-3 gap-2">
        <div class="flex flex-col items-center gap-1">
          <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
            <TrendingUp class="w-4 h-4 text-emerald-600" />
          </div>
          <p class="text-[10px] text-muted-foreground">Доходы</p>
          <p class="text-sm font-bold text-emerald-600 text-center">+{{ currency }}{{ formatMoney(summary.total_income) }}</p>
        </div>
        <div class="flex flex-col items-center gap-1">
          <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
            <TrendingDown class="w-4 h-4 text-red-500" />
          </div>
          <p class="text-[10px] text-muted-foreground">Расходы</p>
          <p class="text-sm font-bold text-red-500 text-center">-{{ currency }}{{ formatMoney(summary.total_expense) }}</p>
        </div>
        <div class="flex flex-col items-center gap-1">
          <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
            <Landmark class="w-4 h-4 text-indigo-600" />
          </div>
          <p class="text-[10px] text-muted-foreground">Баланс</p>
          <p
            class="text-sm font-bold text-center"
            :class="(summary.overall_balance ?? 0) >= 0 ? 'text-indigo-600' : 'text-orange-500'"
          >
            {{ currency }}{{ formatMoney(Math.abs(summary.overall_balance ?? 0)) }}
          </p>
        </div>
      </div>

      <div v-else class="grid grid-cols-3 gap-2">
        <div v-for="i in 3" :key="i" class="flex flex-col items-center gap-1">
          <div class="skeleton w-8 h-8 rounded-lg" />
          <div class="skeleton h-2.5 w-12" />
          <div class="skeleton h-4 w-16" />
        </div>
      </div>
    </div>

    <!-- Charts -->
    <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
      <div class="px-4 py-3 border-b border-border">
        <h2 class="text-sm font-semibold text-foreground">Сводка по категориям</h2>
      </div>
      <div class="p-4">
        <div v-if="loadingData" class="skeleton h-32 w-full rounded-lg" />
        <FinanceSummaryPanel v-else :summary="summary" :currency="currency" :show-bar="period !== 'today'" />
      </div>
    </div>

    <!-- Quick add -->
    <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
      <div class="px-4 py-3 border-b border-border flex items-center gap-2">
        <Plus class="w-4 h-4 text-muted-foreground" />
        <h2 class="text-sm font-semibold text-foreground">Добавить запись</h2>
      </div>
      <div class="p-4">
        <ExpenseForm @submitted="onEntryAdded" />
      </div>
    </div>

    <!-- Entries list -->
    <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
      <div class="px-4 py-3 border-b border-border flex items-center justify-between">
        <div class="flex items-center gap-2">
          <List class="w-4 h-4 text-muted-foreground" />
          <h2 class="text-sm font-semibold text-foreground">История</h2>
        </div>
        <NuxtLink to="/tma/finance/history" class="text-xs font-medium text-indigo-600 flex items-center gap-1">
          Всё
          <ArrowRight class="w-3 h-3" />
        </NuxtLink>
      </div>

      <div v-if="loadingData" class="divide-y divide-border">
        <div v-for="i in 4" :key="i" class="flex items-center justify-between px-4 py-3">
          <div class="flex items-center gap-3">
            <div class="skeleton w-2 h-2 rounded-full" />
            <div class="skeleton h-3 w-28" />
          </div>
          <div class="skeleton h-3 w-14" />
        </div>
      </div>

      <div v-else-if="entries.length === 0" class="flex flex-col items-center justify-center py-8 text-center">
        <Receipt class="w-8 h-8 text-muted-foreground/30 mb-2" />
        <p class="text-sm text-foreground">Нет записей</p>
      </div>

      <div v-else class="divide-y divide-border">
        <div
          v-for="entry in entries"
          :key="entry.id"
          class="flex items-center justify-between px-4 py-3"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <div v-if="entry.type === 'income'" class="w-2.5 h-2.5 rounded-full shrink-0 bg-emerald-400" />
            <span v-else class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: entry.category?.color || '#9ca3af' }" />
            <div class="min-w-0">
              <p class="text-sm font-medium text-foreground truncate">
                {{ entry.description || (entry.type === 'income' ? 'Доход' : 'Без описания') }}
              </p>
              <p class="text-xs text-muted-foreground">
                <span v-if="entry.type === 'income'" class="text-emerald-600 font-medium">Доход</span>
                <span v-else>{{ entry.category?.name || 'Без категории' }}</span>
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 ml-3 shrink-0">
            <span class="text-sm font-semibold" :class="entry.type === 'income' ? 'text-emerald-600' : 'text-foreground'">
              {{ entry.type === 'income' ? '+' : '-' }}{{ currency }}{{ formatMoney(entry.amount) }}
            </span>
            <button
              class="text-muted-foreground hover:text-destructive p-1 rounded"
              @click="deleteEntry(entry)"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- AI button -->
    <NuxtLink to="/tma/ai">
      <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
          <Bot class="w-5 h-5 text-indigo-600" />
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-indigo-900">AI Советник</p>
          <p class="text-xs text-indigo-700">Спросите о ваших финансах</p>
        </div>
        <ArrowRight class="w-4 h-4 text-indigo-500" />
      </div>
    </NuxtLink>

  </div>
</template>

<script setup lang="ts">
import { Bot, Trash2, Plus, List, Receipt, TrendingUp, TrendingDown, Landmark, Wallet, Loader2, X, ArrowRight } from 'lucide-vue-next'

definePageMeta({ layout: 'telegram', middleware: 'tma-auth' })

const api         = useApi()
const period      = ref('today')
const summary     = ref<any>({})
const entries     = ref<any[]>([])
const currency    = ref('$')
const loadingData = ref(true)

const showInitialBalancePrompt = ref(false)
const initialBalanceInput      = ref('')
const savingBalance            = ref(false)

const formatMoney = (value: any) => {
  if (value === null || value === undefined || value === '') return '0.00'
  const num = typeof value === 'number' ? value : parseFloat(String(value).replace(',', '.'))
  if (Number.isNaN(num)) return '0.00'
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
}

const periods = [
  { value: 'today', label: 'Сегодня' },
  { value: 'week',  label: 'Неделя' },
  { value: 'month', label: 'Месяц' },
]

const today      = new Date().toISOString().split('T')[0]
const weekStart  = () => { const d = new Date(); d.setDate(d.getDate() - d.getDay() + 1); return d.toISOString().split('T')[0] }
const monthStart = () => new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]

const fromDate = computed(() =>
  period.value === 'today' ? today : period.value === 'week' ? weekStart() : monthStart()
)

const loadSummary = async () => {
  loadingData.value = true
  try {
    const [s, entriesRes, settings] = await Promise.all([
      api.getSummary(period.value),
      api.getEntries({ from: fromDate.value }),
      api.getSettings(),
    ])
    summary.value  = s
    entries.value  = (entriesRes as any).data || []
    currency.value = (settings as any).currency_symbol || '$'
    const initBal  = parseFloat((settings as any).initial_balance ?? '0')
    if (initBal === 0) showInitialBalancePrompt.value = true
  } finally {
    loadingData.value = false
  }
}

onMounted(() => loadSummary())

const onEntryAdded = () => loadSummary()

const deleteEntry = async (entry: any) => {
  if (!confirm(`Удалить "${entry.description || (entry.type === 'income' ? 'доход' : 'расход')}"?`)) return
  await api.deleteEntry(entry.id)
  await loadSummary()
}

const saveInitialBalance = async () => {
  const val = parseFloat(initialBalanceInput.value)
  if (isNaN(val) || val < 0) return
  savingBalance.value = true
  try {
    await api.updateSettings({ initial_balance: val })
    showInitialBalancePrompt.value = false
    initialBalanceInput.value = ''
    await loadSummary()
  } finally {
    savingBalance.value = false
  }
}
</script>
