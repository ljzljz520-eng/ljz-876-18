<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamPaperController;
use App\Http\Controllers\Api\PracticeController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\WeaknessProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['api', 'auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    Route::prefix('questions')->group(function () {
        Route::get('/', [QuestionController::class, 'index']);
        Route::post('/', [QuestionController::class, 'store']);
        Route::get('/categories', [QuestionController::class, 'categories']);
        Route::post('/categories', [QuestionController::class, 'storeCategory']);
        Route::get('/{question}', [QuestionController::class, 'show']);
        Route::put('/{question}', [QuestionController::class, 'update']);
        Route::delete('/{question}', [QuestionController::class, 'destroy']);
    });

    Route::prefix('exam-papers')->group(function () {
        Route::get('/', [ExamPaperController::class, 'index']);
        Route::post('/', [ExamPaperController::class, 'store']);
        Route::get('/{examPaper}', [ExamPaperController::class, 'show']);
        Route::put('/{examPaper}', [ExamPaperController::class, 'update']);
        Route::delete('/{examPaper}', [ExamPaperController::class, 'destroy']);
        Route::post('/{examPaper}/questions', [ExamPaperController::class, 'addQuestions']);
        Route::delete('/{examPaper}/questions/{question}', [ExamPaperController::class, 'removeQuestion']);
    });

    Route::prefix('exams')->group(function () {
        Route::get('/', [ExamController::class, 'index']);
        Route::post('/{examPaper}/start', [ExamController::class, 'start']);
        Route::get('/{examPaper}/questions', [ExamController::class, 'getQuestions']);
        Route::post('/{examPaper}/submit', [ExamController::class, 'submit']);
        Route::get('/records', [ExamController::class, 'myRecords']);
        Route::get('/records/{record}', [ExamController::class, 'showRecord']);
    });

    Route::prefix('scores')->group(function () {
        Route::get('/statistics', [ScoreController::class, 'statistics']);
        Route::get('/ranking/{examPaper}', [ScoreController::class, 'ranking']);
        Route::get('/analysis/{examPaper}', [ScoreController::class, 'analysis']);
    });

    // 知识点弱项画像：数据仅来源于真实正式考试，只作为学习反馈，不影响正式成绩
    Route::prefix('weakness')->group(function () {
        // 学生：我的弱项画像（知识点 / 题型 / 难度）
        Route::get('/me', [WeaknessProfileController::class, 'me']);
        // 老师/管理员：可查看的班级列表
        Route::get('/classes', [WeaknessProfileController::class, 'classes'])->middleware('role:admin,teacher');
        // 老师/管理员：某班级的共同薄弱点
        Route::get('/classes/{classId}', [WeaknessProfileController::class, 'classProfile'])
            ->whereNumber('classId')
            ->middleware('role:admin,teacher');
    });

    // 课后练习：推荐只来自真实错题与相近题，作答与正式成绩隔离
    Route::prefix('practice')->group(function () {
        Route::get('/recommendations', [PracticeController::class, 'recommendations']);
        Route::post('/check', [PracticeController::class, 'check']);
        Route::get('/history', [PracticeController::class, 'history']);
    });
});
