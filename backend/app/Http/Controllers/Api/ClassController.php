<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 教师端：班级管理（用于按班级查看共同薄弱点）
 */
class ClassController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = SchoolClass::with(['creator:id,username,real_name'])
            ->withCount('students')
            ->where('status', 1);

        // 教师只看自己创建的班级；管理员可看全部
        if (!$user->isAdmin()) {
            $query->where('created_by', $user->id);
        }

        $classes = $query->orderBy('id', 'desc')->paginate($request->input('per_page', 15));

        return response()->json(['classes' => $classes]);
    }

    public function show(Request $request, SchoolClass $schoolClass)
    {
        if ($denied = $this->denyUnlessManager($request, $schoolClass)) {
            return $denied;
        }

        $schoolClass->load([
            'students:id,username,real_name,email',
            'creator:id,username,real_name',
        ]);

        return response()->json(['school_class' => $schoolClass]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $schoolClass = SchoolClass::create([
            'name' => $request->name,
            'description' => $request->description,
            'created_by' => $request->user()->id,
            'status' => 1,
        ]);

        $studentIds = $this->filterStudentIds($request->input('student_ids', []));
        if (!empty($studentIds)) {
            $schoolClass->students()->sync($studentIds);
        }

        return response()->json([
            'message' => '班级创建成功',
            'school_class' => $schoolClass->loadCount('students'),
        ], 201);
    }

    public function update(Request $request, SchoolClass $schoolClass)
    {
        if ($denied = $this->denyUnlessManager($request, $schoolClass)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $schoolClass->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        if ($request->exists('student_ids')) {
            $studentIds = $this->filterStudentIds($request->input('student_ids', []));
            $schoolClass->students()->sync($studentIds);
        }

        return response()->json([
            'message' => '班级更新成功',
            'school_class' => $schoolClass->loadCount('students'),
        ]);
    }

    public function destroy(Request $request, SchoolClass $schoolClass)
    {
        if ($denied = $this->denyUnlessManager($request, $schoolClass)) {
            return $denied;
        }

        $schoolClass->students()->detach();
        $schoolClass->delete();

        return response()->json(['message' => '班级已删除']);
    }

    /**
     * 可加入班级的学生名单（选择学生时用）
     */
    public function studentOptions(Request $request)
    {
        $students = User::where('role', User::ROLE_STUDENT)
            ->where('status', 1)
            ->orderBy('id')
            ->select('id', 'username', 'real_name', 'email')
            ->get();

        return response()->json(['students' => $students]);
    }

    protected function filterStudentIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return User::where('role', User::ROLE_STUDENT)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    protected function denyUnlessManager(Request $request, SchoolClass $schoolClass)
    {
        $user = $request->user();
        if (!$user->isAdmin() && $schoolClass->created_by !== $user->id) {
            return response()->json(['message' => '无权操作此班级'], 403);
        }
        return null;
    }
}
