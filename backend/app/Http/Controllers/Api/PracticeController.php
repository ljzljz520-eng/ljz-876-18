<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracticeAttempt;
use App\Models\Question;
use App\Support\AnswerChecker;
use App\Support\PracticeRecommendationService;
use App\Support\WeaknessProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 课后练习：基于真实错题 / 相近题的个性化推荐与作答。
 *
 * 所有作答只落库到 practice_attempts；正式成绩仍以 exam_records 为准，
 * 因此练习无论对错都不会影响学生的正式成绩。
 */
class PracticeController extends Controller
{
    public function __construct(
        private readonly WeaknessProfileService $profiles,
        private readonly PracticeRecommendationService $recommender
    ) {
    }

    /**
     * 我的练习推荐。
     */
    public function recommendations(Request $request)
    {
        $user = $request->user();

        $profile = $this->profiles->build([$user->id]);
        $recommendations = $this->recommender->recommend($user->id, $profile['wrong_questions']);

        return response()->json([
            'message' => '推荐题均来自你的真实错题或其相近题，仅供学习反馈，不计入正式成绩',
            'based_on' => [
                'wrong_question_count' => count($profile['wrong_questions']),
                'overall_correct_rate' => $profile['summary']['overall_correct_rate'],
            ],
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * 提交一道练习题，返回即时判分与解析。
     */
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|exists:questions,id',
            'answer' => 'required|string',
            'source' => 'nullable|in:wrong_question,similar_question',
            'related_question_id' => 'nullable|exists:questions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $question = Question::where('status', 1)->findOrFail($request->question_id);

        $isCorrect = AnswerChecker::isCorrect($question, $request->answer);
        if ($isCorrect === null) {
            // 主观题不自动判分：不写入练习结果，仅展示参考答案
            return response()->json([
                'gradable' => false,
                'message' => '该题为主观题，无法自动判分，请对照参考答案与解析自评（不计入练习记录）',
                'question_id' => $question->id,
                'reference_answer' => $question->answer,
                'analysis' => $question->analysis,
            ]);
        }

        // 练习结果仅落 practice_attempts，与正式考试完全隔离
        $attempt = PracticeAttempt::create([
            'user_id' => $request->user()->id,
            'question_id' => $question->id,
            'answer' => $request->answer,
            'is_correct' => $isCorrect,
            'source' => $request->source ?? PracticeAttempt::SOURCE_SIMILAR,
            'related_question_id' => $request->related_question_id,
        ]);

        return response()->json([
            'gradable' => true,
            'is_correct' => $isCorrect,
            'message' => $isCorrect ? '回答正确，该题已标记为掌握' : '回答错误，建议查看解析后再练一次',
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'reference_answer' => $question->answer,
            'analysis' => $question->analysis,
            'affects_official_score' => false,
        ]);
    }

    /**
     * 我的练习历史（反馈用，便于学生看到练习进展；不含任何正式成绩字段）。
     */
    public function history(Request $request)
    {
        $attempts = PracticeAttempt::with('question:id,title,type,difficulty,category_id')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'question_id' => $a->question_id,
                'title' => $a->question?->title,
                'type' => $a->question?->type,
                'difficulty' => $a->question?->difficulty,
                'source' => $a->source,
                'source_label' => PracticeAttempt::SOURCES[$a->source] ?? $a->source,
                'is_correct' => $a->is_correct,
                'created_at' => $a->created_at,
            ]);

        $stats = [
            'total' => PracticeAttempt::where('user_id', $request->user()->id)->count(),
            'correct' => PracticeAttempt::where('user_id', $request->user()->id)->where('is_correct', 1)->count(),
        ];
        $stats['correct_rate'] = $stats['total'] > 0 ? round($stats['correct'] / $stats['total'] * 100, 1) : 0;

        return response()->json([
            'stats' => $stats,
            'attempts' => $attempts,
            'notice' => '练习数据仅用于学习反馈，不计入正式成绩',
        ]);
    }
}
