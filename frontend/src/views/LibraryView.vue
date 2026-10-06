<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../api'
import { toastErr } from '../composables/useToast'
import { AGE_LABELS, domainIcon, THEMES, imgSrc } from '../utils/maps'

// 文化库:📖绘本 / 🧩素材 / 🏛️符号 三个页签
const tab = ref('stories')

const stories = ref([])
const materials = ref([])
const symbols = ref([])
const loading = ref(false)

const storyTheme = ref('')
const storyAge = ref('')
const storyQ = ref('')
const materialQ = ref('')
const materialType = ref('')
const symbolQ = ref('')
const symbolCat = ref('')

onMounted(() => {
  loadStories()
  loadMaterials()
  loadSymbols()
})

async function loadStories() {
  loading.value = true
  try {
    const data = await api.stories({
      status: 'published',
      theme: storyTheme.value || undefined,
      age: storyAge.value || undefined,
      q: storyQ.value || undefined,
      page_size: 50,
    })
    stories.value = data.list
  } catch (e) {
    toastErr(e.message)
  } finally {
    loading.value = false
  }
}

async function loadMaterials() {
  try {
    const data = await api.materials({ q: materialQ.value || undefined, type: materialType.value || undefined, page_size: 100 })
    materials.value = data.list
  } catch (e) {
    toastErr(e.message)
  }
}

async function loadSymbols() {
  try {
    const data = await api.symbols({ q: symbolQ.value || undefined, category: symbolCat.value || undefined, page_size: 100 })
    symbols.value = data.list
  } catch (e) {
    toastErr(e.message)
  }
}
</script>

