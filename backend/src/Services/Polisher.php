<?php
namespace App\Services;

/**
 * 提示词构建 —— 生成/润色分开,全部强调"只基于素材卡、不得虚构文化事实"
 */
class Polisher
{
    /** @return array{0:string,1:string} [system, user] */
    public static function promptsForGenerate(array $material, string $age, int $pageCount, string $style, string $symbolTrace = ''): array
    {
        $ageRule = match ($age) {
            '3-4' => '每页1-2句,每句不超过12个字',
            '5-6' => '每页2-4句',
            default => '每页2-3句',
        };
        $system = <<<TXT
你是"童画苍洱"幼儿绘本文字编辑,精通大理白族文化,面向大理幼儿园教师生成绘本文本。
写作规则:
1. 面向{$age}岁幼儿:{$ageRule}。用词口语化、有节奏感,多用象声词("咕嘟咕嘟""哗啦哗啦")。
2. 只基于给定的素材卡撰写,不得虚构文化事实:工艺步骤、节日日期、服饰称谓必须与素材卡一致。
3. 每页配一个教育目标,领域只能从《3-6岁儿童学习与发展指南》五领域选:健康/语言/社会/科学/艺术,目标描述具体可观察。
4. 全文重点词不超过8处,用 **词** 标记(每页最多2处)。
5. 故事有起承转合:封面后从场景/人物开始,中间有小转折或小悬念,结尾有互动提问。
6. 每页内容沿主线推进,不要罗列或堆砌文化名词。
7. "关联文化符号溯源"仅供你了解背景知识,不得直接写进故事;与素材主线无关的请忽略。
8. 同时输出角色设定卡 characters,格式:[{"name":"角色名","role":"主角/配角","appearance":"年龄、性别、发型发色、眼睛颜色、皮肤、服饰"}],硬性要求:中国白族儿童统一"黑色头发、黑色眼睛、黄色皮肤",服饰写白族传统服饰,人类角色的 appearance 末尾写明"是人类"。
9. 只输出 JSON,格式:{"pages":[{"page_no":1,"type":"cover","text":""},{"page_no":2,"type":"content","text":"...","goal_text":"...","goal_domains":["艺术"]}],"characters":[...]}
TXT;
        $goalsText = implode(';', array_map(
            fn($g) => ($g['domain'] ?? '') . ':' . ($g['goal'] ?? ''),
            $material['edu_goals'] ?? []
        ));
        $trace = $symbolTrace !== '' ? "关联文化符号溯源(仅背景参考,不要直接写进故事):\n{$symbolTrace}" : '';
        $user = <<<TXT
请为以下素材创作一本{$pageCount}页的{$style}风绘本:
素材名称:{$material['name']}
文化描述:{$material['description']}
教育目标:{$goalsText}
画面风格提示:{$material['image_prompt']}
{$trace}
页数要求:共{$pageCount}页,第1页为封面(text为空),最后1页为结尾互动页。

风格参考样例(内容请围绕新素材重新创作,不要照抄):
第2页文本:"清晨,白族的小院真安静。奶奶搬出一块**白白软软**的布,笑着说:今天,我们做蓝白花布吧!" 目标:"感受祖孙相处的温暖" 领域:["社会"]
第4页文本:"奶奶教我把白布捏成**小揪揪**,用皮筋扎得紧紧的。这里扎一下,那里绑一下,像在包一个个小糖果。" 目标:"动手体验扎的动作与节奏" 领域:["艺术"]
TXT;
        return [$system, $user];
    }

    /** @return array{0:string,1:string} 润色/扩写/目标/标题 */
    public static function promptForPolish(string $mode, string $age, string $text, string $theme = ''): array
    {
        $system = match ($mode) {
            'expand' => "你是幼儿绘本文字编辑。把以下绘本页文本扩写为2页(面向{$age}岁),每页2-3句,情节连贯,不新增文化事实,重点词用**词**标记(每页最多1处)。只输出 JSON:{\"pages\":[{\"text\":\"...\"},{\"text\":\"...\"}]}",
            'goal'   => "你是幼儿教育专家。为以下绘本页文本设计教育目标(面向{$age}岁):从五领域(健康/语言/社会/科学/艺术)选1-2个,目标具体可观察。只输出 JSON:{\"goal_text\":\"...\",\"goal_domains\":[\"社会\"]}",
            'title'  => '你是绘本编辑。为以下绘本起标题:主标题6-10字、副标题4-8字,大理白族风格、朗朗上口。只输出 JSON:{"title":"...","subtitle":"..."}',
            default  => "你是幼儿绘本文字编辑。润色以下绘本页文本(面向{$age}岁):只改语言表达,让句子更口语、更有节奏、更生动;不得新增事实、不得改变情节;重点词保留或添加不超过2处,用**词**标记。只输出 JSON:{\"text\":\"...\"}",
        };
        $ctx = $theme !== '' ? "所属主题:{$theme}\n" : '';
        $user = "{$ctx}原文:\n{$text}";
        return [$system, $user];
    }
}
