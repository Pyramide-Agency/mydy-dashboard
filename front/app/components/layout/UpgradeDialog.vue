<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="showDialog"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close" />

        <div class="relative z-10 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border" style="background: hsl(var(--sidebar-bg)); border-color: hsl(var(--sidebar-border));">
          <!-- Header -->
          <div class="flex items-start justify-between p-5 border-b" style="border-color: hsl(var(--sidebar-border));">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="text-xs px-2 py-0.5 rounded-full font-mono nav-item--active">
                  {{ release?.tag_name }}
                </span>
                <span class="text-xs" style="color: hsl(215 20% 45%)">{{ publishedDate }}</span>
              </div>
              <h2 class="font-heading text-lg font-bold text-white">
                {{ release?.name || release?.tag_name }}
              </h2>
            </div>
            <button
              class="p-1 rounded transition-colors"
              style="color: hsl(215 20% 45%)"
              @click="close"
            >
              <X class="size-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 max-h-96 overflow-y-auto">
            <pre v-if="release?.body" class="whitespace-pre-wrap font-sans text-sm leading-relaxed" style="color: hsl(213 31% 75%)">{{ release.body }}</pre>
            <p v-else class="text-sm" style="color: hsl(215 20% 45%)">No release notes provided.</p>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between gap-3 px-5 py-4 border-t" style="border-color: hsl(var(--sidebar-border)); background: hsl(217 33% 11%)">
            <span class="text-xs" style="color: hsl(215 20% 45%)">
              Pull the latest changes to update your installation.
            </span>
            <a
              :href="release?.html_url"
              target="_blank"
              rel="noopener"
              class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg font-medium transition-opacity hover:opacity-90 nav-item--active"
              style="background: rgb(99 102 241 / 0.14)"
            >
              View on GitHub
              <ExternalLink class="size-3" />
            </a>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { X, ExternalLink } from 'lucide-vue-next'

const { release, showDialog, close } = useRelease()

const publishedDate = computed(() => {
  if (!release.value?.published_at) return ''
  return new Date(release.value.published_at).toLocaleDateString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
  })
})
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.nav-item--active {
  background: rgb(99 102 241 / 0.14);
  color: rgb(129 140 248);
}
</style>
