import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('canger_token') || '')
  const user = ref(JSON.parse(localStorage.getItem('canger_user') || 'null'))

  const isLoggedIn = computed(() => !!token.value)

  function hasRole(...roles) {
    return !!user.value && roles.includes(user.value.role)
  }

  async function login(username, password) {
    const data = await api.login(username, password)
    token.value = data.token
    user.value = data.user
    localStorage.setItem('canger_token', data.token)
    localStorage.setItem('canger_user', JSON.stringify(data.user))
    return data.user
  }

  function logout() {
    token.value = ''
    user.value = null
    localStorage.removeItem('canger_token')
    localStorage.removeItem('canger_user')
  }

  return { token, user, isLoggedIn, hasRole, login, logout }
})
