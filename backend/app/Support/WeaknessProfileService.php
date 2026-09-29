<?php

namespace App\Support;

use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Support\Facades\DB;

/**
 * 知识点弱项画像聚合服务。
 *
 * 数据来源严格限定为「真实正式考试」：
 *   exam_records(status=graded) + exam_record_answers，与课后练习完全隔离。
 *
 * 画像仅作为学习反馈，不参与任何正式成绩计算，也不会回写考试数据。
 */
class WeaknessProfileService
{
    /** 判定薄弱的正确率阈值（低于该值） */
    public const WEAK_RATE = 60.0;

    /** 知识点维度判定薄弱所需的最少作答数 */
    public const MIN_ATTEMPTS_CATEGORY = 2;

    /** 题型/难度维度判定薄弱所需的最少作答数 */
    public const MIN_ATTEMPTS_DIMENSION = 2;

    /** 每个维度返回的薄弱项条数 */
    public const TOP_LIMIT = 10;

    /**
     * 按一组用户ID聚合画像（学生画像传单个ID，班级画像传班级全部学生ID）。
     */
    public function build(array $userIds): array
    {
        if (empty($userIds)) {
            return $this->emptyProfile();
        }

        // 真实考试作答明细（仅 graded）
        $rows = DB::table('exam_record_answers as era')
            ->join('exam_records as er', 'era.exam_record_id', '=', 'er.id')
            ->join('questions as q', 'era.question_id', '=', 'q.id')
            ->whereIn('er.user_id', $userIds)
            ->where('er.status', 'graded')
            ->select(
                'er.user_id',
                'era.question_id',
                'q.category_id',
                'q.type',
                'q.difficulty',
                'era.is_correct',
                'era.score as earned'
            )
            ->get();

        if ($rows->isEmpty()) {
            return $this->emptyProfile();
        }

        // 每题在试卷中的标准分值（取该题被考过的分值，题目本身分值兜底）
        $paperScore = DB::table('exam_paper_questions')
            ->select('question_id', DB::raw('MAX(score) as max_score'))
            ->groupBy('question_id')
            ->pluck('max_score', 'question_id');

        $questionBaseScore = Question::whereIn('id', $rows->pluck('question_id')->unique())
            ->pluck('score', 'id');

        // ---------- 按题目聚合 ----------
        $byQuestion = [];
        foreach ($rows as $r) {
            $qid = $r->question_id;
            if (!isset($byQuestion[$qid])) {
                $standard = (float) ($paperScore[$qid] ?? $questionBaseScore[$qid] ?? 0);
                $byQuestion[$qid] = [
                    'question_id' => $qid,
                    'category_id' => $r->category_id,
                    'type' => $r->type,
                    'difficulty' => (int) $r->difficulty,
                    'attempts' => 0,
                    'correct' => 0,
                    'lost_score' => 0.0,
                    'affected_users' => [],
                    'wrong_users' => [],
                    'standard_score' => $standard,
                ];
            }

            $byQuestion[$qid]['attempts']++;
            $byQuestion[$qid]['affected_users'][$r->user_id] = true;
            if ((int) $r->is_correct === 1) {
                $byQuestion[$qid]['correct']++;
            } else {
                $standard = $byQuestion[$qid]['standard_score'];
                $byQuestion[$qid]['lost_score'] += max(0, $standard - (float) $r->earned);
                $byQuestion[$qid]['wrong_users'][$r->user_id] = true;
            }
        }

        // ---------- 三维度聚合 ----------
        $categoryBuckets = [];
        $typeBuckets = [];
        $difficultyBuckets = [];

        foreach ($byQuestion as $qid => $q) {
            $this->accumulate($categoryBuckets, $q['category_id'], $q);
            $this->accumulate($typeBuckets, $q['type'], $q);
            $this->accumulate($difficultyBuckets, (string) $q['difficulty'], $q);
        }

        $categories = QuestionCategory::whereIn('id', array_keys($categoryBuckets))
            ->get()->keyBy('id');

        $categoryResult = $this->finalizeCategory($categoryBuckets, $categories, count($userIds));
        $typeResult = $this->finalizeGeneric($typeBuckets, Question::TYPES, false, count($userIds));
        $difficultyResult = $this->finalizeGeneric($difficultyBuckets, Question::DIFFICULTIES, true, count($userIds));

        // ---------- 真实错题清单（练习推荐的数据基础） ----------
        $wrongQuestions = collect($byQuestion)
            ->filter(fn ($q) => $q['correct'] < $q['attempts'])
            ->map(function ($q) use ($questionBaseScore) {
                return [
                    'question_id' => $q['question_id'],
                    'category_id' => $q['category_id'],
                    'type' => $q['type'],
                    'difficulty' => $q['difficulty'],
                    'attempts' => $q['attempts'],
                    'correct' => $q['correct'],
                    'correct_rate' => $this->rate($q['correct'], $q['attempts']),
                    'lost_score' => round($q['lost_score'], 2),
                    'affected_students' => count($q['affected_users']),
                    'wrong_students' => count($q['wrong_users']),
                ];
            })
            ->sortByDesc('lost_score')
            ->values()
            ->all();

        return [
            'empty' => false,
            'student_count' => count($userIds),
            'summary' => [
                'total_answers' => array_sum(array_map(fn ($q) => $q['attempts'], $byQuestion)),
                'total_correct' => array_sum(array_map(fn ($q) => $q['correct'], $byQuestion)),
                'total_lost_score' => round(array_sum(array_map(fn ($q) => $q['lost_score'], $byQuestion)), 2),
                'overall_correct_rate' => $this->rate(
                    array_sum(array_map(fn ($q) => $q['correct'], $byQuestion)),
                    array_sum(array_map(fn ($q) => $q['attempts'], $byQuestion))
                ),
            ],
            'knowledge_points' => $categoryResult,
            'question_types' => $typeResult,
            'difficulties' => $difficultyResult,
            'wrong_questions' => $wrongQuestions,
        ];
    }

