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
onMounted(() => initAuth())
</script>
