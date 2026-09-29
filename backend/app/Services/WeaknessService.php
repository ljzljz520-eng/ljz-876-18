<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 知识点弱项画像服务
 *
 * 数据来源（只读，不会修改任何正式成绩）：
 *   1) exam_record_answers 关联已评分(graded)的 exam_records —— 真实考试作答
 *   2) practice_attempts —— 学生基于推荐完成的自主练习
 *
 * 按三个维度聚合：知识点(题目分类) / 题型 / 难度层级。
 */
class WeaknessService
{
    /** 判定为薄弱的最低作答次数（样本太少不下结论） */
    public const MIN_ATTEMPTS = 1;

    /** 正确率低于该阈值(%)视为薄弱 */
    public const WEAK_RATE_THRESHOLD = 70.0;

    /**
     * 获取一批学生在所有维度上的作答明细（已按题目维度去重合并）
     *
     * @param  array|Collection  $userIds
     * @return Collection 每行: question_id, category_id, type, difficulty, is_correct(0/1), source
     */
    public function getAttemptRows($userIds): Collection
    {
        $userIds = collect($userIds)->all();
        if (empty($userIds)) {
            return collect();
        }

        // 1) 正式考试作答（只统计已评分记录，不触碰未交卷/进行中数据）
        $examRows = DB::table('exam_record_answers as era')
            ->join('exam_records as er', 'era.exam_record_id', '=', 'er.id')
            ->join('questions as q', 'era.question_id', '=', 'q.id')
            ->whereIn('er.user_id', $userIds)
            ->where('er.status', 'graded')
            ->select(
                'era.question_id',
                'q.category_id',
                'q.type',
                'q.difficulty',
                DB::raw('IF(era.is_correct = 1, 1, 0) as is_correct'),
                DB::raw("'exam' as source")
            )
            ->get();

        // 2) 自主练习作答（练习表独立于考试成绩）
        $practiceRows = DB::table('practice_attempts as pa')
            ->join('questions as q', 'pa.question_id', '=', 'q.id')
            ->whereIn('pa.user_id', $userIds)
            ->select(
                'pa.question_id',
                'q.category_id',
                'q.type',
                'q.difficulty',
                DB::raw('IF(pa.is_correct = 1, 1, 0) as is_correct'),
                DB::raw("'practice' as source")
            )
            ->get();

        return $examRows->concat($practiceRows);
    }

    /**
     * 构建画像：知识点 / 题型 / 难度 三个维度 + 总体概览
     */
    public function buildProfile(Collection $rows, array $categoryMap = null): array
    {
        $categories = $this->aggregate($rows, 'category_id');
        $types = $this->aggregate($rows, 'type');
        $difficulties = $this->aggregate($rows, 'difficulty');

        $categoryNames = $categoryMap ?? $this->categoryNameMap();
        $typeNames = Question::TYPES;
        // Question 模型常量是 1=>'简单' ...
        $difficultyNames = [
            Question::DIFFICULTY_EASY => '简单',
            Question::DIFFICULTY_MEDIUM => '中等',
            Question::DIFFICULTY_HARD => '困难',
        ];

        $dimension = function (Collection $agg, array $names, $id) {
            return $agg->map(function ($item, $key) use ($names, $id) {
                $rate = $item['total'] > 0
                    ? round($item['correct'] / $item['total'] * 100, 1)
                    : 0.0;

                // 分组键保留原始类型（整型维度用于查难度表，字符串维度直接作为题型值）
                $rawKey = ($key === '' || $key === null) ? null : (is_numeric($key) ? (int) $key : $key);

                return [
                    $id => $rawKey,
                    'name' => $names[$rawKey] ?? (string) $key,
                    'total_attempts' => $item['total'],
                    'correct_count' => $item['correct'],
                    'wrong_count' => $item['total'] - $item['correct'],
                    'correct_rate' => $rate,
                    'is_weak' => $item['total'] >= self::MIN_ATTEMPTS
                        && $rate < self::WEAK_RATE_THRESHOLD,
                ];
            })->values();
        };

        $byCategory = $dimension($categories, $categoryNames, 'category_id')
            ->sortBy('correct_rate')
            ->values();
        $byType = $dimension($types, $typeNames, 'type')
            ->sortBy('correct_rate')
            ->values();
        $byDifficulty = $dimension($difficulties, $difficultyNames, 'difficulty')
            ->sortBy('correct_rate')
            ->values();

        $totalAttempts = (int) $rows->count();
        $totalCorrect = (int) $rows->where('is_correct', 1)->count();

        return [
            'summary' => [
                'total_attempts' => $totalAttempts,
                'correct_count' => $totalCorrect,
                'wrong_count' => $totalAttempts - $totalCorrect,
                'correct_rate' => $totalAttempts > 0
                    ? round($totalCorrect / $totalAttempts * 100, 1)
                    : 0.0,
            ],
            'by_category' => $byCategory,
            'by_type' => $byType,
            'by_difficulty' => $byDifficulty,
            'weak_categories' => $byCategory->where('is_weak', true)->values(),
        ];
    }

    /**
     * 按指定字段聚合作答正确/错误数量
     */
    protected function aggregate(Collection $rows, string $groupField): Collection
    {
        return $rows->groupBy(function ($row) use ($groupField) {
            return (string) ($row->{$groupField} ?? '');
        })->map(function (Collection $group) {
            return [
                'total' => $group->count(),
                'correct' => $group->where('is_correct', 1)->count(),
            ];
        });
    }

    public function categoryNameMap(): array
    {
        return QuestionCategory::pluck('name', 'id')->all();
    }

    /**
     * 学生画像（个人维度）
     */
    public function studentProfile(int $userId): array
    {
        $rows = $this->getAttemptRows([$userId]);
        return $this->buildProfile($rows, $this->categoryNameMap());
    }
}
