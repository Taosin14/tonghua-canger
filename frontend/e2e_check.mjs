// 端到端交互测试:模拟教师真实操作
// 1. 首页:选主题 -> 一键生成 -> 出现"已生成"
// 2. 点"去阅读" -> 阅读器 -> 翻页 -> 朗读按钮存在
// 3. 文化库 -> 打开《奶奶的蓝白花布》-> 翻页
import { chromium } from 'playwright-core'

const browser = await chromium.launch({ channel: 'msedge', headless: true })
const page = await browser.newPage()
const errors = []
page.on('pageerror', (e) => errors.push(e.message.slice(0, 200)))

async function step(name, fn) {
  try {
    await fn()
    console.log(`✅ ${name}`)
  } catch (e) {
    console.log(`❌ ${name}: ${e.message.split('\n')[0].slice(0, 200)}`)
    errors.push(name + ': ' + e.message.slice(0, 200))
  }
}

await page.goto('http://localhost:5173/', { waitUntil: 'networkidle', timeout: 20000 })

await step('首页渲染出主题导航', async () => {
  await page.waitForSelector('text=主题导航', { timeout: 8000 })
})

await step('选主题"火把节"', async () => {
  await page.click('text=火把节 >> nth=0')
})

await step('一键生成出现结果卡片', async () => {
  await page.click('button:has-text("一键生成")')
  await page.waitForSelector('text=已生成', { timeout: 30000 })
})

await step('点击"去阅读"进入阅读器', async () => {
  await page.click('button:has-text("去阅读")')
  await page.waitForSelector('button:has-text("上一页")', { timeout: 10000 })
})

await step('翻到下一页(计数 2)', async () => {
  await page.click('button:has-text("下一页")')
  await page.waitForSelector('text=2 / 8', { timeout: 5000 })
})

await step('键盘右箭头翻页(计数 3)', async () => {
  await page.keyboard.press('ArrowRight')
  await page.waitForSelector('text=3 / 8', { timeout: 5000 })
})

await step('朗读按钮存在', async () => {
  await page.waitForSelector('button:has-text("听一听")', { timeout: 5000 })
})

await page.goto('http://localhost:5173/library', { waitUntil: 'networkidle' })

await step('文化库显示《奶奶的蓝白花布》', async () => {
  await page.waitForSelector('text=奶奶的蓝白花布', { timeout: 8000 })
})

await step('打开种子故事并翻页', async () => {
  await page.click('text=奶奶的蓝白花布')
  await page.waitForSelector('button:has-text("下一页")', { timeout: 8000 })
  await page.click('button:has-text("下一页")')
  await page.waitForSelector('text=2 / 9', { timeout: 5000 })
})

console.log('\n未捕获异常: ' + (errors.length ? errors.length + ' 条' : '无'))
await browser.close()
