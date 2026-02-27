<template>
  <div class="max-w-2xl space-y-6">
    <!-- Language settings -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base">{{ $t('settings.language') }}</CardTitle>
        <CardDescription>{{ $t('settings.selectLanguage') }}</CardDescription>
      </CardHeader>
      <CardContent>
        <Select v-model="languageForm" @update:modelValue="saveLanguage">
          <SelectTrigger>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="en">{{ $t('settings.english') }}</SelectItem>
            <SelectItem value="ru">{{ $t('settings.russian') }}</SelectItem>
          </SelectContent>
        </Select>
      </CardContent>
    </Card>

    <!-- Currency settings -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base">{{ $t('settings.currency') }}</CardTitle>
        <CardDescription>{{ $t('settings.currencyCode') }}</CardDescription>
      </CardHeader>
      <CardContent>
        <DynamicForm
          v-model="currencyForm"
          :fields="currencyFields"
          :submit-label="$t('common.save')"
          :loading="saving"
          @submit="saveCurrency"
        />
      </CardContent>
    </Card>

    <!-- AI Provider -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base flex items-center gap-2">
          <Bot class="w-4 h-4" />
          {{ $t('settings.aiProvider') }}
        </CardTitle>
        <CardDescription>
          {{ $t('settings.selectProvider') }}
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div>
          <label class="text-sm font-medium mb-1.5 block">{{ $t('settings.aiProvider') }}</label>
          <Select v-model="aiForm.provider" @update:modelValue="onProviderChange">
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="anthropic">{{ $t('settings.anthropic') }}</SelectItem>
              <SelectItem value="openai">{{ $t('settings.openai') }}</SelectItem>
              <SelectItem value="groq">{{ $t('settings.groq') }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.aiProvider" class="text-xs text-destructive mt-1">{{ errors.aiProvider }}</p>
        </div>
        <div>
          <label class="text-sm font-medium mb-1.5 block">{{ $t('settings.apiKey') }} ({{ $t('settings.aiProvider') }})</label>
          <Input
            v-model="aiForm.apiKey"
            type="password"
            :placeholder="aiApiKeySet ? `●●●●●●●● (${$t('settings.apiKeySet')})` : 'sk-ant-... или sk-...'"
          />
        </div>
        <div>
          <label class="text-sm font-medium mb-1.5 block">
            {{ $t('settings.groqApiKey') }}
            <span class="text-xs text-muted-foreground font-normal ml-1">{{ $t('settings.forMemoryExtraction') }}</span>
          </label>
          <Input
            v-model="aiForm.groqApiKey"
            type="password"
            :placeholder="groqApiKeySet ? `●●●●●●●● (${$t('settings.apiKeySet')})` : 'gsk_...'"
          />
        </div>
        <div>
          <label class="text-sm font-medium mb-1.5 block">
            {{ $t('settings.jinaApiKey') }}
            <span class="text-xs text-muted-foreground font-normal ml-1">{{ $t('settings.forVectorMemory') }}</span>
          </label>
          <Input
            v-model="aiForm.jinaApiKey"
            type="password"
            :placeholder="jinaApiKeySet ? `●●●●●●●● (${$t('settings.apiKeySet')})` : 'jina_...'"
          />
        </div>
        <div>
          <label class="text-sm font-medium mb-1.5 block">{{ $t('settings.model') }}</label>
          <Select v-model="aiForm.model">
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="m in providerModels[aiForm.provider]"
                :key="m.value"
                :value="m.value"
              >
                {{ m.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <Button @click="saveAi" :disabled="savingAi">
          <Loader2 v-if="savingAi" class="w-4 h-4 mr-2 animate-spin" />
          {{ $t('common.save') }}
        </Button>
      </CardContent>
    </Card>

    <!-- AI Memory -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base flex items-center gap-2">
          <Brain class="w-4 h-4" />
          {{ $t('settings.aiMemory') }}
        </CardTitle>
        <CardDescription>
          {{ $t('settings.aiMemory') }}
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">

        <!-- Manual input -->
        <div class="space-y-2">
          <label class="text-sm font-medium block">{{ $t('settings.addInfoAboutYou') }}</label>
          <textarea
            v-model="newMemoryText"
            :placeholder="$t('settings.addInfoAboutYou')"
            rows="3"
            class="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          />
          <Button
            size="sm"
            :disabled="!newMemoryText.trim() || savingMemory"
            @click="addMemory"
          >
            <Loader2 v-if="savingMemory" class="w-3.5 h-3.5 mr-1.5 animate-spin" />
            <Plus v-else class="w-3.5 h-3.5 mr-1.5" />
            {{ $t('settings.saveToMemory') }}
          </Button>
        </div>

        <!-- Divider -->
        <div class="border-t border-border" />

        <!-- Memory list -->
        <div class="space-y-1">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium">
              {{ $t('settings.savedFacts') }}
              <span v-if="memories.length > 0" class="ml-1.5 text-xs bg-muted text-muted-foreground px-1.5 py-0.5 rounded-full">
                {{ memories.length }}
              </span>
            </p>
            <button
              v-if="memories.length > 0"
              class="text-xs text-muted-foreground hover:text-destructive transition-colors"
              @click="confirmClearMemories"
            >
              {{ $t('settings.clearAll') }}
            </button>
          </div>

          <div v-if="memoriesLoading" class="text-xs text-muted-foreground py-3 text-center">
            <Loader2 class="w-4 h-4 animate-spin mx-auto" />
          </div>

          <div
            v-else-if="memories.length === 0"
            class="text-xs text-muted-foreground text-center py-4 bg-muted/30 rounded-lg"
          >
            {{ $t('settings.noFactsYet') }}
          </div>

          <div
            v-for="mem in memories"
            :key="mem.id"
            class="group flex items-start gap-2 px-3 py-2 rounded-lg hover:bg-muted/40 transition-colors"
          >
            <span class="text-muted-foreground text-xs mt-0.5 shrink-0">•</span>
            <p class="text-sm flex-1 leading-relaxed">{{ mem.content }}</p>
            <button
              class="opacity-0 group-hover:opacity-100 shrink-0 text-muted-foreground hover:text-destructive transition-all"
              @click="deleteMemory(mem.id)"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

      </CardContent>
    </Card>

    <!-- Password change -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base">{{ $t('settings.changePassword') }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <Input v-model="newPassword" type="password" :placeholder="$t('settings.newPassword')" />
        <p v-if="errors.password" class="text-xs text-destructive -mt-1">{{ errors.password }}</p>
        <p class="text-xs text-muted-foreground">{{ $t('settings.reloginAfterChange') }}</p>
        <Button @click="changePassword" :disabled="!newPassword || savingPassword">
          <Loader2 v-if="savingPassword" class="w-4 h-4 mr-2 animate-spin" />
          {{ $t('settings.changePassword') }}
        </Button>
      </CardContent>
    </Card>

    <!-- Telegram bot -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base flex items-center gap-2">
          <Send class="w-4 h-4" />
          {{ $t('settings.telegramBot') }}
        </CardTitle>
        <CardDescription>
          {{ $t('settings.connectTelegramBot') }}
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="bg-muted/50 rounded-lg p-3 text-sm space-y-1">
          <p class="font-medium">{{ $t('settings.connectInstructions') }}</p>
          <ol class="text-muted-foreground space-y-1 list-decimal list-inside">
            <li>{{ $t('settings.createBotVia') }} <span class="font-mono text-xs bg-background px-1 rounded">@BotFather</span> {{ $t('settings.inTelegram') }}</li>
            <li>{{ $t('settings.copyToken') }}</li>
            <li>{{ $t('settings.insertTokenBelow') }}</li>
            <li>{{ $t('settings.writeBot') }} <span class="font-mono text-xs bg-background px-1 rounded">/help</span></li>
          </ol>
        </div>
        <div class="bg-muted/30 rounded-lg p-3 text-sm">
          <p class="font-medium mb-1">{{ $t('settings.botCommands') }}</p>
          <p class="font-mono text-xs text-muted-foreground">/add 25.50 Coffee — {{ $t('settings.addExpense') }}</p>
          <p class="font-mono text-xs text-muted-foreground">/today — {{ $t('settings.todayExpenses') }}</p>
          <p class="font-mono text-xs text-muted-foreground">/help — {{ $t('settings.help') }}</p>
        </div>
        <Input
          v-model="telegramToken"
          type="password"
          placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz"
        />
        <p v-if="errors.telegram" class="text-xs text-destructive -mt-1">{{ errors.telegram }}</p>
        <div v-if="telegramStatus" class="text-sm" :class="telegramError ? 'text-destructive' : 'text-green-600'">
          {{ telegramStatus }}
        </div>
        <div class="flex items-center gap-x-3">
          <Button @click="connectTelegram" :disabled="!telegramToken || connectingTelegram">
            <Loader2 v-if="connectingTelegram" class="w-4 h-4 mr-2 animate-spin" />
            {{ $t('settings.connectBot') }}
          </Button>
          <p class="text-green-500 text-sm">
            <Check class="w-4 h-4 inline mr-1" />
            {{ $t('settings.botConnected') }}
          </p>
        </div>
      </CardContent>
    </Card>

    <!-- Notifications -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base flex items-center gap-2">
          <Bell class="w-4 h-4" />
          {{ $t('settings.notifications') }}
        </CardTitle>
        <CardDescription>{{ $t('settings.notificationsDesc') }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-if="!telegramConnected" class="bg-muted/50 rounded-lg p-3 text-sm text-muted-foreground">
          {{ $t('settings.notificationsRequireBot') }}
        </div>
        <div v-else class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-foreground">{{ $t('settings.deadlineNotifications') }}</p>
            <p class="text-xs text-muted-foreground mt-0.5">{{ $t('settings.deadlineNotificationsDesc') }}</p>
          </div>
          <Switch
            :model-value="deadlineNotifications"
            :disabled="savingNotifications"
            @update:model-value="toggleDeadlineNotifications"
          />
        </div>
      </CardContent>
    </Card>

    <!-- Work Tracker -->
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle class="text-base flex items-center gap-2">
              <BriefcaseBusiness class="w-4 h-4" />
              {{ $t('settings.workTracker') }}
            </CardTitle>
            <CardDescription class="mt-1">
              {{ $t('settings.webhookForShortcuts') }}
            </CardDescription>
          </div>
          <Switch
            v-model="workEnabled"
            :disabled="workToggling"
            @update:model-value="onWorkToggle"
          />
        </div>
      </CardHeader>

      <!-- Webhook content when enabled -->
      <CardContent v-if="workEnabled" class="space-y-4 pt-0">

        <div v-if="workToggling" class="flex items-center gap-2 text-sm text-muted-foreground py-1">
          <Loader2 class="w-4 h-4 animate-spin" />
          {{ $t('settings.generatingKey') }}
        </div>

        <template v-else>

        <!-- Registration status badge -->
        <div
          class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium"
          :class="workShortcutRegistered ? 'bg-green-500/10 text-green-400' : 'bg-muted/50 text-muted-foreground'"
        >
          <span
            class="w-2 h-2 rounded-full shrink-0"
            :class="workShortcutRegistered ? 'bg-green-400' : 'bg-slate-500 animate-pulse'"
          />
          <span v-if="workShortcutRegistered">
            {{ $t('settings.shortcutConnected') }}
            <span class="font-normal text-xs ml-1 opacity-70">{{ workShortcutRegisteredAt }}</span>
          </span>
          <span v-else>{{ $t('settings.waitingForFirstRequest') }}</span>
          <Loader2 v-if="!workShortcutRegistered" class="w-3.5 h-3.5 ml-auto animate-spin opacity-40" />
        </div>

        <!-- Webhook URL -->
        <div>
          <label class="text-sm font-medium mb-1.5 block">{{ $t('settings.webhookUrl') }}</label>
          <div class="flex gap-2">
            <input
              :value="workWebhookUrl"
              readonly
              :disabled="workShortcutRegistered"
              :class="{
                'cursor-not-allowed opacity-60': workShortcutRegistered,
                'cursor-text': !workShortcutRegistered
              }"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-mono ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <Button variant="outline" size="icon" @click="copyWebhookUrl" :title="workCopied ? $t('common.copied') : $t('settings.copy')">
              <Check v-if="workCopied" class="w-4 h-4 text-green-500" />
              <Copy v-else class="w-4 h-4" />
            </Button>
            <Button variant="outline" size="icon" @click="regenerateWebhookKey" :disabled="workRegenerating" :title="$t('settings.regenerate')">
              <Loader2 v-if="workRegenerating" class="w-4 h-4 animate-spin" />
              <RefreshCw v-else class="w-4 h-4" />
            </Button>
          </div>
        </div>

        <!-- QR Code -->
        <div class="flex flex-col items-start gap-2">
          <label class="text-sm font-medium">{{ $t('settings.qrCode') }}</label>
          <canvas ref="qrCanvas" class="rounded-lg border border-border" />
        </div>

        <!-- Instructions -->
        <div class="bg-muted/50 rounded-lg p-3 text-sm">
          <p class="font-medium mb-2">{{ $t('settings.iosSetup') }}</p>
          <ol class="text-muted-foreground space-y-1.5 list-decimal list-inside text-xs">
            <li>{{ $t('settings.openShortcuts') }}</li>
            <li>{{ $t('settings.tapAdd') }}</li>
            <li>{{ $t('settings.setArrivalOrDeparture') }}</li>
            <li>{{ $t('settings.addAction') }} <span class="font-mono bg-background px-1 rounded">{{ $t('settings.getContentsOfUrl') }}</span></li>
            <li>{{ $t('settings.insertUrlFromAbove') }}</li>
            <li>{{ $t('settings.method') }} <span class="font-mono bg-background px-1 rounded">{{ $t('settings.post') }}</span></li>
            <li>{{ $t('settings.requestBody') }} <span class="font-mono bg-background px-1 rounded">{"action":"toggle"}</span></li>
            <li>{{ $t('settings.saveAndAddToHome') }}</li>
            <li>{{ $t('settings.tapOnce') }}</li>
          </ol>
        </div>

        </template>

      </CardContent>
    </Card>

    <!-- Categories -->
    <Card>
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <div>
          <CardTitle class="text-base">{{ $t('settings.expenseCategories') }}</CardTitle>
          <CardDescription>{{ $t('settings.managingCategories') }}</CardDescription>
        </div>
        <Button size="sm" @click="showAddCategory = true">
          <Plus class="w-4 h-4 mr-1" />
          {{ $t('common.add') }}
        </Button>
      </CardHeader>
      <CardContent class="space-y-2">
        <div
          v-for="cat in categories"
          :key="cat.id"
          class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-muted/30 group"
        >
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color }" />
            <span class="text-sm">{{ cat.name }}</span>
          </div>
          <button
            class="opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive transition-all"
            @click="deleteCategory(cat)"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </CardContent>
    </Card>

    <!-- Add category dialog -->
    <Dialog v-model:open="showAddCategory">
      <DialogContent class="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>{{ $t('finance.newCategory') }}</DialogTitle>
        </DialogHeader>
        <div class="space-y-3">
          <Input v-model="newCat.name" :placeholder="$t('finance.categoryName')" />
          <p v-if="errors.category" class="text-xs text-destructive -mt-1">{{ errors.category }}</p>
          <div>
            <label class="text-sm font-medium mb-1.5 block">{{ $t('settings.color') }}</label>
            <input v-model="newCat.color" type="color" class="w-10 h-10 rounded cursor-pointer border border-border" />
          </div>
          <div class="flex gap-2 justify-end">
            <Button variant="outline" @click="showAddCategory = false">{{ $t('common.cancel') }}</Button>
            <Button @click="addCategory">{{ $t('common.add') }}</Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { Send, Loader2, Plus, Trash2, Check, Bot, Brain, BriefcaseBusiness, Copy, RefreshCw, Bell } from 'lucide-vue-next'
import { Switch } from '@/components/ui/switch'
import QRCode from 'qrcode'
import type { FormField } from '~/components/DynamicForm.vue'

definePageMeta({ middleware: 'auth' })

const api      = useApi()
const toast    = useToast()
const { logout } = useAuth()
const { $t, locale, setLocale } = useLocale()

const languageForm = ref<'en' | 'ru'>('ru')

const categories         = ref<any[]>([])
const saving             = ref(false)
const savingPassword     = ref(false)
const savingAi           = ref(false)
const connectingTelegram    = ref(false)
const showAddCategory       = ref(false)
const telegramStatus        = ref('')
const telegramError         = ref(false)
const aiApiKeySet           = ref(false)
const telegramConnected     = ref(false)
const deadlineNotifications = ref(false)
const savingNotifications   = ref(false)
const groqApiKeySet      = ref(false)
const jinaApiKeySet      = ref(false)

type Memory = { id: number; content: string; category: string | null; created_at: string }
const memories        = ref<Memory[]>([])
const memoriesLoading = ref(false)
const newMemoryText   = ref('')
const savingMemory    = ref(false)
const errors             = reactive({
  currency: '',
  symbol: '',
  aiProvider: '',
  password: '',
  telegram: '',
  category: '',
})

const currencyForm = ref<Record<string, any>>({ currency: 'USD', symbol: '$' })

const currencyFields: FormField[] = [
  { key: 'currency', label: 'Код валюты', type: 'text', required: true, placeholder: 'USD', maxLength: 10 },
  { key: 'symbol',   label: 'Символ',     type: 'text', required: true, placeholder: '$',   maxLength: 5  },
]
const newPassword  = ref('')
const telegramToken = ref('')
const newCat = reactive({ name: '', color: '#6366f1' })

const providerModels: Record<string, { label: string; value: string }[]> = {
  anthropic: [
    { label: 'Claude Sonnet 4.6 (рек.)',  value: 'claude-sonnet-4-6'         },
    { label: 'Claude Haiku 4.5',           value: 'claude-haiku-4-5-20251001' },
    { label: 'Claude Opus 4.6',            value: 'claude-opus-4-6'           },
  ],
  openai: [
    { label: 'GPT-4o Mini (рек.)', value: 'gpt-4o-mini'    },
    { label: 'GPT-4o',             value: 'gpt-4o'         },
    { label: 'GPT-3.5 Turbo',      value: 'gpt-3.5-turbo'  },
  ],
  groq: [
    { label: 'Llama 3.3 70B (рек.)',  value: 'llama-3.3-70b-versatile' },
    { label: 'Llama 3.1 8B (быстрый)', value: 'llama-3.1-8b-instant'   },
    { label: 'Mixtral 8x7B',           value: 'mixtral-8x7b-32768'     },
    { label: 'Gemma 2 9B',             value: 'gemma2-9b-it'           },
  ],
}

const aiForm = reactive({
  provider: 'anthropic',
  apiKey: '',
  groqApiKey: '',
  jinaApiKey: '',
  model: providerModels['anthropic']![0]!.value,
})

const onProviderChange = (value: any) => {
  aiForm.model = providerModels[value as string]?.[0]?.value ?? ''
}

// ── Work Tracker ──────────────────────────────────────────────────────────────
const workEnabled              = ref(false)   // mirrors whether a webhook key exists
const workToggling             = ref(false)
const workRegenerating         = ref(false)
const workWebhookUrl           = ref('')
const workCopied               = ref(false)
const workShortcutRegistered   = ref(false)
const workShortcutRegisteredAt = ref('')
const qrCanvas                 = ref<HTMLCanvasElement | null>(null)
let   workPollInterval: ReturnType<typeof setInterval> | null = null

const renderQr = async (url: string) => {
  await nextTick()
  if (qrCanvas.value && url) {
    await QRCode.toCanvas(qrCanvas.value, url, { width: 200, margin: 2, color: { dark: '#000000', light: '#ffffff' } })
  }
}

const pollWorkStatus = async () => {
  try {
    const data = await api.getWorkStatus() as any
    if (data?.shortcut_registered) {
      workShortcutRegistered.value   = true
      workShortcutRegisteredAt.value = data.shortcut_registered_at
        ? new Date(data.shortcut_registered_at).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' })
        : ''
      stopWorkPoll()
      toast.success(`iOS ${$t('settings.shortcutConnected')}!`)
    }
  } catch {}
}

const startWorkPoll = () => {
  if (workPollInterval) return
  workPollInterval = setInterval(pollWorkStatus, 3000)
}

const stopWorkPoll = () => {
  if (workPollInterval) { clearInterval(workPollInterval); workPollInterval = null }
}

onUnmounted(() => stopWorkPoll())

// v-model:checked already updated workEnabled before this is called
const onWorkToggle = async (enable: boolean) => {
  workToggling.value = true
  let urlToRender = ''
  try {
    const data = await api.setWorkEnabled(enable) as any
    if (enable) {
      urlToRender          = data?.url ?? ''
      workWebhookUrl.value = urlToRender
      if (!workShortcutRegistered.value) startWorkPoll()
    } else {
      stopWorkPoll()
      workWebhookUrl.value           = ''
      workShortcutRegistered.value   = false
      workShortcutRegisteredAt.value = ''
    }
  } catch {
    // rollback switch
    workEnabled.value = !enable
    toast.error($t('settings.errorChangingStatus'))
  } finally {
    workToggling.value = false
  }
  if (urlToRender) {
    await nextTick()
    await renderQr(urlToRender)
  }
}

const copyWebhookUrl = async () => {
  if (!workWebhookUrl.value) return
  await navigator.clipboard.writeText(workWebhookUrl.value)
  workCopied.value = true
  setTimeout(() => { workCopied.value = false }, 2000)
}

const regenerateWebhookKey = async () => {
  if (!confirm($t('settings.confirmKeyUpdate'))) return
  workRegenerating.value = true
  try {
    const data = await api.regenerateWorkKey() as any
    workWebhookUrl.value           = data?.url ?? ''
    workShortcutRegistered.value   = false
    workShortcutRegisteredAt.value = ''
    stopWorkPoll()
    await nextTick()
    await renderQr(workWebhookUrl.value)
    startWorkPoll()
    toast.success($t('settings.keyUpdated'))
  } catch {
    toast.error($t('settings.errorUpdatingKey'))
  } finally {
    workRegenerating.value = false
  }
}

const loadMemories = async () => {
  memoriesLoading.value = true
  try {
    memories.value = (await api.listMemories()) as Memory[]
  } finally {
    memoriesLoading.value = false
  }
}

const addMemory = async () => {
  const text = newMemoryText.value.trim()
  if (!text) return
  savingMemory.value = true
  try {
    await api.storeMemory(text)
    newMemoryText.value = ''
    await loadMemories()
  } finally {
    savingMemory.value = false
  }
}

const deleteMemory = async (id: number) => {
  await api.deleteMemory(id)
  memories.value = memories.value.filter(m => m.id !== id)
}

const confirmClearMemories = async () => {
  if (!confirm($t('settings.iosClearMemory'))) return
  await api.clearMemories()
  memories.value = []
}

onMounted(async () => {
  const [settings, cats] = await Promise.all([api.getSettings(), api.getCategories()])
  void loadMemories()

  // Restore work tracker state from DB
  try {
    const workStatus = await api.getWorkStatus() as any
    if (workStatus?.webhook_enabled) {
      const url = workStatus?.webhook_url ?? ''
      workWebhookUrl.value           = url
      workShortcutRegistered.value   = workStatus?.shortcut_registered ?? false
      workShortcutRegisteredAt.value = workStatus?.shortcut_registered_at
        ? new Date(workStatus.shortcut_registered_at).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' })
        : ''
      workEnabled.value = true
      if (url) {
        await nextTick()
        await renderQr(url)
      }
      if (!workShortcutRegistered.value) startWorkPoll()
    }
  } catch {}
  const s = settings as any
  languageForm.value         = s.language         || locale.value
  currencyForm.value.currency = s.currency        || 'USD'
  currencyForm.value.symbol   = s.currency_symbol || '$'
  aiForm.provider = s.ai_provider || 'anthropic'
  aiForm.model    = s.ai_model   || providerModels[aiForm.provider]?.[0]?.value || ''
  aiApiKeySet.value     = s.ai_api_key_set   || false
  groqApiKeySet.value   = s.groq_api_key_set || false
  jinaApiKeySet.value           = s.jina_api_key_set || false
  telegramConnected.value       = s.telegram_connected || false
  deadlineNotifications.value   = s.deadline_notifications === '1'
  categories.value              = cats as any[]
  telegramToken.value           = s.telegram_bot_token || ''

  // Restore locale from settings
  setLocale(languageForm.value)
})

const saveLanguage = async (lang: 'en' | 'ru') => {
  setLocale(lang)
  await api.updateSettings({ language: lang })
}

const saveCurrency = async (data: Record<string, any>) => {
  saving.value = true
  try {
    await api.updateSettings({ currency: data.currency, currency_symbol: data.symbol })
  } finally {
    saving.value = false
  }
}

const saveAi = async () => {
  errors.aiProvider = ''
  if (!aiForm.provider) {
    errors.aiProvider = $t('settings.selectProvider')
    return
  }
  savingAi.value = true
  try {
    const payload: any = { ai_provider: aiForm.provider }
    if (aiForm.apiKey)     payload.ai_api_key  = aiForm.apiKey
    if (aiForm.groqApiKey) payload.groq_api_key = aiForm.groqApiKey
    if (aiForm.jinaApiKey) payload.jina_api_key = aiForm.jinaApiKey
    if (aiForm.model)      payload.ai_model     = aiForm.model
    await api.updateSettings(payload)
    if (aiForm.apiKey)     { aiApiKeySet.value   = true; aiForm.apiKey     = '' }
    if (aiForm.groqApiKey) { groqApiKeySet.value = true; aiForm.groqApiKey = '' }
    if (aiForm.jinaApiKey) { jinaApiKeySet.value = true; aiForm.jinaApiKey = '' }
  } finally {
    savingAi.value = false
  }
}

const changePassword = async () => {
  errors.password = ''
  if (!newPassword.value.trim()) {
    errors.password = $t('settings.passwordRequired')
    return
  }
  if (newPassword.value.length < 4) {
    errors.password = $t('settings.minChars')
    return
  }
  savingPassword.value = true
  try {
    await api.updateSettings({ new_password: newPassword.value })
    newPassword.value = ''
    await logout()
  } finally {
    savingPassword.value = false
  }
}

const connectTelegram = async () => {
  errors.telegram = ''
  if (!telegramToken.value.trim()) {
    errors.telegram = $t('settings.enterToken')
    return
  }
  connectingTelegram.value = true
  telegramStatus.value     = ''
  telegramError.value      = false
  try {
    const res: any = await api.registerTelegram(telegramToken.value)
    telegramStatus.value    = '✓ ' + res.message
    telegramToken.value     = ''
    telegramConnected.value = true
  } catch (e: any) {
    telegramError.value  = true
    telegramStatus.value = e?.data?.message || $t('common.error')
  } finally {
    connectingTelegram.value = false
  }
}

const toggleDeadlineNotifications = async (val: boolean) => {
  savingNotifications.value = true
  try {
    deadlineNotifications.value = val
    await api.updateSettings({ deadline_notifications: val })
  } catch {
    deadlineNotifications.value = !val
  } finally {
    savingNotifications.value = false
  }
}

const addCategory = async () => {
  errors.category = ''
  if (!newCat.name.trim()) {
    errors.category = `${$t('finance.categoryName')} ${$t('common.required')}`
    return
  }
  await api.createCategory({ name: newCat.name, color: newCat.color })
  categories.value = (await api.getCategories()) as any[]
  newCat.name  = ''
  newCat.color = '#6366f1'
  showAddCategory.value = false
}

const deleteCategory = async (cat: any) => {
  if (!confirm(`${$t('settings.deleteConfirm')} "${cat.name}"?`)) return
  await api.deleteCategory(cat.id)
  categories.value = (await api.getCategories()) as any[]
}
</script>
