<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useThemeStore } from '../../stores/theme'
import { useAuthStore } from '../../stores/auth'
import MetalTitle from '../effects/MetalTitle.vue'
import Modal from './Modal.vue'

const router = useRouter()
const themeStore = useThemeStore()
const auth = useAuthStore()
const helpOpen = ref(false)

function go(path) {
  router.push(path)
}
</script>

<template>
  <header class="topbar">
    <div class="topbar-left" @click="go('/')" style="cursor: pointer">
      <svg class="brand-logo" viewBox="0 0 64 64" aria-hidden="true">
        <defs>
          <pattern id="zharanPattern" x="0" y="0" width="16" height="16" patternUnits="userSpaceOnUse">
            <circle cx="8" cy="8" r="3" fill="#185FA5" />
            <circle cx="0" cy="0" r="2" fill="#378ADD" />
            <circle cx="16" cy="16" r="2" fill="#378ADD" />
          </pattern>
        </defs>
        <circle cx="32" cy="32" r="30" fill="url(#zharanPattern)" />
        <circle cx="32" cy="32" r="30" fill="none" stroke="#185FA5" stroke-width="2" />
        <text x="32" y="40" text-anchor="middle" font-size="22" font-weight="700" fill="#fff" font-family="serif">白</text>
      </svg>
      <div class="brand-text">
        <div class="brand-title"><MetalTitle size="19px">童画苍洱</MetalTitle></div>
        <div class="brand-sub">AIGC · 大理白族幼儿绘本资源工坊</div>
      </div>
    </div>
    <div class="topbar-right">
      <button class="btn ghost sm top-btn" :class="{ active: themeStore.erhaiOn }" @click="themeStore.toggleErhai()" title="洱海氛围背景开关">
        <span>🌊</span><span>氛围</span>
      </button>
      <button class="btn ghost sm top-btn" @click="go('/library')" title="文化库(绘本/素材/符号)">
        <span>🏛️</span><span>文化库</span>
      </button>
      <button class="btn ghost sm top-btn" @click="go('/works')" title="我的作品">
        <span>📚</span><span>我的作品</span>
      </button>
      <button v-if="auth.hasRole('admin', 'reviewer')" class="btn ghost sm top-btn" @click="go('/admin')" title="审核后台">
        <span>🛡️</span><span>审核</span>
      </button>
      <button class="btn ghost sm top-btn" @click="helpOpen = true" title="使用帮助">
        <span>❓</span><span>帮助</span>
      </button>
      <template v-if="auth.isLoggedIn">
        <span class="user-chip" :title="'角色:' + auth.user.role">{{ auth.user.display_name || auth.user.username }}</span>
        <button class="btn ghost sm top-btn" @click="auth.logout()">退出</button>
      </template>
      <button v-else class="btn ghost sm top-btn" @click="go('/login')">登录</button>
    </div>
  </header>

  <Modal :open="helpOpen" title="使用帮助" @close="helpOpen = false">
    <h3>这是做什么的?</h3>
    <p>面向大理乡村/民族幼儿园教师的 AI 资源生成工具,三步产出可用的教学资源:</p>
    <ol>
      <li><b>选主题</b>:扎染 / 三道茶 / 洱海 / 白族民俗等</li>
      <li><b>配参数</b>:年龄段、页数、风格</li>
      <li><b>一键生成</b>:得到绘本,可编辑、可导出 PDF/PNG/素材包</li>
    </ol>
    <h3>数据库优先 · AI 辅助</h3>
    <p>故事内容以人工审核过的白族文化素材库为底座(保证文化准确),AI 大模型只做生成与润色;AI 不可用时自动降级为素材库拼装,依然可用。</p>
    <h3>数据安全</h3>
    <p>作品保存在数据库,可导出;AI 调用全部留痕(ai_logs),满足 AIGC 披露要求。</p>
  </Modal>
</template>

<style scoped>
.topbar {
  position: sticky;
  top: 0;
  z-index: 100;
  height: var(--topbar-h);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  background: rgba(255, 255, 255, 0.86);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(43, 75, 124, 0.12);
}
.topbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.brand-logo { width: 38px; height: 38px; }
.brand-title { line-height: 1.2; }
.brand-sub { font-size: 11px; color: var(--indigo-soft); letter-spacing: 1px; }
.topbar-right { display: flex; align-items: center; gap: 8px; }
.top-btn { display: flex; align-items: center; gap: 4px; }
.top-btn.active { background: #e8f0fb; border-color: var(--indigo); }
.user-chip {
  font-size: 12px;
  color: var(--indigo);
  background: #eef3fb;
  padding: 4px 10px;
  border-radius: 12px;
}
h3 { margin: 12px 0 6px; color: var(--indigo-deep); font-size: 15px; }
p, li { font-size: 13.5px; line-height: 1.8; color: var(--ink); }
ol { padding-left: 20px; }
</style>
