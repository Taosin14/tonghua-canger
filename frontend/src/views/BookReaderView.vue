<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import { toast, toastErr, toastOk } from '../composables/useToast'
import { useAuthStore } from '../stores/auth'
import { useExport } from '../composables/useExport'
import BookReader from '../components/book/BookReader.vue'
import EduGoalsChart from '../components/effects/EduGoalsChart.vue'
import { STATUS_LABELS, STATUS_COLORS } from '../utils/maps'

// 全屏阅读器页:故事 + 顶部操作(编辑/提交审核/导出)
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { exportPdf, exportPng } = useExport()

const story = ref(null)
const loading = ref(true)
const bookShell = ref(null)

onMounted(load)
onBeforeUnmount(() => { if ('speechSynthesis' in window) speechSynthesis.cancel() })

async function load() {
  loading.value = true
  try {
    story.value = await api.story(route.params.id)
  } catch (e) {
    toastErr('加载失败:' + e.message)
  } finally {
    loading.value = false
  }
}

async function submitReview() {
  try {
    await api.submitStory(story.value.id)
    toastOk('已提交审核,等待管理员通过后进入图书馆')
    story.value.status = 'pending'
  } catch (e) {
    toastErr(e.message)
  }
}

async function exportBook(format) {
  if (!bookShell.value) return
  try {
    if (format === 'pdf') await exportPdf(bookShell.value, story.value.title)
    else await exportPng(bookShell.value, story.value.title)
    toastOk('导出成功')
  } catch (e) {
    toastErr('导出失败:' + e.message)
  }
}
</script>

<template>
  <div class="reader-view">
    <div class="reader-toolbar">
      <button class="btn ghost sm" @click="router.back()">← 返回</button>
      <span class="toolbar-title">{{ story?.title || '加载中…' }}</span>
      <span
        v-if="story"
        class="badge"
        :style="{ background: STATUS_COLORS[story.status] + '22', color: STATUS_COLORS[story.status] }"
      >{{ STATUS_LABELS[story.status] }}</span>
      <span class="toolbar-spacer"></span>
      <template v-if="story">
        <button v-if="story.status === 'draft' || story.status === 'rejected'" class="btn sm" @click="submitReview">📤 提交审核</button>
        <button
          v-if="auth.hasRole('teacher', 'reviewer', 'admin')"
          class="btn ghost sm"
          @click="router.push(`/stories/${story.id}/edit`)"
        >✏️ 编辑</button>
        <button class="btn ghost sm" @click="exportBook('png')">🖼️ PNG</button>
        <button class="btn ghost sm" @click="exportBook('pdf')">📄 PDF</button>
      </template>
    </div>

    <div v-if="loading" class="card loading-card">⏳ 加载中…</div>
    <div v-else-if="story" ref="bookShell">
      <BookReader :story="story" />
      <EduGoalsChart v-if="story.edu_goals_summary?.length" :summary="story.edu_goals_summary" />
    </div>
    <div v-else class="card empty-state">
      <div class="empty-icon">😢</div>
      <div class="empty-title">故事不存在或加载失败</div>
    </div>
  </div>
</template>

<style scoped>
.reader-view { max-width: 1020px; margin: 0 auto; }
.reader-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.toolbar-title { font-size: 15px; font-weight: 700; color: var(--indigo-deep); }
.toolbar-spacer { flex: 1; }
.loading-card { text-align: center; padding: 60px; color: var(--indigo-soft); }
</style>
