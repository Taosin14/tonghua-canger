import { chromium } from 'playwright-core'
const browser = await chromium.launch({ channel: 'msedge', headless: true })
const page = await browser.newPage()
page.on('pageerror', (e) => console.log('PAGEERROR:', (e.stack || e.message).slice(0, 400)))
page.on('console', (m) => { if (m.type() === 'error') console.log('CONSOLE:', m.text().slice(0, 250)) })
const steps = [
  ['首页', '/'],
  ['图书馆', '/library'],
  ['阅读器', '/stories/1'],
]
for (const [name, p] of steps) {
  await page.goto('http://localhost:5173' + p, { waitUntil: 'networkidle', timeout: 30000 })
  await page.waitForTimeout(1200)
  console.log('visited', name)
}
// 复现 E2E 的生成步骤
await page.goto('http://localhost:5173/', { waitUntil: 'networkidle' })
await page.click('text=火把节 >> nth=0').catch(() => console.log('主题点击失败'))
await page.click('button:has-text("一键生成")').catch(() => console.log('生成点击失败'))
console.log('等待生成结果(最多 100 秒)…')
try {
  await page.waitForSelector('text=已生成', { timeout: 100000 })
  console.log('生成成功')
} catch {
  console.log('生成未在 100 秒内出现结果')
}
await page.waitForTimeout(1000)
await browser.close()
console.log('trace done')
