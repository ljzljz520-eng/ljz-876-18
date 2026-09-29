<?php

namespace App\Support;

use App\Models\Question;

/**
 * 客观题判分（与正式考试判分规则完全一致）。
 * 同时供「正式考试提交」与「课后练习」使用，保证反馈口径一致。
 * essay 等主观题无法自动判分，统一返回 null。
 */
class AnswerChecker
{
    public static function isCorrect(Question $question, ?string $userAnswer): ?bool
    {
        if ($userAnswer === null || $userAnswer === '') {
            return false;
        }

        $correctAnswer = $question->answer;

        return match ($question->type) {
            Question::TYPE_SINGLE_CHOICE,
            Question::TYPE_TRUE_FALSE,
            Question::TYPE_FILL_BLANK => strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer)),
            Question::TYPE_MULTIPLE_CHOICE => self::sameSet($userAnswer, $correctAnswer),
            default => null, // essay：无法自动判分
        };
    }

    /**
     * 该题型是否可自动判分（决定能否进入练习推荐）。
     */
    public static function isAutoGradable(Question $question): bool
    {
        return in_array($question->type, [
            Question::TYPE_SINGLE_CHOICE,
            Question::TYPE_MULTIPLE_CHOICE,
            Question::TYPE_TRUE_FALSE,
            Question::TYPE_FILL_BLANK,
        ], true);
    }

    protected static function sameSet(string $a, string $b): bool
    {
        $aParts = explode(',', strtoupper(trim($a)));
        $bParts = explode(',', strtoupper(trim($b)));
        sort($aParts);
        sort($bParts);
        return $aParts === $bParts;
    }
}
