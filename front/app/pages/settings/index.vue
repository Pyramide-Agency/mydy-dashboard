<template>
  <div class="max-w-2xl space-y-6">
    <!-- Currency settings -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base">Валюта</CardTitle>
        <CardDescription>Настройка отображения валюты</CardDescription>
      </CardHeader>
      <CardContent>
        <DynamicForm
          v-model="currencyForm"
          :fields="currencyFields"
          submit-label="Сохранить"
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
          AI Провайдер
        </CardTitle>
        <CardDescription>
          Настройка провайдера и ключа для AI-советника и анализа расходов
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div>
          <label class="text-sm font-medium mb-1.5 block">Провайдер</label>
          <Select v-model="aiForm.provider" @update:modelValue="onProviderChange">
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="anthropic">Anthropic (Claude)</SelectItem>
              <SelectItem value="openai">OpenAI (GPT)</SelectItem>
              <SelectItem value="groq">Groq (бесплатно)</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.aiProvider" class="text-xs text-destructive mt-1">{{ errors.aiProvider }}</p>
        </div>
        <div>
          <label class="text-sm font-medium mb-1.5 block">API ключ</label>
          <Input
            v-model="aiForm.apiKey"
            type="password"
            :placeholder="aiApiKeySet ? '●●●●●●●● (ключ сохранён)' : 'sk-ant-... или sk-...'"
          />
        </div>
        <div>
          <label class="text-sm font-medium mb-1.5 block">Модель</label>
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
          Сохранить
        </Button>
      </CardContent>
    </Card>

    <!-- Password change -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base">Изменить пароль</CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <Input v-model="newPassword" type="password" placeholder="Новый пароль" />
        <p v-if="errors.password" class="text-xs text-destructive -mt-1">{{ errors.password }}</p>
        <p class="text-xs text-muted-foreground">После смены пароля потребуется повторный вход</p>
        <Button @click="changePassword" :disabled="!newPassword || savingPassword">
          <Loader2 v-if="savingPassword" class="w-4 h-4 mr-2 animate-spin" />
          Сменить пароль
        </Button>
      </CardContent>
    </Card>

    <!-- Telegram bot -->
    <Card>
      <CardHeader>
        <CardTitle class="text-base flex items-center gap-2">
          <Send class="w-4 h-4" />
          Telegram бот
        </CardTitle>
        <CardDescription>
          Подключите Telegram бота для добавления расходов через мессенджер
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="bg-muted/50 rounded-lg p-3 text-sm space-y-1">
          <p class="font-medium">Как подключить:</p>
          <ol class="text-muted-foreground space-y-1 list-decimal list-inside">
            <li>Создайте бота через <span class="font-mono text-xs bg-background px-1 rounded">@BotFather</span> в Telegram</li>
            <li>Скопируйте токен бота</li>
            <li>Вставьте токен ниже и сохраните</li>
            <li>Напишите боту <span class="font-mono text-xs bg-background px-1 rounded">/help</span></li>
          </ol>
        </div>
        <div class="bg-muted/30 rounded-lg p-3 text-sm">
          <p class="font-medium mb-1">Команды бота:</p>
          <p class="font-mono text-xs text-muted-foreground">/add 25.50 Кофе — добавить расход</p>
          <p class="font-mono text-xs text-muted-foreground">/today — расходы за сегодня</p>
          <p class="font-mono text-xs text-muted-foreground">/help — помощь</p>
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
            Подключить бота
          </Button>
          <p class="text-green-500 text-sm">
            <Check class="w-4 h-4 inline mr-1" />
            Telegram Bot уже подключен
          </p>
        </div>
      </CardContent>
    </Card>

    <!-- Categories -->
    <Card>
      <CardHeader class="flex flex-row items-center justify-between pb-3">
        <div>
          <CardTitle class="text-base">Категории расходов</CardTitle>
          <CardDescription>Управление категориями</CardDescription>
        </div>
        <Button size="sm" @click="showAddCategory = true">
          <Plus class="w-4 h-4 mr-1" />
          Добавить
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
          <DialogTitle>Новая категория</DialogTitle>
        </DialogHeader>
        <div class="space-y-3">
          <Input v-model="newCat.name" placeholder="Название" />
          <p v-if="errors.category" class="text-xs text-destructive -mt-1">{{ errors.category }}</p>
          <div>
            <label class="text-sm font-medium mb-1.5 block">Цвет</label>
            <input v-model="newCat.color" type="color" class="w-10 h-10 rounded cursor-pointer border border-border" />
          </div>
          <div class="flex gap-2 justify-end">
            <Button variant="outline" @click="showAddCategory = false">Отмена</Button>
            <Button @click="addCategory">Добавить</Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { Send, Loader2, Plus, Trash2, Check, Bot } from 'lucide-vue-next'