<template>
  <div class="library-view">
    <nav class="lib-tabs card">
      <button class="ltab" :class="{ active: tab === 'stories' }" @click="tab = 'stories'">📖 绘本图书馆</button>
      <button class="ltab" :class="{ active: tab === 'materials' }" @click="tab = 'materials'">🧩 素材库</button>
      <button class="ltab" :class="{ active: tab === 'symbols' }" @click="tab = 'symbols'">🏛️ 文化符号库</button>
    </nav>

    <!-- 绘本 -->
    <div v-if="tab === 'stories'">
      <div class="filter-bar card">
        <select v-model="storyTheme" @change="loadStories">
          <option value="">全部主题</option>
          <option v-for="t in THEMES" :key="t" :value="t">{{ t }}</option>
        </select>
        <select v-model="storyAge" @change="loadStories">
          <option value="">全部年龄段</option>
          <option value="3-4">小班(3-4岁)</option>
          <option value="4-5">中班(4-5岁)</option>
          <option value="5-6">大班(5-6岁)</option>
        </select>
        <input v-model="storyQ" placeholder="搜索书名…" @keyup.enter="loadStories" />
        <button class="btn sm" @click="loadStories">搜索</button>
      </div>
      <div v-if="loading" class="card loading-card">⏳ 加载中…</div>
      <div v-else-if="stories.length === 0" class="card empty-state">
        <div class="empty-icon">📭</div>
        <div class="empty-title">图书馆还没有绘本</div>
        <div class="empty-sub">去工作台生成一本,审核通过后就会出现在这里</div>
      </div>
      <div v-else class="story-grid">
        <router-link v-for="s in stories" :key="s.id" :to="`/stories/${s.id}`" class="story-card card">
          <div class="s-cover">
            <img v-if="s.cover_illus" :src="imgSrc(s.cover_illus)" :alt="s.title" />
            <span v-else class="s-letter">{{ (s.title || '童')[0] }}</span>
          </div>
          <div class="s-title">{{ s.title }}</div>
          <div class="s-meta">
            <span class="badge">{{ s.theme || '白族文化' }}</span>
            <span class="badge">{{ AGE_LABELS[s.age_band] || s.age_band }}</span>
            <span class="badge">{{ s.page_count }}页</span>
          </div>
        </router-link>
      </div>
    </div>

    <!-- 素材 -->
    <div v-else-if="tab === 'materials'">
      <div class="filter-bar card">
        <select v-model="materialType" @change="loadMaterials">
          <option value="">全部类型</option>
          <option v-for="t in ['民间故事', '非遗工艺', '建筑场景', '人物角色', '节日民俗', '童谣儿歌']" :key="t" :value="t">{{ t }}</option>
        </select>
        <input v-model="materialQ" placeholder="搜索素材…" @keyup.enter="loadMaterials" />
        <button class="btn sm" @click="loadMaterials">搜索</button>
        <span class="count-tip">共 {{ materials.length }} 条人工审核素材(故事生成的"事实底座")</span>
      </div>
      <div class="material-grid">
        <div v-for="m in materials" :key="m.id" class="material-card card">
          <div class="m-head">
            <span class="badge">{{ m.material_type }}</span>
            <span class="badge">{{ AGE_LABELS[m.age_band] || m.age_band }}</span>
          </div>
          <div class="m-name">{{ m.name }}</div>
          <p class="m-desc">{{ m.description }}</p>
          <div class="m-goals">
            <span v-for="g in m.edu_goals" :key="g.domain + g.goal" class="m-goal">
              {{ domainIcon(g.domain) }} {{ g.domain }}:{{ g.goal }}
            </span>
          </div>
          <div class="m-tags"><span v-for="t in (m.tags || [])" :key="t" class="tag">{{ t }}</span></div>
        </div>
      </div>
    </div>

    <!-- 符号 -->
    <div v-else>
      <div class="filter-bar card">
        <select v-model="symbolCat" @change="loadSymbols">
          <option value="">全部类别</option>
          <option v-for="c in ['建筑', '服饰', '饮食', '工艺', '节日', '人物', '自然', '音乐', '其他']" :key="c" :value="c">{{ c }}</option>
        </select>
        <input v-model="symbolQ" placeholder="搜索符号…" @keyup.enter="loadSymbols" />
        <button class="btn sm" @click="loadSymbols">搜索</button>
        <span class="count-tip">共 {{ symbols.length }} 个文化符号(带文化溯源)</span>
      </div>
      <div v-if="symbols.length === 0" class="card empty-state">
        <div class="empty-icon">🏛️</div>
        <div class="empty-title">符号库建设中</div>
        <div class="empty-sub">文化核校组正在录入 50 个白族文化符号及溯源资料</div>
      </div>
      <div v-else class="symbol-grid">
        <div v-for="s in symbols" :key="s.id" class="symbol-card card">
          <div class="s-head">
            <span class="badge">{{ s.category }}</span>
            <span class="s-name">{{ s.name }}</span>
          </div>
          <p class="s-desc">{{ s.description }}</p>
          <div v-if="s.symbol_trace" class="s-trace">
            <span class="trace-label">📍 文化溯源</span>
            {{ s.symbol_trace }}
          </div>
          <div v-if="s.source" class="s-source">来源:{{ s.source }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.library-view { max-width: 1200px; margin: 0 auto; }
.lib-tabs { display: flex; gap: 4px; margin-bottom: 14px; }
.ltab {
  border: none;
  background: none;
  padding: 10px 18px;
  font-size: 14px;
  cursor: pointer;
  color: var(--indigo-soft);
  border-radius: 10px;
}
.ltab.active { background: var(--indigo); color: #fff; font-weight: 700; }
.filter-bar { display: flex; gap: 10px; align-items: center; margin-bottom: 14px; flex-wrap: wrap; }
.filter-bar select, .filter-bar input {
  padding: 8px 12px;
  border: 1.5px solid #c9d3e3;
  border-radius: 10px;
  font-size: 13px;
  font-family: inherit;
  background: #fff;
}
.filter-bar input { width: 220px; }
.count-tip { font-size: 12px; color: #9aa6b8; margin-left: auto; }
.loading-card { text-align: center; padding: 50px; color: var(--indigo-soft); }
.story-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
.story-card { display: block; transition: transform 0.2s, box-shadow 0.2s; }
.story-card:hover { transform: translateY(-4px); box-shadow: 0 14px 30px rgba(29, 53, 87, 0.2); }
.s-cover {
  aspect-ratio: 4 / 3;
  border-radius: 10px;
  overflow: hidden;
  background: linear-gradient(160deg, #eaf1fb, #d7e3f7);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 10px;
}
.s-cover img { width: 100%; height: 100%; object-fit: cover; }
.s-letter { font-size: 44px; font-weight: 800; color: var(--indigo); text-shadow: 2px 2px 0 #fff; }
.s-title { font-size: 14.5px; font-weight: 700; color: var(--indigo-deep); margin-bottom: 8px; }
.s-meta { display: flex; gap: 6px; flex-wrap: wrap; }
.material-grid, .symbol-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
.material-card .m-head, .symbol-card .s-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.m-name, .s-name { font-size: 15px; font-weight: 700; color: var(--indigo-deep); }
.m-desc, .s-desc { font-size: 13px; color: var(--ink); line-height: 1.8; margin-bottom: 8px; }
.m-goals { display: flex; flex-direction: column; gap: 3px; margin-bottom: 8px; }
.m-goal { font-size: 12px; color: var(--indigo-soft); }
.s-trace {
  font-size: 12.5px;
  background: #f0f5fc;
  border-left: 3px solid var(--accent);
  padding: 8px 10px;
  border-radius: 8px;
  line-height: 1.7;
  color: var(--indigo);
  margin-bottom: 6px;
}
.trace-label { font-weight: 700; margin-right: 4px; }
.s-source { font-size: 12px; color: #9aa6b8; }
</style>
