<?php

namespace App\Services;

use App\Models\PracticeAttempt;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * 练习推荐服务
 *
 * 硬性规则：
 *  - 推荐题只来自两类来源：
 *      1) wrong_questions：学生本人在正式考试中真实答错的题
 *      2) similar：与错题"知识点相同 + 题型相同 + 难度相近"的题
 *  - 绝不使用"热门题/全站正确率/做题人数"等热度信号
 *  - 已答对过的练习、原题本身、停用题、不可自动判分的问答题都会被排除
 *
 * 练习数据写入 practice_attempts，与 exam_records 正式成绩完全隔离。
 */
class RecommendationService
{
    public const WRONG_QUESTION_LIMIT = 10;
    public const SIMILAR_PER_WRONG = 2;
    public const SIMILAR_LIMIT = 10;

    /** 支持自动判分、适合推荐练习的题型 */
    public const PRACTICEABLE_TYPES = [
        Question::TYPE_SINGLE_CHOICE,
        Question::TYPE_MULTIPLE_CHOICE,
        Question::TYPE_TRUE_FALSE,
        Question::TYPE_FILL_BLANK,
    ];

    /**
     * 学生本人在已评分考试中答错的、且尚未在练习中做对的题
     */
    public function wrongQuestions(int $userId): array
    {
        // 真实错题：按题目取最近一次考试结果（同一题可能考多次）；
        // 只纳入支持自动判分、可直接在线练习的题型
        $wrongRows = DB::table('exam_record_answers as era')
            ->join('exam_records as er', 'era.exam_record_id', '=', 'er.id')
            ->join('questions as q', 'era.question_id', '=', 'q.id')
            ->where('er.user_id', $userId)
            ->where('er.status', 'graded')
            ->where('era.is_correct', 0)
            ->where('q.status', 1)
            ->whereIn('q.type', self::PRACTICEABLE_TYPES)
            ->orderBy('er.end_time', 'desc')
            ->select(
                'era.question_id',
                'q.category_id',
                'q.type',
                'q.difficulty',
                'q.title',
                'q.options',
                'q.score',
                'er.id as exam_record_id',
                'er.exam_paper_id',
                'er.end_time',
                'era.answer as student_answer',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY era.question_id ORDER BY er.end_time DESC) as rn')
            )
            ->get();

        $latestWrong = $wrongRows->where('rn', 1);

        // 已经通过练习做对的题不再出现在错题本中
        $masteredIds = PracticeAttempt::where('user_id', $userId)
            ->where('is_correct', 1)
            ->pluck('question_id')
            ->unique()
            ->all();

        $wrong = $latestWrong
            ->reject(fn ($row) => in_array($row->question_id, $masteredIds, true))
            ->take(self::WRONG_QUESTION_LIMIT)
            ->values();