import type { FormField } from '~/components/DynamicForm.vue'

definePageMeta({ middleware: 'auth' })

const api = useApi()
const { logout } = useAuth()

const categories         = ref<any[]>([])
const saving             = ref(false)
const savingPassword     = ref(false)
const savingAi           = ref(false)
const connectingTelegram = ref(false)
const showAddCategory    = ref(false)
const telegramStatus     = ref('')
const telegramError      = ref(false)
const aiApiKeySet        = ref(false)
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
  model: providerModels['anthropic']![0]!.value,
})

const onProviderChange = (value: any) => {
  aiForm.model = providerModels[value as string]?.[0]?.value ?? ''
}

onMounted(async () => {
  const [settings, cats] = await Promise.all([api.getSettings(), api.getCategories()])
  const s = settings as any
  currencyForm.value.currency = s.currency        || 'USD'
  currencyForm.value.symbol   = s.currency_symbol || '$'
  aiForm.provider = s.ai_provider || 'anthropic'
  aiForm.model    = s.ai_model   || providerModels[aiForm.provider]?.[0]?.value || ''
  aiApiKeySet.value     = s.ai_api_key_set  || false
  categories.value      = cats as any[]
  telegramToken.value   = s.telegram_bot_token || ''
})

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
    errors.aiProvider = 'Выберите провайдера'
    return
  }
  savingAi.value = true
  try {
    const payload: any = { ai_provider: aiForm.provider }
    if (aiForm.apiKey)  payload.ai_api_key = aiForm.apiKey
    if (aiForm.model)   payload.ai_model   = aiForm.model
    await api.updateSettings(payload)
    if (aiForm.apiKey) {
      aiApiKeySet.value = true
      aiForm.apiKey = ''
    }
  } finally {
    savingAi.value = false
  }
}

const changePassword = async () => {
  errors.password = ''
  if (!newPassword.value.trim()) {
    errors.password = 'Введите новый пароль'
    return
  }
  if (newPassword.value.length < 4) {
    errors.password = 'Минимум 4 символа'
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
    errors.telegram = 'Введите токен бота'
    return
  }
  connectingTelegram.value = true
  telegramStatus.value     = ''
  telegramError.value      = false
  try {
    const res: any = await api.registerTelegram(telegramToken.value)
    telegramStatus.value = '✓ ' + res.message
    telegramToken.value  = ''
  } catch (e: any) {
    telegramError.value  = true
    telegramStatus.value = e?.data?.message || 'Ошибка подключения'
  } finally {
    connectingTelegram.value = false
  }
}

const addCategory = async () => {
  errors.category = ''
  if (!newCat.name.trim()) {
    errors.category = 'Введите название категории'
    return
  }
  await api.createCategory({ name: newCat.name, color: newCat.color })
  categories.value = (await api.getCategories()) as any[]
  newCat.name  = ''
  newCat.color = '#6366f1'
  showAddCategory.value = false
}

const deleteCategory = async (cat: any) => {
  if (!confirm(`Удалить категорию "${cat.name}"?`)) return
  await api.deleteCategory(cat.id)
  categories.value = (await api.getCategories()) as any[]
}
</script>
