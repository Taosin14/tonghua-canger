<script setup>
import { computed } from 'vue'
import { useThemeStore } from '../../stores/theme'
import { useRouter } from 'vue-router'
import MouseFollowGrid from '../effects/MouseFollowGrid.vue'
import { imgSrc } from '../../utils/maps'

// 右侧预览面板:有作品显示缩略信息与入口,无作品显示空状态
const themeStore = useThemeStore()
const router = useRouter()
const story = computed(() => themeStore.currentStory)

function open() {
  if (story.value?.id) router.push(`/stories/${story.value.id}`)
}
</script>

<template>
  <div v-if="story" class="preview">
    <div class="preview-cover" :class="{ 'no-img': !story.cover_illus }" @click="open">
      <img v-if="story.cover_illus" :src="imgSrc(story.cover_illus)" :alt="story.title" />
      <span v-else class="cover-letter">{{ (story.title || '童')[0] }}</span>
    </div>
    <div class="preview-title">{{ story.title }}</div>
    <div class="preview-meta">
      <span class="badge">{{ story.page_count }} 页</span>
      <span class="badge">{{ story.age_band }} 岁</span>
      <span class="badge">{{ story.style }}</span>
      <span v-if="story.status" class="badge">{{ { draft: '草稿', pending: '待审核', published: '已发布', rejected: '已驳回' }[story.status] }}</span>
    </div>
    <p class="preview-tip">点击封面进入阅读器(翻页 + 中文朗读)</p>
  </div>
  <div v-else class="empty-state">
    <MouseFollowGrid />
    <div class="empty-icon">🌸</div>
    <div class="empty-title">还没有作品</div>
    <div class="empty-sub">左侧选主题,中部配参数,点「✨ 一键生成」</div>
  </div>
</template>

<style scoped>
.preview { text-align: center; padding-top: 8px; }
.preview-cover {
  width: min(240px, 78%);
  aspect-ratio: 4 / 3;
  margin: 0 auto 12px;
  border-radius: 14px;
  overflow: hidden;
  cursor: pointer;
  background: linear-gradient(160deg, #eaf1fb, #d7e3f7);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 10px 24px rgba(29, 53, 87, 0.2);
  transition: transform 0.2s;
}
.preview-cover:hover { transform: translateY(-3px); }
.preview-cover img { width: 100%; height: 100%; object-fit: cover; }
.cover-letter {
  font-size: 64px;
  font-weight: 800;
  color: var(--indigo);
  text-shadow: 2px 2px 0 #fff;
}
.preview-title { font-size: 16px; font-weight: 700; color: var(--indigo-deep); margin-bottom: 8px; }
.preview-meta { display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; }
.preview-tip { font-size: 12px; color: #9aa6b8; margin-top: 10px; }
</style>
