// 五领域 -> 图标(与《3-6岁儿童学习与发展指南》对应)
export const DOMAIN_ICONS = {
  健康: '🏃', 语言: '💬', 社会: '🌿', 科学: '🔬', 艺术: '🎨', 延伸: '💡', 环保: '🌍',
}
export const domainIcon = (d) => DOMAIN_ICONS[d] || '⭐'

export const AGE_LABELS = { '3-4': '小班(3-4岁)', '4-5': '中班(4-5岁)', '5-6': '大班(5-6岁)' }
export const AGE_OPTIONS = [
  { value: '3-4', label: '小班(3-4岁)' },
  { value: '4-5', label: '中班(4-5岁)' },
  { value: '5-6', label: '大班(5-6岁)' },
]

export const STATUS_LABELS = { draft: '草稿', pending: '待审核', published: '已发布', rejected: '已驳回' }
export const STATUS_COLORS = { draft: '#8a97ab', pending: '#e8a33d', published: '#2b8a5c', rejected: '#c0504d' }

export const STYLE_OPTIONS = ['水彩', '彩铅', '扁平插画', '水墨']

// 生成主题(与素材库 6 大类对应,可随素材扩充)
export const THEMES = ['扎染', '三道茶', '洱海', '苍山', '火把节', '三月街', '甲马', '瓦猫', '蝴蝶泉', '三塔', '照壁', '乳扇', '霸王鞭', '金花阿鹏']

export const MATERIAL_TYPES = ['民间故事', '非遗工艺', '建筑场景', '人物角色', '节日民俗', '童谣儿歌']
export const SYMBOL_CATEGORIES = ['建筑', '服饰', '饮食', '工艺', '节日', '人物', '自然', '音乐', '其他']

/** 数据库存的相对路径(images/...)在嵌套路由下会解析错,统一补站点根前缀 */
export function imgSrc(p) {
  if (!p) return ''
  if (/^(https?:)?\/\//.test(p) || p.startsWith('/')) return p
  return '/' + p
}