        return [
            'wrong' => $wrong,
            'mastered_ids' => $masteredIds,
        ];
    }

    /**
     * 基于错题找相近题
     * 匹配优先级：同分类 + 同题型 + 难度差最小；难度相同时取ID邻近（题库稳定顺序），
     * 全程不出现任何热度/统计排序。
     */
    public function similarQuestions(int $userId, array $wrongRows, array $masteredIds): \Illuminate\Support\Collection
    {
        if ($wrongRows === [] || empty($wrongRows)) {
            return collect();
        }

        // 排除集合：原题、已经做对的题、以及练习里已经做过的题（避免重复推）
        $excludeIds = collect($wrongRows)->pluck('question_id')->merge($masteredIds);
        $practicedIds = PracticeAttempt::where('user_id', $userId)
            ->pluck('question_id')
            ->unique()
            ->all();
        $excludeIds = $excludeIds->merge($practicedIds)->unique()->values()->all();

        $picked = collect();
        $pickedIds = [];

        // 优先围绕最薄弱知识点（错题列表已按最近考试排序）逐题找相近题
        foreach ($wrongRows as $wrong) {
            if ($picked->count() >= self::SIMILAR_LIMIT) {
                break;
            }

            $candidates = Question::where('status', 1)
                ->where('category_id', $wrong->category_id)
                ->where('type', $wrong->type)
                ->whereIn('type', self::PRACTICEABLE_TYPES)
                ->when(!empty($excludeIds) || !empty($pickedIds), function ($q) use ($excludeIds, $pickedIds) {
                    $q->whereNotIn('id', array_merge($excludeIds, $pickedIds));
                })
                ->get();

            // 难度差最小，其次同难度内取 ID 最接近原题的 —— 纯内容相似，无热度
            $ranked = $candidates
                ->map(function (Question $candidate) use ($wrong) {
                    return [
                        'question' => $candidate,
                        'difficulty_gap' => abs($candidate->difficulty - $wrong->difficulty),
                        'id_distance' => abs($candidate->id - $wrong->question_id),
                    ];
                })
                ->sortBy([
                    ['difficulty_gap', 'asc'],
                    ['id_distance', 'asc'],
                ])
                ->take(self::SIMILAR_PER_WRONG)
                ->values();

            foreach ($ranked as $item) {
                if ($picked->count() >= self::SIMILAR_LIMIT) {
                    break;
                }
                $q = $item['question'];
                $pickedIds[] = $q->id;
                $picked->push([
                    'question' => $q,
                    'based_on_question_id' => $wrong->question_id,
                    'based_on_title' => $wrong->title,
                    'difficulty_gap' => $item['difficulty_gap'],
                ]);
            }
        }

        return $picked;
    }

    /**
     * 输出给前端的推荐结构
     */
    public function recommendations(int $userId): array
    {
        $wrongData = $this->wrongQuestions($userId);
        $wrongRows = $wrongData['wrong']->all();
        $masteredIds = $wrongData['mastered_ids'];

        $similar = $this->similarQuestions($userId, $wrongRows, $masteredIds);

        $formatQuestion = function (Question $q) {
            return [
                'id' => $q->id,
                'category_id' => $q->category_id,
                'type' => $q->type,
                'difficulty' => $q->difficulty,
                'title' => $q->title,
                'options' => $q->options,
                'score' => $q->score,
            ];
        };

        $wrongList = collect($wrongRows)->map(function ($row) {
            return [
                'id' => $row->question_id,
                'category_id' => $row->category_id,
                'type' => $row->type,
                'difficulty' => $row->difficulty,
                'title' => $row->title,
                'options' => is_string($row->options) ? json_decode($row->options, true) : $row->options,
                'score' => $row->score,
                'source' => PracticeAttempt::SOURCE_WRONG_QUESTION,
                'wrong_answer' => $row->student_answer,
                'exam_record_id' => $row->exam_record_id,
            ];
        })->values();

        $similarList = $similar->map(function ($item) use ($formatQuestion) {
            return array_merge($formatQuestion($item['question']), [
                'source' => PracticeAttempt::SOURCE_SIMILAR,
                'based_on_question_id' => $item['based_on_question_id'],
                'based_on_question_title' => $item['based_on_title'],
                'difficulty_gap' => $item['difficulty_gap'],
            ]);
        })->values();

        return [
            'wrong_questions' => $wrongList,
            'similar_questions' => $similarList,
            'rule_note' => '推荐仅来自本人真实错题与同知识点、同题型、难度相近的题目，不含热门题。',
        ];
    }

    /**
     * 判分（与 ExamController::checkAnswer 同口径），返回 bool；
     * essay 等不可自动判分的题型返回 null。
     */
    public static function grade(Question $question, string $userAnswer): ?bool
    {
        switch ($question->type) {
            case Question::TYPE_SINGLE_CHOICE:
            case Question::TYPE_TRUE_FALSE:
            case Question::TYPE_FILL_BLANK:
                return strtoupper(trim($userAnswer)) === strtoupper(trim($question->answer));
            case Question::TYPE_MULTIPLE_CHOICE:
                $userAnswers = explode(',', strtoupper(trim($userAnswer)));
                $correctAnswers = explode(',', strtoupper(trim($question->answer)));
                sort($userAnswers);
                sort($correctAnswers);
                return $userAnswers === $correctAnswers;
            default:
                return null;
        }
    }
}
