<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { toastOk, toastErr } from '../composables/useToast'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const username = ref('')
const password = ref('')
const busy = ref(false)

async function doLogin() {
  busy.value = true
  try {
    const user = await auth.login(username.value, password.value)
    toastOk(`欢迎,${user.display_name || user.username}`)
    router.replace('/')
  } catch (e) {
    toastErr('登录失败:' + e.message)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="login-view">
    <div class="login-card card">
      <h2 class="login-title">🔐 登录童画苍洱</h2>
      <p class="login-tip">演示账号:admin/admin123(审核) · teacher/teacher123(教师)</p>
      <div class="field">
        <label>用户名</label>
        <input v-model="username" @keyup.enter="doLogin" />
      </div>
      <div class="field">
        <label>密码</label>
        <input v-model="password" type="password" @keyup.enter="doLogin" />
      </div>
      <button class="btn login-btn" :disabled="busy" @click="doLogin">{{ busy ? '登录中…' : '登录' }}</button>
      <button class="btn ghost login-btn" @click="router.replace('/')">返回首页</button>
    </div>
  </div>
</template>

<style scoped>
.login-view { display: flex; justify-content: center; padding-top: 8vh; }
.login-card { width: min(380px, 92vw); padding: 28px; }
.login-title { font-size: 20px; color: var(--indigo-deep); margin-bottom: 6px; text-align: center; }
.login-tip { font-size: 12px; color: #9aa6b8; text-align: center; margin-bottom: 18px; }
.login-btn { width: 100%; margin-top: 6px; }
</style>
