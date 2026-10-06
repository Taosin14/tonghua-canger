<script setup>
import { toastErr } from '../../composables/useToast'
import { useRouter } from 'vue-router'

// 导出面板:引导到阅读器页完成导出
const props = defineProps({
  story: { type: Object, default: null },
})
const router = useRouter()

function goReader() {
  if (!props.story?.id) {
    toastErr('请先生成或打开一本绘本')
    return
  }
  router.push(`/stories/${props.story.id}`)
}
</script>

<template>
  <div class="export-bar">
    <p class="export-tip">
      导出操作在<b>阅读器页</b>完成:打开绘本后点顶部「🖼️ PNG」导出当前页、「📄 PDF」导出当前页。
    </p>
    <button class="btn sm" :disabled="!story" @click="goReader">📦 前往阅读器导出</button>
    <div v-if="!story" class="empty-state">
      <div class="empty-icon">📦</div>
      <div class="empty-title">暂无作品可导出</div>
      <div class="empty-sub">先生成一本绘本,或在文化库打开一本</div>
    </div>
  </div>
</template>

<style scoped>
.export-bar { padding-top: 6px; }
.export-tip { font-size: 13px; color: var(--indigo-soft); line-height: 1.8; margin-bottom: 12px; }
</style>
