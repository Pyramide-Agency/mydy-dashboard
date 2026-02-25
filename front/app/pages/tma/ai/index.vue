<template>
  <div class="flex flex-col h-full p-4 gap-3">

    <!-- Header -->
    <div class="flex items-center gap-2 shrink-0">
      <div class="flex-1 min-w-0">
        <h1 class="text-base font-semibold text-foreground truncate">{{ $t('ai.title') }}</h1>
      </div>
      <Button variant="outline" size="sm" @click="newChat" :disabled="streaming">
        <Plus class="w-4 h-4" />
      </Button>
    </div>

    <!-- Conversation selector -->
    <div v-if="conversations.length > 0" class="shrink-0">
      <Select v-model="selectedId" @update:model-value="onSelectChange">
        <SelectTrigger class="text-sm">
          <SelectValue :placeholder="$t('ai.conversations')" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="conv in conversations"
            :key="conv.id"
            :value="String(conv.id)"
          >
            <span class="truncate">{{ conv.title }}</span>
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Messages -->
    <div
      ref="messagesRef"
      class="flex-1 overflow-y-auto bg-white rounded-xl border border-border shadow-sm p-3 space-y-3 min-h-0"
    >
      <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-center py-10">
        <Bot class="w-10 h-10 text-muted-foreground/30 mb-2" />
        <p class="text-sm font-medium text-foreground">{{ $t('ai.title') }}</p>
        <p class="text-xs text-muted-foreground mt-1">{{ $t('ai.startHint') }}</p>
      </div>

      <div
        v-for="(msg, i) in messages"
        :key="i"
        class="flex gap-2"
        :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
      >
        <div
          v-if="msg.role === 'assistant'"
          class="w-6 h-6 bg-primary/10 rounded-full flex items-center justify-center shrink-0 mt-1"
        >
          <Bot class="w-3.5 h-3.5 text-primary" />
        </div>

        <div
          class="max-w-[80%] rounded-2xl px-3 py-2 text-sm leading-relaxed"
          :class="msg.role === 'user'
            ? 'bg-primary text-primary-foreground rounded-tr-sm'
            : 'bg-muted text-foreground rounded-tl-sm'"
        >
          <span v-if="msg.role === 'user'" class="whitespace-pre-wrap">{{ msg.content }}</span>
          <span v-else class="prose prose-sm dark:prose-invert max-w-none" v-html="renderMd(msg.content)" />
          <span v-if="msg.streaming" class="animate-pulse">▋</span>
        </div>

        <div
          v-if="msg.role === 'user'"
          class="w-6 h-6 bg-secondary rounded-full flex items-center justify-center shrink-0 mt-1"
        >
          <User class="w-3.5 h-3.5" />
        </div>
      </div>
    </div>

    <!-- Input -->
    <div class="shrink-0">
      <form @submit.prevent="sendMessage" class="flex gap-2">
        <Input
          v-model="input"
          :placeholder="$t('ai.messagePlaceholder')"
          :disabled="streaming"
          class="flex-1 text-sm"
          @keydown.enter.exact.prevent="sendMessage"
        />
        <Button type="submit" size="sm" :disabled="!input.trim() || streaming">
          <Loader2 v-if="streaming" class="w-4 h-4 animate-spin" />
          <Send v-else class="w-4 h-4" />
        </Button>
      </form>
    </div>

  </div>
</template>

<script setup lang="ts">
import { Bot, User, Send, Loader2, Plus } from 'lucide-vue-next'
import { marked } from 'marked'

const renderMd = (text: string) => marked(text, { breaks: true }) as string

definePageMeta({ layout: 'telegram', middleware: 'tma-auth' })

const api         = useApi()
const config      = useRuntimeConfig()
const { $t } = useLocale()
const messages    = ref<{ role: string; content: string; streaming?: boolean }[]>([])
const input       = ref('')
const streaming   = ref(false)
const messagesRef = ref<HTMLElement | null>(null)

const { showBackButton, hideBackButton } = useTelegram()

type ConvSummary = { id: number; title: string; preview: string; updated_at: string }
const conversations = ref<ConvSummary[]>([])
const currentId     = ref<number | null>(null)
const selectedId    = ref<string>('')

onMounted(async () => {
  showBackButton(() => navigateTo('/tma/'))
  await loadConversations()
  if (currentId.value) await loadMessages(currentId.value)
})

onUnmounted(() => hideBackButton())

const loadConversations = async () => {
  try {
    const list = (await api.listConversations()) as ConvSummary[]
    conversations.value = list
    if (!currentId.value && list.length > 0) {
      currentId.value = list[0]!.id
      selectedId.value = String(list[0]!.id)
    }
  } catch {}
}

const loadMessages = async (id: number) => {
  try {
    const res: any = await api.getConversation(id)
    messages.value  = res.messages || []
    currentId.value = res.id
    selectedId.value = String(res.id)
    scrollToBottom()
  } catch {}
}

const onSelectChange = async (val: string) => {
  if (streaming.value) return
  const id = parseInt(val)
  if (id === currentId.value) return
  messages.value = []
  await loadMessages(id)
}

const newChat = async () => {
  if (streaming.value) return
  try {
    const res: any = await api.createConversation()
    conversations.value.unshift({ id: res.id, title: res.title, preview: '', updated_at: new Date().toISOString() })
    currentId.value  = res.id
    selectedId.value = String(res.id)
    messages.value   = []
  } catch {}
}

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesRef.value) messagesRef.value.scrollTop = messagesRef.value.scrollHeight
  })
}

const sendMessage = async () => {
  const text = input.value.trim()
  if (!text || streaming.value) return

  if (!currentId.value) {
    await newChat()
    if (!currentId.value) return
  }

  input.value     = ''
  streaming.value = true

  messages.value.push({ role: 'user', content: text })
  const assistantMsg = reactive({ role: 'assistant', content: '', streaming: true })
  messages.value.push(assistantMsg)
  scrollToBottom()

  try {
    const token    = import.meta.client ? localStorage.getItem('auth_token') : ''
    const response = await fetch(`${config.public.apiBase}/finance/ai-conversation`, {
      method:  'POST',
      headers: {
        'Content-Type':  'application/json',
        'Accept':        'text/event-stream',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify({ message: text, conversation_id: currentId.value }),
    })

    const reader  = response.body!.getReader()
    const decoder = new TextDecoder()
    let sseBuffer = ''
    let finished  = false

    while (!finished) {
      const { done, value } = await reader.read()
      if (done) break

      sseBuffer += decoder.decode(value, { stream: true })

      let sep: number
      while ((sep = sseBuffer.indexOf('\n\n')) !== -1) {
        const event = sseBuffer.slice(0, sep)
        sseBuffer   = sseBuffer.slice(sep + 2)

        for (const line of event.split('\n')) {
          if (!line.startsWith('data: ')) continue
          const data = line.slice(6)
          if (data === '[DONE]') { finished = true; break }
          try {
            const parsed = JSON.parse(data)
            if (parsed.chunk) { assistantMsg.content += parsed.chunk; scrollToBottom() }
            if (parsed.error) { assistantMsg.content = `${$t('common.error')}: ${parsed.error}` }
          } catch {}
        }
        if (finished) break
      }
    }

    await loadConversations()
  } catch {
    assistantMsg.content = $t('ai.connectionError')
  } finally {
    assistantMsg.streaming = false
    streaming.value        = false
    scrollToBottom()
  }
}
</script>
