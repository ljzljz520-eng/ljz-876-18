<?php

namespace App\Support;

use App\Models\PracticeAttempt;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * 课后练习推荐服务。
 *
 * 铁律：推荐题只可能来自
 *   1) 该学生在「真实正式考试」中做错的题（wrong_question）；
 *   2) 与这些错题「同知识点（分类）、同题型、难度相近」的相似题（similar_question）。
 * 不存在任何「热门题」「随机题」逻辑。
 *
 * 推荐与作答都只读写 practice_attempts，绝不触碰 exam_records，因此不计入正式成绩。
 */
class PracticeRecommendationService
{
    public const MAX_RECOMMEND = 10;
    public const MAX_PER_WRONG_SIMILAR = 2;

    /**
     * @param int   $userId
     * @param array $wrongQuestions 来自 WeaknessProfileService::build 的 wrong_questions
     */
    public function recommend(int $userId, array $wrongQuestions): array
    {
        $wrongIds = array_column($wrongQuestions, 'question_id');
        if (empty($wrongIds)) {
            return [];
        }

        // 该学生在练习中已答对掌握的题 -> 不再推荐
        $masteredIds = PracticeAttempt::where('user_id', $userId)
            ->where('is_correct', 1)
            ->pluck('question_id')
            ->all();

        // 最近练习过（无论对错）的题降权，避免短期内重复刷同一道
        $recentIds = PracticeAttempt::where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('question_id')
            ->all();
        $recentRank = [];
        foreach (array_values($recentIds) as $i => $id) {
            $recentRank[$id] = $i;
        }

        $recommendations = [];

        // ---------- 第一部分：真实错题（仍未掌握） ----------
        $wrongQuestionModels = Question::whereIn('id', $wrongIds)
            ->where('status', 1)
            ->get()
            ->keyBy('id');

        foreach ($wrongIds as $qid) {
            $question = $wrongQuestionModels->get($qid);
            if (!$question) {
                continue;
            }
            if (in_array($qid, $masteredIds, true)) {
                continue; // 已通过练习掌握
            }
            if (!AnswerChecker::isAutoGradable($question)) {
                continue; // 主观题无法在线自动判分，不进入练习
            }
            $recommendations[] = $this->format($question, PracticeAttempt::SOURCE_WRONG, null, $recentRank[$qid] ?? null);
        }

        // ---------- 第二部分：相近题 ----------
        $excludeIds = array_unique(array_merge($wrongIds, $masteredIds, array_column($recommendations, 'question_id')));

        foreach ($wrongQuestions as $wrong) {
            $seedQuestion = $wrongQuestionModels->get($wrong['question_id']);
            if (!$seedQuestion || !AnswerChecker::isAutoGradable($seedQuestion)) {
                continue;
            }

            // 同知识点 + 同题型 + 难度相邻（±1），且是启用的客观题
            $similar = Question::where('category_id', $seedQuestion->category_id)
                ->where('type', $seedQuestion->type)
                ->where('status', 1)
                ->whereIn('difficulty', [$seedQuestion->difficulty - 1, $seedQuestion->difficulty, $seedQuestion->difficulty + 1])
                ->whereNotIn('id', $excludeIds)
                ->orderByRaw('ABS(difficulty - ?)', [$seedQuestion->difficulty])
                ->orderBy('id')
                ->limit(self::MAX_PER_WRONG_SIMILAR)
                ->get();

            foreach ($similar as $q) {
                if (count($recommendations) >= self::MAX_RECOMMEND) {
                    break 2;
                }
                $excludeIds[] = $q->id;
                $recommendations[] = $this->format(
                    $q,
                    PracticeAttempt::SOURCE_SIMILAR,
                    $seedQuestion->id,
                    $recentRank[$q->id] ?? null
                );
            }
        }

        // 真实错题在前，相近题在后；相近题内部难度越接近越靠前
        usort($recommendations, function ($a, $b) {
            if ($a['source'] !== $b['source']) {
                return $a['source'] === PracticeAttempt::SOURCE_WRONG ? -1 : 1;
            }
            return ($a['recency_rank'] ?? 999) <=> ($b['recency_rank'] ?? 999);
        });

        return array_slice($recommendations, 0, self::MAX_RECOMMEND);
    }

    private function format(Question $q, string $source, ?int $relatedId, ?int $recencyRank): array
    {
        return [
            'question_id' => $q->id,
            'type' => $q->type,
            'difficulty' => (int) $q->difficulty,
            'difficulty_label' => Question::DIFFICULTIES[$q->difficulty] ?? (string) $q->difficulty,
            'title' => $q->title,
            'options' => $q->options,
            'category_id' => (int) $q->category_id,
            'source' => $source,
            'source_label' => PracticeAttempt::SOURCES[$source] ?? $source,
            'related_question_id' => $relatedId,
            'recency_rank' => $recencyRank,
        ];
    }
}
