<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\WeaknessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 教师端：按班级查看学生共同薄弱点
 * 只读聚合，不影响任何学生正式成绩。
 */
class ClassWeaknessController extends Controller
{
    public function __construct(protected WeaknessService $weaknessService)
    {
    }

    public function show(Request $request, SchoolClass $schoolClass)
    {
        $user = $request->user();
        if (!$user->isAdmin() && $schoolClass->created_by !== $user->id) {
            return response()->json(['message' => '无权查看此班级'], 403);
        }

        $studentIds = $schoolClass->students()->pluck('users.id')->all();

        if (empty($studentIds)) {
            return response()->json([
                'school_class' => ['id' => $schoolClass->id, 'name' => $schoolClass->name],
                'student_count' => 0,
                'profile' => null,
                'weak_students' => [],
                'notice' => '该班级还没有学生',
            ]);
        }

        // 班级整体画像（所有学生作答合并聚合）
        $rows = $this->weaknessService->getAttemptRows($studentIds);
        $profile = $this->weaknessService->buildProfile($rows, $this->weaknessService->categoryNameMap());

        // 每个学生各自的薄弱知识点，便于教师定位需要关注的人
        $weakStudents = [];

        // 逐学生聚合（复用服务，按学生取数）
        $students = User::whereIn('id', $studentIds)
            ->select('id', 'username', 'real_name')
            ->get()
            ->keyBy('id');

        foreach ($studentIds as $studentId) {
            $studentProfileRows = $this->weaknessService->getAttemptRows([$studentId]);
            $studentProfile = $this->weaknessService->buildProfile(
                $studentProfileRows,
                $this->weaknessService->categoryNameMap()
            );

            $weakCats = $studentProfile['weak_categories']
                ->map(fn ($c) => [
                    'category_id' => $c['category_id'],
                    'name' => $c['name'],
                    'correct_rate' => $c['correct_rate'],
                    'wrong_count' => $c['wrong_count'],
                ])
                ->values();

            if ($weakCats->isEmpty()) {
                continue;
            }

            $student = $students->get($studentId);
            $weakStudents[] = [
                'student_id' => $studentId,
                'username' => $student?->username,
                'real_name' => $student?->real_name,
                'summary' => $studentProfile['summary'],
                'weak_categories' => $weakCats,
            ];
        }

        // 共同薄弱点：按错误人数从多到少排序，方便教师安排班级讲评
        $commonWeak = $profile['weak_categories']
            ->map(function ($category) use ($studentIds) {
                // 统计该知识点上答错过的学生人数（至少错一次）
                $wrongStudentCount = DB::table('exam_record_answers as era')
                    ->join('exam_records as er', 'era.exam_record_id', '=', 'er.id')
                    ->join('questions as q', 'era.question_id', '=', 'q.id')
                    ->whereIn('er.user_id', $studentIds)
                    ->where('er.status', 'graded')
                    ->where('era.is_correct', 0)
                    ->where('q.category_id', $category['category_id'])
                    ->distinct('er.user_id')
                    ->count('er.user_id');

                return array_merge($category, [
                    'wrong_student_count' => $wrongStudentCount,
                    'coverage' => round($wrongStudentCount / count($studentIds) * 100, 1),
                ]);
            })
            ->sortByDesc('wrong_student_count')
            ->values();

        return response()->json([
            'school_class' => ['id' => $schoolClass->id, 'name' => $schoolClass->name],
            'student_count' => count($studentIds),
            'profile' => $profile,
            'common_weak_categories' => $commonWeak,
            'weak_students' => collect($weakStudents)->sortByDesc(
                fn ($s) => $s['weak_categories']->count()
            )->values(),
            'notice' => '班级画像基于学生已结束考试与练习记录聚合，仅用于教学反馈，不改变正式成绩。',
        ]);
    }
}
