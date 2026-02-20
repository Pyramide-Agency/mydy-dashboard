<template>
  <div class="space-y-5 animate-fade-in">

    <!-- Initial balance prompt -->
    <div
      v-if="showInitialBalancePrompt"
      class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-3"
    >
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
          <Wallet class="w-4 h-4 text-amber-600" />
        </div>
        <div>
          <p class="text-sm font-medium text-amber-900">Задайте начальный баланс</p>
          <p class="text-xs text-amber-700 mt-0.5">Укажите стартовую сумму, чтобы точнее отслеживать финансы</p>
        </div>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">{{ currency }}</span>
          <Input
            v-model="initialBalanceInput"
            type="number"
            step="0.01"
            min="0"
            placeholder="0.00"
            class="pl-12 w-32"
            @keydown.enter="saveInitialBalance"
          />
          <p v-if="initialBalanceError" class="text-xs text-destructive mt-1">{{ initialBalanceError }}</p>
        </div>
        <Button size="sm" @click="saveInitialBalance" :disabled="savingBalance">
          <Loader2 v-if="savingBalance" class="w-3.5 h-3.5 mr-1 animate-spin" />
          Сохранить
        </Button>
        <button
          class="text-muted-foreground hover:text-foreground p-1"
          title="Скрыть"
          @click="showInitialBalancePrompt = false"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Period selector + stats -->
    <div class="bg-white rounded-xl shadow-sm border border-border p-5 space-y-4">
      <!-- Period tabs -->
      <div class="flex gap-1 bg-muted rounded-lg p-1 w-fit">
        <button
          v-for="p in periods"
          :key="p.value"
          class="px-4 py-1.5 rounded-md text-sm font-medium transition-all duration-150"
          :class="period === p.value
            ? 'bg-white text-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground'"
          @click="period = p.value; loadSummary()"
        >
          {{ p.label }}
        </button>
      </div>

      <!-- Stats row -->
      <div v-if="!loadingData" class="grid grid-cols-3 gap-4">
        <!-- Income -->
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
            <TrendingUp class="w-4.5 h-4.5 text-emerald-600" />
          </div>
          <div>
            <p class="text-xs text-muted-foreground">Доходы</p>
            <p class="text-lg font-bold text-emerald-600">+{{ currency }} {{ formatMoney(summary.total_income) }}</p>
            <p class="text-xs text-muted-foreground">{{ summary.count_income || 0 }} опер.</p>
          </div>
        </div>

        <!-- Expense -->
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
            <TrendingDown class="w-4.5 h-4.5 text-red-500" />
          </div>
          <div>
            <p class="text-xs text-muted-foreground">Расходы</p>
            <p class="text-lg font-bold text-red-500">-{{ currency }} {{ formatMoney(summary.total_expense) }}</p>
            <p class="text-xs text-muted-foreground">{{ summary.count_expense || 0 }} опер.</p>
          </div>
        </div>

        <!-- Overall balance -->
        <div class="flex items-center gap-3">
          <div
            class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
            :class="(summary.overall_balance ?? 0) >= 0 ? 'bg-indigo-50' : 'bg-orange-50'"
          >
            <Landmark
              class="w-4.5 h-4.5"
              :class="(summary.overall_balance ?? 0) >= 0 ? 'text-indigo-600' : 'text-orange-500'"
            />
          </div>
          <div>
            <p class="text-xs text-muted-foreground">Общий баланс</p>
            <p
              class="text-lg font-bold"
              :class="(summary.overall_balance ?? 0) >= 0 ? 'text-indigo-600' : 'text-orange-500'"
            >
              {{ (summary.overall_balance ?? 0) >= 0 ? '' : '-' }} {{ currency }} {{ formatMoney(Math.abs(summary.overall_balance ?? 0)) }}
            </p>
            <button
              class="text-xs text-muted-foreground hover:text-foreground underline-offset-2 hover:underline"
              @click="showInitialBalancePrompt = true"
            >
              нач. {{ currency }}{{ formatMoney(summary.initial_balance ?? 0) }}
            </button>
          </div>
        </div>
      </div>

      <!-- Loading state for stats -->
      <div v-else class="grid grid-cols-3 gap-4">
        <div v-for="i in 3" :key="i" class="flex items-center gap-3">
          <div class="skeleton w-9 h-9 rounded-lg" />
          <div>
            <div class="skeleton h-2.5 w-14 mb-1.5" />
            <div class="skeleton h-5 w-20 mb-1" />
            <div class="skeleton h-2.5 w-10" />
          </div>
        </div>
      </div>
    </div>

    <!-- Charts + AI -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Charts -->
      <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-border overflow-hidden">
        <div class="px-5 py-4 border-b border-border">
          <h2 class="text-sm font-semibold text-foreground">Сводка по категориям</h2>
        </div>
        <div v-if="loadingData" class="p-5">
          <div class="skeleton h-40 w-full" />
        </div>
        <div v-else class="p-5">
          <FinanceSummaryPanel :summary="summary" :currency="currency" :show-bar="period !== 'today'" />
        </div>
      </div>

      <!-- AI panel -->
      <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-2">
          <div class="w-6 h-6 rounded-md bg-indigo-100 flex items-center justify-center">
            <Bot class="w-3.5 h-3.5 text-indigo-600" />
          </div>
          <h2 class="text-sm font-semibold text-foreground">AI Анализ</h2>
        </div>
        <div class="p-5">
          <AiFeedbackPanel />
        </div>
      </div>
    </div>

    <!-- Add + List -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
      <!-- Add form -->
      <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-border overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-2">
          <Plus class="w-4 h-4 text-muted-foreground" />
          <h2 class="text-sm font-semibold text-foreground">Добавить запись</h2>
        </div>
        <div class="p-5">
          <ExpenseForm @submitted="onEntryAdded" />
        </div>
      </div>

      <!-- Entries list -->
      <div class="lg:col-span-3 bg-white rounded-xl shadow-sm border border-border overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center justify-between">
          <div class="flex items-center gap-2">
            <List class="w-4 h-4 text-muted-foreground" />
            <h2 class="text-sm font-semibold text-foreground">История</h2>
          </div>
          <span class="text-xs text-muted-foreground bg-muted rounded-full px-2.5 py-0.5">
            {{ periodLabel }}
          </span>
        </div>

        <!-- Loading -->
        <div v-if="loadingData" class="divide-y divide-border">
          <div v-for="i in 5" :key="i" class="flex items-center justify-between px-5 py-3.5">
            <div class="flex items-center gap-3">
              <div class="skeleton w-2 h-2 rounded-full" />
              <div>
                <div class="skeleton h-3 w-28 mb-1.5" />
                <div class="skeleton h-2.5 w-16" />
              </div>
            </div>
            <div class="skeleton h-3 w-14" />
          </div>
        </div>

        <!-- Empty -->
        <div v-else-if="entries.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
          <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center mb-3">
            <Receipt class="w-5 h-5 text-muted-foreground" />
          </div>
          <p class="text-sm font-medium text-foreground">Нет записей за период</p>
          <p class="text-xs text-muted-foreground mt-1">Добавьте расход или доход с помощью формы</p>
        </div>

        <!-- Entries -->
        <div v-else class="divide-y divide-border max-h-80 overflow-y-auto">
          <div
            v-for="entry in entries"
            :key="entry.id"
            class="flex items-center justify-between px-5 py-3 hover:bg-muted/30 transition-colors group"
          >
            <div class="flex items-center gap-3 min-w-0">
              <!-- Icon indicator: income vs expense -->
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
                <p class="text-xs text-muted-foreground">
                  <span v-if="entry.type === 'income'" class="text-emerald-600 font-medium">Доход</span>
                  <span v-else>{{ entry.category?.name || 'Без категории' }}</span>
                  · {{ formatDate(entry.date) }}
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
                class="text-muted-foreground hover:text-destructive transition-all duration-150 p-1 rounded hover:bg-destructive/10"
                title="Удалить"
                @click="deleteEntry(entry)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Bot, Trash2, Plus, List, Receipt, TrendingUp, TrendingDown, Landmark, Wallet, Loader2, X } from 'lucide-vue-next'

