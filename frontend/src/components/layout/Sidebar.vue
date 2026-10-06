<script setup>
import { THEMES } from '../../utils/maps'
import { useThemeStore } from '../../stores/theme'

// 左侧主题导航:选择主题 -> 同步到生成参数
const themeStore = useThemeStore()
</script>

<template>
  <aside class="sidebar card">
    <div class="sidebar-title">🧭 主题导航</div>
    <p class="sidebar-tip">点击主题,右侧配置生成参数</p>
    <ul class="theme-list">
      <li
        v-for="t in THEMES"
        :key="t"
        class="theme-item"
        :class="{ on: themeStore.genParams.theme === t }"
        @click="themeStore.genParams.theme = t"
      >
        <span class="theme-dot"></span>{{ t }}
      </li>
    </ul>
  </aside>
</template>

<style scoped>
.sidebar { width: 172px; flex-shrink: 0; max-height: calc(100vh - var(--topbar-h) - 36px); overflow-y: auto; }
.sidebar-title { font-size: 15px; font-weight: 700; color: var(--indigo-deep); margin-bottom: 4px; }
.sidebar-tip { font-size: 12px; color: #9aa6b8; margin-bottom: 10px; }
.theme-list { list-style: none; }
.theme-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 10px;
  font-size: 14px;
  cursor: pointer;
  color: var(--ink);
  transition: background 0.15s;
  margin-bottom: 2px;
}
.theme-item:hover { background: #eef3fb; }
.theme-item.on {
  background: var(--indigo);
  color: #fff;
  font-weight: 600;
}
.theme-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--accent);
  flex-shrink: 0;
}
.theme-item.on .theme-dot { background: #ffd27d; }
</style>
