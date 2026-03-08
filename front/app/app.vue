<template>
  <div>
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <ToastContainer />
  </div>
</template>

<script setup lang="ts">
const { initAuth } = useAuth()
const { me } = useApi()
const { initTheme } = useTheme()
onMounted(() => {
  initTheme()
  initAuth()
  me()
})
</script>