definePageMeta({ middleware: 'auth' })

const api        = useApi()
const period     = ref('today')
const summary    = ref<any>({})
const entries    = ref<any[]>([])
const currency   = ref('$')
const loadingData = ref(true)

const showInitialBalancePrompt = ref(false)
const initialBalanceInput      = ref('')
const savingBalance            = ref(false)
const initialBalanceError      = ref('')

const formatMoney = (value: any) => {
  if (value === null || value === undefined || value === '') return '0.00'
  const num = typeof value === 'number'
    ? value
    : parseFloat(String(value).replace(',', '.'))
  if (Number.isNaN(num)) return '0.00'
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num)
}


const periods = [
  { value: 'today', label: 'Сегодня' },
  { value: 'week',  label: 'Неделя' },
  { value: 'month', label: 'Месяц' },
]

const periodLabel = computed(() => ({
  today: 'Сегодня', week: 'Эта неделя', month: 'Этот месяц',
}[period.value] || ''))

const today = new Date().toISOString().split('T')[0]
const weekStart = () => {
  const d = new Date()
  d.setDate(d.getDate() - d.getDay() + 1)
  return d.toISOString().split('T')[0]
}
const monthStart = () =>
  new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]

const fromDate = computed(() =>
  period.value === 'today' ? today
  : period.value === 'week' ? weekStart()
  : monthStart()
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

    // Show initial balance prompt if it's 0 or not set
    const initBal = parseFloat((settings as any).initial_balance ?? '0')
    if (initBal === 0) {
      showInitialBalancePrompt.value = true
    }
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
  initialBalanceError.value = ''
  if (isNaN(val) || val < 0) {
    initialBalanceError.value = 'Введите сумму 0 или больше'
    return
  }
  savingBalance.value = true
  try {
    await api.updateSettings({ initial_balance: val })
    showInitialBalancePrompt.value = false
    initialBalanceInput.value = ''
    await loadSummary()
  } catch (e) {
    console.error(e)
  } finally {
    savingBalance.value = false
  }
}

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
</script>
