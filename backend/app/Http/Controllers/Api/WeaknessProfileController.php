<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\User;
use App\Support\WeaknessProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 知识点弱项画像。
 *
 * 数据全部来源于真实正式考试作答（graded），只读分析；
 * 画像仅用于学习反馈，不写入、不修改任何正式成绩。
 */
class WeaknessProfileController extends Controller
{
    public function __construct(private readonly WeaknessProfileService $profiles)
    {
    }

    /**
     * 学生：我的弱项画像（知识点 / 题型 / 难度 三维度失分）。
     */
    public function me(Request $request)
    {
        $profile = $this->profiles->build([$request->user()->id]);

        return response()->json([
            'message' => $profile['empty'] ? '暂无已完成的正式考试，画像将在交卷后生成' : '画像仅用于学习反馈，不计入正式成绩',
            'scope' => 'self',
            'weakness' => $profile,
            'thresholds' => $this->thresholds(),
        ]);
    }

    /**
     * 老师/管理员：可查看的班级列表。
     */
    public function classes(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isTeacher()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $query = ClassRoom::withCount('students')->with('teacher:id,username,real_name');
        if (!$user->isAdmin()) {
            $query->where('teacher_id', $user->id);
        }

        $classes = $query->where('status', 1)->orderBy('id')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'description' => $c->description,
            'teacher_name' => $c->teacher?->real_name ?: $c->teacher?->username,
            'student_count' => $c->students_count,
        ]);

        return response()->json(['classes' => $classes]);
    }

    /**
     * 老师/管理员：某班级的共同薄弱点画像。
     */
    public function classProfile(Request $request, int $classId)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isTeacher()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $class = ClassRoom::with('students:id,username,real_name')->find($classId);
        if (!$class) {
            return response()->json(['message' => '班级不存在'], 404);
        }
        if (!$user->isAdmin() && (int) $class->teacher_id !== (int) $user->id) {
            return response()->json(['message' => '无权查看该班级'], 403);
        }

        $studentIds = $class->students->pluck('id')->all();
        $profile = $this->profiles->build($studentIds);

        $students = $class->students->map(fn ($s) => [
            'id' => $s->id,
            'username' => $s->username,
            'real_name' => $s->real_name,
        ]);

        return response()->json([
            'message' => '班级画像为聚合反馈，仅用于教学参考，不影响任何学生正式成绩',
            'scope' => 'class',
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'description' => $class->description,
                'students' => $students,
            ],
            'weakness' => $profile,
            'thresholds' => $this->thresholds(),
        ]);
    }

    private function thresholds(): array
    {
        return [
            'weak_correct_rate_below' => WeaknessProfileService::WEAK_RATE,
            'min_attempts_category' => WeaknessProfileService::MIN_ATTEMPTS_CATEGORY,
            'min_attempts_dimension' => WeaknessProfileService::MIN_ATTEMPTS_DIMENSION,
        ];
    }
}
