<template>
  <!-- h-[calc(100vh-6.5rem)] = 100vh - header(3.5rem) - main padding(3rem) -->
  <div class="flex gap-3 h-[calc(100vh-6.5rem)]">

    <!-- Left: conversation list -->
    <div class="w-56 shrink-0 flex flex-col gap-2">
      <Button class="w-full" size="sm" @click="newChat">
        <Plus class="w-4 h-4 mr-1" />
        {{ $t('ai.newChat') }}
      </Button>

      <Card class="flex-1 overflow-hidden flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto p-2 space-y-1">
          <div
            v-if="conversations.length === 0"
            class="text-xs text-muted-foreground text-center py-4"
          >
            {{ $t('ai.noChats') }}
          </div>
          <div
            v-for="conv in conversations"
            :key="conv.id"
            class="group flex items-start gap-1 rounded-lg px-2 py-2 cursor-pointer transition-colors"
            :class="conv.id === currentId
              ? 'bg-primary/10 text-primary'
              : 'hover:bg-muted/50 text-foreground'"
            @click="selectConversation(conv.id)"
          >
            <div class="flex-1 min-w-0">
              <p class="text-xs font-medium truncate leading-tight">{{ conv.title }}</p>
              <p class="text-[10px] text-muted-foreground truncate mt-0.5 leading-tight">
                {{ conv.preview || $t('ai.emptyChat') }}
              </p>
            </div>
            <button
              class="opacity-0 group-hover:opacity-100 shrink-0 mt-0.5 text-muted-foreground hover:text-destructive transition-all"
              @click.stop="removeConversation(conv.id)"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </Card>
    </div>

    <!-- Center: chat area -->
    <Card class="flex-1 flex flex-col overflow-hidden min-w-0">
      <CardHeader class="border-b border-border pb-3 shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <Bot class="w-5 h-5 text-primary" />
          </div>
          <div class="min-w-0 flex-1">
            <CardTitle class="text-base truncate">{{ currentTitle }}</CardTitle>
            <CardDescription>{{ $t('ai.subtitle') }}</CardDescription>
          </div>
        </div>
      </CardHeader>

      <!-- Messages -->
      <div ref="messagesRef" class="flex-1 overflow-y-auto p-4 space-y-4 min-h-0">
        <div v-if="messages.length === 0" class="text-center py-12 text-muted-foreground">
          <Bot class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p class="text-sm">{{ $t('ai.startConversation') }}</p>
          <p class="text-xs mt-1">{{ $t('ai.startHint') }}</p>
        </div>

        <div
          v-for="(msg, i) in messages"
          :key="i"
          class="flex gap-3"
          :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
        >
          <div
            v-if="msg.role === 'assistant'"
            class="w-7 h-7 bg-primary/10 rounded-full flex items-center justify-center shrink-0 mt-1"
          >
            <Bot class="w-4 h-4 text-primary" />
          </div>

          <div
            class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed"
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
            class="w-7 h-7 bg-secondary rounded-full flex items-center justify-center shrink-0 mt-1"
          >
            <User class="w-4 h-4" />
          </div>
        </div>
      </div>

      <!-- Input -->
      <div class="border-t border-border p-4 shrink-0">
        <form @submit.prevent="sendMessage" class="flex gap-2">
          <Input
            v-model="input"
            :placeholder="$t('ai.messagePlaceholder')"
            :disabled="streaming"
            class="flex-1"
            @keydown.enter.exact.prevent="sendMessage"
          />
          <Button type="submit" :disabled="!input.trim() || streaming">
            <Loader2 v-if="streaming" class="w-4 h-4 animate-spin" />
            <Send v-else class="w-4 h-4" />
          </Button>
        </form>
      </div>
    </Card>

  </div>
</template>

<script setup lang="ts">
import { Bot, User, Send, Loader2, Plus, Trash2 } from 'lucide-vue-next'
import { marked } from 'marked'

const renderMd = (text: string) => marked(text, { breaks: true }) as string

definePageMeta({ middleware: 'auth' })

const api         = useApi()
const { $t }     = useLocale()
const config      = useRuntimeConfig()
const messages    = ref<{ role: string; content: string; streaming?: boolean }[]>([])
const input       = ref('')
const streaming   = ref(false)
const messagesRef = ref<HTMLElement | null>(null)

type ConvSummary = { id: number; title: string; preview: string; updated_at: string }
const conversations = ref<ConvSummary[]>([])
const currentId     = ref<number | null>(null)
const currentTitle  = computed(() =>
  conversations.value.find(c => c.id === currentId.value)?.title ?? $t('ai.title')
)

onMounted(async () => {
  await loadConversations()
  if (currentId.value) {
    await loadMessages(currentId.value)
  }
})

const loadConversations = async () => {
  try {
    const list = (await api.listConversations()) as ConvSummary[]
    conversations.value = list
    if (!currentId.value && list.length > 0) {
      currentId.value = list[0]!.id
    }
  } catch {}
}

const loadMessages = async (id: number) => {
  try {
    const res: any = await api.getConversation(id)
    messages.value  = res.messages || []
    currentId.value = res.id
    scrollToBottom()
  } catch {}
}

const selectConversation = async (id: number) => {
  if (id === currentId.value || streaming.value) return
  messages.value = []
  await loadMessages(id)
}

const newChat = async () => {
  if (streaming.value) return
  try {
    const res: any = await api.createConversation()
    conversations.value.unshift({ id: res.id, title: res.title, preview: '', updated_at: new Date().toISOString() })
    currentId.value = res.id
    messages.value  = []
  } catch {}
}

const removeConversation = async (id: number) => {
  if (!confirm($t('ai.deleteConversation'))) return
  try {
    await api.deleteConversation(id)
    conversations.value = conversations.value.filter(c => c.id !== id)
    if (currentId.value === id) {
      currentId.value = conversations.value[0]?.id ?? null
      messages.value  = []
      if (currentId.value) await loadMessages(currentId.value)
    }
  } catch {}
}

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesRef.value) {
      messagesRef.value.scrollTop = messagesRef.value.scrollHeight
    }
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
    if (currentId.value) {
      const fresh = conversations.value.find(c => c.id === currentId.value)
      if (fresh) currentId.value = fresh.id
    }
  } catch {
    assistantMsg.content = $t('ai.connectionError')
  } finally {
    assistantMsg.streaming = false
    streaming.value        = false
    scrollToBottom()
  }
}
</script>
