<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 学生练习记录表
 * 专门承载"弱项画像"里的自主练习数据。
 * 与正式考试成绩(exam_records/exam_record_answers)完全隔离：
 *  - 不会写回 exam_records.score
 *  - 不会改变任何已评分试卷结果
 * 仅作为学习反馈统计来源。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('practice_attempts')) {
            Schema::create('practice_attempts', function (Blueprint $template) {
                $template->id();
                $template->unsignedBigInteger('user_id')->comment('学生ID');
                $template->unsignedBigInteger('question_id')->comment('题目ID');
                $template->text('answer')->comment('学生答案');
                $template->boolean('is_correct')->default(false)->comment('是否正确');
                $template->string('source', 30)->default('recommendation')->comment('来源: wrong_question/similar/manual');
                $template->unsignedBigInteger('related_question_id')->nullable()->comment('相近题推荐时关联的错题ID');
                $template->timestamps();
                $template->index(['user_id', 'question_id']);
                $template->index(['user_id', 'is_correct']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_attempts');
    }
};
