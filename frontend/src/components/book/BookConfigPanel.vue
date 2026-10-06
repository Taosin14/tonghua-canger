<script setup>
import { ref } from 'vue'
import { api } from '../../api'
import { useThemeStore } from '../../stores/theme'
import { toast, toastOk, toastErr } from '../../composables/useToast'
import { AGE_OPTIONS, STYLE_OPTIONS, THEMES } from '../../utils/maps'
import CornerConnectButton from '../effects/CornerConnectButton.vue'
import WaveBallLoading from '../effects/WaveBallLoading.vue'

// 生成参数表单:主题/年龄段/页数/风格 -> 一键生成(降级提示)
const themeStore = useThemeStore()
const quota = ref(null)
const cooldown = ref(false)

async function loadQuota() {
  try {
    quota.value = await api.quota()
  } catch { /* 忽略 */ }
}
loadQuota()

async function generate() {
  if (cooldown.value) return
  cooldown.value = true
  setTimeout(() => { cooldown.value = false }, 3000)
  themeStore.generating = true
  themeStore.currentStory = null
  try {
    const data = await api.generateStory({ ...themeStore.genParams })
    themeStore.currentStory = data.story
    themeStore.lastAiUsed = data.ai_used
    themeStore.lastMessage = data.message
    if (data.ai_used) toastOk(`已生成《${data.story.title}》(GLM 大模型)`)
    else if (data.reused) toastOk(data.message)
    else toast(data.message, 'info', 5000)
    loadQuota()
  } catch (e) {
    toastErr('生成失败:' + e.message)
  } finally {
    themeStore.generating = false
  }
}
</script>

<template>
  <div class="config-panel">
    <div class="field">
      <label>主题(输入时点选建议,或从左侧主题列表选)</label>
      <input v-model="themeStore.genParams.theme" placeholder="如:扎染 / 火把节 / 洱海…" list="theme-suggestions" />
      <datalist id="theme-suggestions">
        <option v-for="t in THEMES" :key="t" :value="t"></option>
      </datalist>
    </div>
    <div class="field-row">
      <div class="field">
        <label>年龄段</label>
        <select v-model="themeStore.genParams.age_band">
          <option v-for="a in AGE_OPTIONS" :key="a.value" :value="a.value">{{ a.label }}</option>
        </select>
      </div>
      <div class="field">
        <label>页数</label>
        <select v-model.number="themeStore.genParams.page_count">
          <option v-for="n in [6, 8, 10, 12]" :key="n" :value="n">{{ n }} 页</option>
        </select>
      </div>
      <div class="field">
        <label>风格</label>
        <select v-model="themeStore.genParams.style">
          <option v-for="s in STYLE_OPTIONS" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
    </div>

    <div v-if="themeStore.generating" class="generating card">
      <WaveBallLoading text="检索素材 → 拼装 → AI 润色 → 装订中…" />
    </div>
    <div v-else class="gen-actions">
      <CornerConnectButton @click="generate">✨ 一键生成</CornerConnectButton>
      <span v-if="quota" class="quota-tip">
        AI 配额 {{ quota.used_today }}/{{ quota.limit }} ·
        {{ quota.available ? 'GLM 已接入' : '未配置 Key(降级拼装模式)' }}
      </span>
    </div>

    <div v-if="themeStore.currentStory" class="gen-result fade-in-up">
      <div class="result-title">
        📖 已生成:《{{ themeStore.currentStory.title }}》
        <span class="badge">{{ themeStore.currentStory.page_count }}页 · {{ themeStore.currentStory.age_band }}岁</span>
        <span v-if="themeStore.lastAiUsed" class="badge ai-badge">✨ AI 生成</span>
        <span v-else class="badge">🧩 素材拼装</span>
      </div>
      <p class="result-msg">{{ themeStore.lastMessage }}</p>
      <div class="result-btns">
        <router-link :to="`/stories/${themeStore.currentStory.id}`"><button class="btn">📖 去阅读</button></router-link>
        <router-link :to="`/stories/${themeStore.currentStory.id}/edit`"><button class="btn ghost">✏️ 去编辑</button></router-link>
      </div>
    </div>
  </div>
</template>

<style scoped>
.config-panel { max-width: 560px; }
.field-row { display: flex; gap: 12px; }
.field-row .field { flex: 1; }
.generating { margin: 18px 0; }
.gen-actions { display: flex; align-items: center; gap: 14px; margin-top: 18px; flex-wrap: wrap; }
.quota-tip { font-size: 12px; color: #8a97ab; }
.gen-result {
  margin-top: 20px;
  background: #f0f5fc;
  border: 1px solid #d5e2f2;
  border-radius: 14px;
  padding: 14px 16px;
}
.result-title { font-size: 15px; font-weight: 700; color: var(--indigo-deep); margin-bottom: 6px; }
.result-msg { font-size: 13px; color: var(--indigo-soft); line-height: 1.7; margin-bottom: 10px; }
.result-btns { display: flex; gap: 10px; }
.ai-badge { background: #e8f0fb; color: var(--indigo); }
</style>
