<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracticeAttempt;
use App\Models\Question;
use App\Services\RecommendationService;
use App\Services\WeaknessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 学生端：知识点弱项画像 + 基于真实错题的练习推荐
 *
 * 重要边界：本控制器的所有写操作只落在 practice_attempts，
 * 绝不修改 exam_records / exam_record_answers，画像不影响正式成绩。
 */
class WeaknessController extends Controller
{
    public function __construct(
        protected WeaknessService $weaknessService,
        protected RecommendationService $recommendationService
    ) {
    }

    /**
     * 我的弱项画像：知识点 / 题型 / 难度
     */
    public function profile(Request $request)
    {
        $userId = $request->user()->id;
        $profile = $this->weaknessService->studentProfile($userId);

        return response()->json([
            'profile' => $profile,
            'notice' => '画像仅基于已结束考试的真实作答与你的练习记录生成，只用于学习反馈，不影响正式成绩。',
        ]);
    }

    /**
     * 我的练习推荐：真实错题 + 相近题
     */
    public function recommendations(Request $request)
    {
        $recommendations = $this->recommendationService->recommendations($request->user()->id);

        return response()->json($recommendations);
    }

    /**
     * 提交一道练习题（来自推荐或错题本）
     * 判分结果只写入练习表，不计入考试成绩。
     */
    public function submitPractice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|exists:questions,id',
            'answer' => 'required|string',
            'source' => 'nullable|in:wrong_question,similar,manual',
            'related_question_id' => 'nullable|exists:questions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $question = Question::where('status', 1)->findOrFail($request->question_id);

        $isCorrect = RecommendationService::grade($question, $request->answer);
        if ($isCorrect === null) {
            return response()->json([
                'message' => '该题型暂不支持在线练习自动判分',
            ], 422);
        }

        $attempt = DB::transaction(function () use ($request, $question, $isCorrect) {
            return PracticeAttempt::create([
                'user_id' => $request->user()->id,
                'question_id' => $question->id,
                'answer' => $request->answer,
                'is_correct' => $isCorrect,
                'source' => $request->input('source', 'manual'),
                'related_question_id' => $request->input('related_question_id'),
            ]);
        });

        return response()->json([
            'message' => '练习已提交（不计入正式成绩）',
            'attempt' => [
                'id' => $attempt->id,
                'question_id' => $attempt->question_id,
                'is_correct' => $attempt->is_correct,
            ],
            'correct_answer' => $question->answer,
            'analysis' => $question->analysis,
            'affects_grade' => false,
        ]);
    }

    /**
     * 我的练习历史（供学生回看）
     */
    public function practiceHistory(Request $request)
    {
        $attempts = PracticeAttempt::with('question:id,title,type,difficulty,category_id')
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json(['attempts' => $attempts]);
    }
}