    public function emptyProfile(): array
    {
        return [
            'empty' => true,
            'student_count' => 0,
            'summary' => [
                'total_answers' => 0,
                'total_correct' => 0,
                'total_lost_score' => 0,
                'overall_correct_rate' => 0,
            ],
            'knowledge_points' => [],
            'question_types' => [],
            'difficulties' => [],
            'wrong_questions' => [],
        ];
    }

    private function accumulate(array &$buckets, $key, array $q): void
    {
        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'attempts' => 0,
                'correct' => 0,
                'lost_score' => 0.0,
                'affected_users' => [],
                'wrong_users' => [],
            ];
        }
        $buckets[$key]['attempts'] += $q['attempts'];
        $buckets[$key]['correct'] += $q['correct'];
        $buckets[$key]['lost_score'] += $q['lost_score'];
        foreach ($q['affected_users'] as $uid => $_) {
            $buckets[$key]['affected_users'][$uid] = true;
        }
        foreach ($q['wrong_users'] as $uid => $_) {
            $buckets[$key]['wrong_users'][$uid] = true;
        }
    }

    private function rate(int $correct, int $attempts): float
    {
        return $attempts > 0 ? round($correct / $attempts * 100, 1) : 0.0;
    }

    private function markWeak(float $rate, int $attempts, int $minAttempts): bool
    {
        return $attempts >= $minAttempts && $rate < self::WEAK_RATE;
    }

    private function finalizeCategory(array $buckets, $categories, int $studentCount): array
    {
        $result = [];
        foreach ($buckets as $catId => $b) {
            $rate = $this->rate($b['correct'], $b['attempts']);
            $cat = $categories->get((int) $catId);
            $result[] = [
                'dimension_key' => (string) $catId,
                'name' => $cat->name ?? ('分类#' . $catId),
                'category_id' => (int) $catId,
                'parent_id' => $cat ? (int) $cat->parent_id : 0,
                'attempts' => $b['attempts'],
                'correct' => $b['correct'],
                'correct_rate' => $rate,
                'lost_score' => round($b['lost_score'], 2),
                'affected_students' => count($b['affected_users']),
                'wrong_students' => count($b['wrong_users']),
                'is_weak' => $this->markWeak($rate, $b['attempts'], self::MIN_ATTEMPTS_CATEGORY),
            ];
        }

        return $this->sortAndLimit($result, $studentCount);
    }

    private function finalizeGeneric(array $buckets, array $labels, bool $isDifficulty, int $studentCount): array
    {
        $result = [];
        foreach ($buckets as $key => $b) {
            $rate = $this->rate($b['correct'], $b['attempts']);
            $labelKey = $isDifficulty ? (int) $key : $key;
            $result[] = [
                'dimension_key' => (string) $key,
                'name' => $labels[$labelKey] ?? (string) $key,
                'attempts' => $b['attempts'],
                'correct' => $b['correct'],
                'correct_rate' => $rate,
                'lost_score' => round($b['lost_score'], 2),
                'affected_students' => count($b['affected_users']),
                'wrong_students' => count($b['wrong_users']),
                'is_weak' => $this->markWeak($rate, $b['attempts'], self::MIN_ATTEMPTS_DIMENSION),
            ];
        }

        return $this->sortAndLimit($result, $studentCount);
    }

    private function sortAndLimit(array $items, int $studentCount): array
    {
        usort($items, function ($a, $b) use ($studentCount) {
            // 班级画像：优先看覆盖的错误人数（共同薄弱）
            if ($studentCount > 1 && $a['wrong_students'] !== $b['wrong_students']) {
                return $b['wrong_students'] <=> $a['wrong_students'];
            }
            if ($a['is_weak'] !== $b['is_weak']) {
                return $b['is_weak'] <=> $a['is_weak'];
            }
            if ($a['lost_score'] !== $b['lost_score']) {
                return $b['lost_score'] <=> $a['lost_score'];
            }
            return $a['correct_rate'] <=> $b['correct_rate'];
        });

        return array_slice($items, 0, self::TOP_LIMIT);
    }
}
