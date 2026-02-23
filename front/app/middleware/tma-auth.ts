export default defineNuxtRouteMiddleware((to) => {
  if (to.path === '/tma/login') return

  if (import.meta.client) {
    const token = localStorage.getItem('auth_token')
    if (!token) {
      return navigateTo('/tma/login')
    }
  }
})
