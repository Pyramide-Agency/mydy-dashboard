<template>
  <span class="inline-flex items-center gap-1.5" :class="small ? 'text-xs' : 'text-sm'">
    <span
      class="rounded-full shrink-0"
      :class="small ? 'w-2 h-2' : 'w-3 h-3'"
      :style="{ backgroundColor: category?.color || '#6b7280' }"
    />
    <span class="text-muted-foreground">{{ category?.name || $t('finance.noCategory') }}</span>
  </span>
</template>

<script setup lang="ts">
const { $t } = useLocale()
defineProps<{
  category?: { name: string; color: string } | null
  small?: boolean
}>()
</script>
