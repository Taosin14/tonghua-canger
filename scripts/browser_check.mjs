// 无头浏览器冒烟检查:用系统 Edge 打开页面,抓取 console 错误与页面渲染结果
// 用法: cd frontend && node ../scripts/browser_check.mjs
import { chromium } from 'playwright-core'

const errors = []
const browser = await chromium.launch({ channel: 'msedge', headless: true })

for (const url of ['http://localhost:5173/', 'http://localhost:5173/library', 'http://localhost:5173/stories/1']) {
  const page = await browser.newPage()
  const pageErrors = []
  page.on('console', (msg) => {
    if (msg.type() === 'error') pageErrors.push(msg.text().slice(0, 300))
  })
  page.on('pageerror', (err) => pageErrors.push('PAGEERROR: ' + err.message.slice(0, 300)))
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 })
    await page.waitForTimeout(2500)
  } catch (e) {
    pageErrors.push('GOTO: ' + e.message.slice(0, 200))
  }
  const appHtmlLen = await page.evaluate(() => document.getElementById('app')?.innerHTML.length ?? 0)
  const title = await page.title()
  console.log(`\n===== ${url}`)
  console.log(`app 内容长度: ${appHtmlLen} | 标题: ${title}`)
  if (pageErrors.length) {
    console.log('错误 ' + pageErrors.length + ' 条:')
    pageErrors.forEach((e, i) => console.log(`  [${i + 1}] ${e}`))
  } else {
    console.log('无 console 错误')
  }
  await page.close()
}

await browser.close()
console.log('\n检查完成')
