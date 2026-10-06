<?php
namespace App\Services;

/**
 * 纯模板拼装器 —— 数据库优先理念的兜底实现:
 * 无 GLM Key / 断网时,仅凭素材卡也能产出完整的绘本页结构(零成本)。
 */
class StoryAssembler
{
    /** @param array $material 素材行(edu_goals 已解码) */
    public static function assemble(array $material, int $pageCount, string $style): array
    {
        $name = $material['name'];
        $desc = trim((string)($material['description'] ?? ''));
        $goals = $material['edu_goals'] ?? [];
        $prompt = (string)($material['image_prompt'] ?? '');

        $sentences = preg_split('/[。！？!?]/u', $desc, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_values(array_filter(array_map('trim', $sentences), fn($s) => $s !== ''));
        if (!$sentences) {
            $sentences = [$desc];
        }

        $contentCount = max(1, $pageCount - 2); // 第1页封面 + 最后1页结尾
        $per = max(1, (int)ceil(count($sentences) / $contentCount));
        $filler = [
            '小朋友们,我们一起想一想:{goal}',
            '你发现了吗?{goal}',
            '仔细观察,{goal}',
        ];

        $pages = [];
        // 封面
        $pages[] = [
            'page_no' => 1, 'type' => 'cover', 'text' => '',
            'goal_text' => '', 'goal_domains' => [],
            'illus' => '', 'illus_prompt' => $prompt,
        ];
        // 内容页
        for ($i = 1; $i <= $contentCount; $i++) {
            $chunk = array_slice($sentences, ($i - 1) * $per, $per);
            $text = $chunk ? implode('。', $chunk) . '。' : '';
            [$goalText, $goalDomains] = self::goalFor($goals, $i - 1);
            if ($text === '') {
                $text = str_replace('{goal}', $goalText !== '' ? $goalText : '故事里的美好细节', $filler[($i - 1) % count($filler)]);
            } else {
                $text = self::applyHighlight($text, $name);
            }
            $pages[] = [
                'page_no' => $i + 1, 'type' => 'content',
                'text' => $text,
                'goal_text' => $goalText, 'goal_domains' => $goalDomains,
                'illus' => '', 'illus_prompt' => $prompt,
            ];
        }
        // 结尾互动页
        $short = str_replace('白族', '', $name);
        $pages[] = [
            'page_no' => $pageCount, 'type' => 'ending',
            'text' => "小朋友,关于{$short}的故事就讲到这里啦。你最喜欢哪个画面?和身边的小伙伴说一说,也可以动手试一试哦!",
            'goal_text' => '喜欢听故事,愿意表达自己的想法', 'goal_domains' => ['语言'],
            'illus' => '', 'illus_prompt' => $prompt,
        ];
        return $pages;
    }

    /** 从五领域目标中轮转取一条 */
    private static function goalFor(array $goals, int $i): array
    {
        if (!$goals) {
            return ['', []];
        }
        $g = $goals[$i % count($goals)];
        return [(string)($g['goal'] ?? ''), (array)($g['domain'] ?? [])];
    }

    /** 首现关键词加 ** 高亮 */
    private static function applyHighlight(string $text, string $name): string
    {
        foreach ([str_replace('白族', '', $name), $name] as $w) {
            if ($w === '' || mb_strpos($text, $w) === false) {
                continue;
            }
            $pos = mb_strpos($text, $w);
            return mb_substr($text, 0, $pos) . '**' . $w . '**' . mb_substr($text, $pos + mb_strlen($w));
        }
        return $text;
    }
}
