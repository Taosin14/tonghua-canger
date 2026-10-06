<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

// 五领域覆盖条图:移植自 GG(1) skills_horizontal_bar_graph(宽度动画 + 滚动触发)
const props = defineProps({
  summary: { type: Array, default: () => [] }, // [{domain,count}]
})

const MAX = 5
const bars = computed(() =>
  props.summary.map((s) => ({
    domain: s.domain,
    count: s.count,
    pct: Math.min(100, Math.round((s.count / MAX) * 100)),
  }))
)

const visible = ref(false)
const rootEl = ref(null)
let io = null

onMounted(() => {
  io = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) {
        visible.value = true
        io?.disconnect()
      }
    },
    { threshold: 0.3 }
  )
  if (rootEl.value) io.observe(rootEl.value)
})
onBeforeUnmount(() => io?.disconnect())

const ICONS = { 健康: '🏃', 语言: '💬', 社会: '🌿', 科学: '🔬', 艺术: '🎨', 延伸: '💡' }
</script>

<template>
  <div ref="rootEl" class="goals-chart card">
    <div class="chart-title">📊 五领域教育目标覆盖</div>
    <div v-for="b in bars" :key="b.domain" class="bar-row">
      <span class="bar-label">{{ ICONS[b.domain] || '⭐' }} {{ b.domain }}</span>
      <div class="bar-track">
        <div class="bar-fill" :style="{ width: visible ? b.pct + '%' : '0%' }"></div>
      </div>
      <span class="bar-count">{{ b.count }} 页</span>
    </div>
  </div>
</template>

<style scoped>
.goals-chart { max-width: 560px; margin: 16px auto 0; }
.chart-title { font-size: 14px; font-weight: 700; color: var(--indigo-deep); margin-bottom: 12px; }
.bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.bar-label { width: 74px; font-size: 13px; color: var(--indigo-soft); flex-shrink: 0; }
.bar-track { flex: 1; height: 14px; background: #eef3fb; border-radius: 8px; overflow: hidden; }
.bar-fill {
  height: 100%;
  border-radius: 8px;
  background: linear-gradient(90deg, var(--indigo-soft), var(--indigo));
  transition: width 1.2s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.bar-count { width: 44px; font-size: 12px; color: #8a97ab; text-align: right; }
</style>
