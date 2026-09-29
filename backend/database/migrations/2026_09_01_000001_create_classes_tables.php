<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 班级表 + 班级-学生关联表
 * 仅用于教师端按班级查看共同薄弱点，与正式成绩无关。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_classes')) {
            Schema::create('school_classes', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->comment('班级名称');
                $table->text('description')->nullable()->comment('班级描述');
                $table->unsignedBigInteger('created_by')->nullable()->comment('创建教师ID');
                $table->boolean('status')->default(1)->comment('状态: 1-启用 0-禁用');
                $table->timestamps();
                $table->index('created_by');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('class_student')) {
            Schema::create('class_student', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('class_id')->comment('班级ID');
                $table->unsignedBigInteger('user_id')->comment('学生ID');
                $table->timestamps();
                $table->unique(['class_id', 'user_id'], 'uk_class_student');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_student');
        Schema::dropIfExists('school_classes');
    }
};
