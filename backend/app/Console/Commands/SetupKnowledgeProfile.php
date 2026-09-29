<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 知识点弱项画像 / 课后练习 基础数据初始化。
 *
 * 幂等：可重复执行；已存在的数据会被跳过，不删除、不覆盖正式成绩。
 * 同时兼容全新部署（docker-compose 首次启动）与已有部署（追加表与演示数据）。
 */
class SetupKnowledgeProfile extends Command
{
    protected $signature = 'exam:setup-knowledge-profile';

    protected $description = '创建班级/练习表，并初始化弱项画像所需的题库、班级与真实作答演示数据（幂等）';

    // 与现有 users 种子一致的 password = "password" 的 bcrypt 值
    private const PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function handle(): int
    {
        $this->createTables();
        $this->ensureStudents();
        $this->ensureClasses();
        $this->ensureQuestions();
        $this->ensureExamHistory();

        $this->info('Knowledge profile schema/data is ready.');
        return self::SUCCESS;
    }

    private function createTables(): void
    {
        if (!Schema::hasTable('classes')) {
            Schema::create('classes', function ($table) {
                $table->id();
                $table->string('name', 100)->comment('班级名称');
                $table->unsignedBigInteger('teacher_id')->nullable()->comment('负责教师ID');
                $table->string('description', 255)->nullable()->comment('班级描述');
                $table->boolean('status')->default(1)->comment('1-启用 0-禁用');
                $table->timestamps();
                $table->index('teacher_id');
            });
            $this->line('  created table: classes');
        }

        if (!Schema::hasTable('class_student')) {
            Schema::create('class_student', function ($table) {
                $table->id();
                $table->unsignedBigInteger('class_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
                $table->unique(['class_id', 'user_id'], 'uk_class_user');
                $table->index('user_id');
            });
            $this->line('  created table: class_student');
        }

        if (!Schema::hasTable('practice_attempts')) {
            Schema::create('practice_attempts', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->comment('学生ID');
                $table->unsignedBigInteger('question_id')->comment('练习题目ID');
                $table->text('answer')->nullable()->comment('学生答案');
                $table->boolean('is_correct')->default(false)->comment('本次作答是否正确');
                $table->string('source', 30)->default('similar_question')->comment('推荐来源: wrong_question-真实错题 similar_question-相近题');
                $table->unsignedBigInteger('related_question_id')->nullable()->comment('相近题来源时对应的真实错题ID');
                $table->timestamps();
                $table->index(['user_id', 'question_id']);
                $table->index('user_id');
            });
            $this->line('  created table: practice_attempts');
        }
    }

    private function ensureStudents(): void
    {
        $students = [
            ['id' => 4, 'username' => 'student2', 'email' => 'student2@example.com', 'real_name' => '学生用户2'],
            ['id' => 5, 'username' => 'student3', 'email' => 'student3@example.com', 'real_name' => '学生用户3'],
        ];

        $now = now();
        foreach ($students as $s) {
            $exists = DB::table('users')->where('email', $s['email'])->exists();
            if (!$exists) {
                DB::table('users')->insert($s + [
                    'password' => self::PASSWORD_HASH,
                    'role' => 'student',
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->line("  added user: {$s['username']}");
            }
        }
    }

    private function ensureClasses(): void
    {
        $now = now();
        $classes = [
            ['id' => 1, 'name' => '计算机2301班', 'teacher_id' => 2, 'description' => '计算机基础方向'],
            ['id' => 2, 'name' => '计算机2302班', 'teacher_id' => 2, 'description' => '程序设计方向'],
        ];

        foreach ($classes as $c) {
            if (!DB::table('classes')->where('id', $c['id'])->exists()) {
                DB::table('classes')->insert($c + ['status' => 1, 'created_at' => $now, 'updated_at' => $now]);
                $this->line("  added class: {$c['name']}");
            }
        }

        $members = [
            ['class_id' => 1, 'user_id' => 3],
            ['class_id' => 1, 'user_id' => 4],
            ['class_id' => 1, 'user_id' => 5],
        ];
        foreach ($members as $m) {
            $exists = DB::table('class_student')
                ->where('class_id', $m['class_id'])->where('user_id', $m['user_id'])->exists();
            if (!$exists) {
                DB::table('class_student')->insert($m + ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    private function ensureQuestions(): void
    {
        if (DB::table('questions')->where('id', 8)->exists()) {
            return;
        }

        $now = now();
        $questions = [
            // 操作系统（分类4）
            [8, 4, 'single_choice', '下列关于进程和线程的描述，正确的是？',
                json_encode(['A' => '进程是资源分配的基本单位，线程是调度的基本单位', 'B' => '线程比进程占用更多的内存资源', 'C' => '进程之间无法进行通信', 'D' => '线程拥有独立的地址空间'], JSON_UNESCAPED_UNICODE),
                'A', '进程是资源分配单位，线程是CPU调度的基本单位', 2, 2.00],
            [9, 4, 'true_false', '死锁是指多个进程互相等待对方所持资源而无法继续执行的状态。', null,
                'true', '这是死锁的基本定义', 2, 1.00],
            [10, 4, 'single_choice', '分页存储管理中，逻辑地址到物理地址的转换需要借助？',
                json_encode(['A' => '段表', 'B' => '页表', 'C' => '文件分配表', 'D' => '进程控制块'], JSON_UNESCAPED_UNICODE),
                'B', '分页系统通过页表完成地址映射', 3, 2.00],
            [11, 4, 'multiple_choice', '下列属于进程调度算法的有？',
                json_encode(['A' => '先来先服务', 'B' => '短作业优先', 'C' => '时间片轮转', 'D' => '电梯调度算法'], JSON_UNESCAPED_UNICODE),
                'ABC', '电梯算法是磁盘调度算法，不是进程调度算法', 3, 3.00],
            // 数据结构（分类5）
            [12, 5, 'single_choice', '栈这种数据结构的特点是？',
                json_encode(['A' => '先进先出', 'B' => '后进先出', 'C' => '随机存取', 'D' => '双端进出'], JSON_UNESCAPED_UNICODE),
                'B', '栈是后进先出（LIFO）结构', 2, 2.00],
            [13, 5, 'true_false', '对二叉搜索树进行中序遍历，可以得到一个有序序列。', null,
                'true', '二叉搜索树的中序遍历即升序序列', 2, 1.00],
            [14, 5, 'multiple_choice', '下列排序算法中，属于稳定排序的有？',
                json_encode(['A' => '冒泡排序', 'B' => '归并排序', 'C' => '快速排序', 'D' => '选择排序'], JSON_UNESCAPED_UNICODE),
                'AB', '冒泡和归并稳定；快速排序、选择排序不稳定', 3, 3.00],
            [15, 5, 'fill_blank', '队列的特点是先进先出，其英文缩写为____。', null,
                'FIFO', 'First In First Out，缩写为 FIFO', 2, 2.00],
            // Python（分类6）
            [16, 6, 'single_choice', 'Python中定义函数使用的关键字是？',
                json_encode(['A' => 'function', 'B' => 'def', 'C' => 'func', 'D' => 'define'], JSON_UNESCAPED_UNICODE),
                'B', 'Python 使用 def 定义函数', 2, 1.00],
            [17, 6, 'true_false', 'Python 使用缩进来表示代码块，而不是大括号。', null,
                'true', 'Python 以缩进划分代码块', 1, 1.00],
            [18, 6, 'fill_blank', 'Python 中用于查看对象类型的内置函数是____()。', null,
                'type', 'type() 返回对象类型', 2, 1.00],
            [19, 6, 'multiple_choice', '下列哪些是 Python 的可变（mutable）数据类型？',
                json_encode(['A' => 'list（列表）', 'B' => 'dict（字典）', 'C' => 'tuple（元组）', 'D' => 'str（字符串）'], JSON_UNESCAPED_UNICODE),
                'AB', '列表和字典可变；元组和字符串不可变', 3, 3.00],
            // Java（分类7）
            [20, 7, 'single_choice', 'Java 中表示32位整数基本类型的关键字是？',
                json_encode(['A' => 'int', 'B' => 'Integer', 'C' => 'number', 'D' => 'long'], JSON_UNESCAPED_UNICODE),
                'A', 'int 是32位整数基本类型，Integer 是其包装类', 2, 2.00],
            [21, 7, 'true_false', 'Java 是跨平台语言，依靠 JVM 实现“一次编写，到处运行”。', null,
                'true', 'JVM 屏蔽了操作系统差异', 2, 1.00],
            // MongoDB（分类9）
            [22, 9, 'single_choice', 'MongoDB 中最基本的数据逻辑单元是？',
                json_encode(['A' => '行（Row）', 'B' => '文档（Document）', 'C' => '列（Column）', 'D' => '表（Table）'], JSON_UNESCAPED_UNICODE),
                'B', 'MongoDB 是文档数据库，基本单元是文档', 3, 2.00],
        ];

        foreach ($questions as $q) {
            [$id, $catId, $type, $title, $options, $answer, $analysis, $difficulty, $score] = $q;
            DB::table('questions')->insert([
                'id' => $id,
                'category_id' => $catId,
                'type' => $type,
                'title' => $title,
                'options' => $options,
                'answer' => $answer,
                'analysis' => $analysis,
                'difficulty' => $difficulty,
                'score' => $score,
                'created_by' => 2,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->line('  added 15 practice/similar questions (id 8-22)');
    }

    /**
     * 真实考试历史（graded 的 exam_records + exam_record_answers）。
     * 这是弱项画像唯一的正式数据源；练习数据不会写入这些表。
     */
    private function ensureExamHistory(): void
    {
        if (DB::table('exam_records')->where('user_id', 3)->exists()) {
            return;
        }

        $now = now();
        $start = now()->subDays(10);

        // [recordId, userId, paperId, score, 答案: [questionId => [answer, isCorrect, score]]]
        $history = [
            [1, 3, 1, 1.00, [
                1 => ['B', 0, 0.00],      // 操作系统 单选(易) 错
                2 => ['true', 1, 1.00],   // 操作系统 判断(易) 对
                3 => ['A', 0, 0.00],      // 数据结构 单选(中) 错
            ]],
            [2, 3, 2, 3.00, [
                4 => ['A', 1, 1.00],      // Python 单选(易) 对
                5 => ['AB', 0, 0.00],     // Python 多选(易) 错（漏选D）
                6 => ['A', 1, 2.00],      // MySQL 单选(易) 对
            ]],
            [3, 3, 3, 3.00, [
                6 => ['A', 1, 2.00],
                7 => ['true', 1, 1.00],
            ]],
            [4, 4, 1, 0.00, [
                1 => ['D', 0, 0.00],
                2 => ['false', 0, 0.00],
                3 => ['A', 0, 0.00],
            ]],
            [5, 4, 2, 0.00, [
                4 => ['B', 0, 0.00],
                5 => ['AC', 0, 0.00],
                6 => ['B', 0, 0.00],
            ]],
            [6, 5, 1, 3.00, [
                1 => ['C', 1, 2.00],
                2 => ['true', 1, 1.00],
                3 => ['B', 0, 0.00],     // 数据结构 单选(中) 错
            ]],
            [7, 5, 2, 3.00, [
                4 => ['A', 1, 1.00],
                5 => ['AD', 0, 0.00],    // 多选 错
                6 => ['A', 1, 2.00],
            ]],
        ];

        foreach ($history as [$recordId, $userId, $paperId, $score, $answers]) {
            DB::table('exam_records')->insert([
                'id' => $recordId,
                'user_id' => $userId,
                'exam_paper_id' => $paperId,
                'start_time' => $start,
                'end_time' => $start->copy()->addMinutes(20),
                'score' => $score,
                'status' => 'graded',
                'created_at' => $start,
                'updated_at' => $start->copy()->addMinutes(20),
            ]);

            foreach ($answers as $questionId => [$answer, $isCorrect, $ansScore]) {
                DB::table('exam_record_answers')->insert([
                    'exam_record_id' => $recordId,
                    'question_id' => $questionId,
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                    'score' => $ansScore,
                    'created_at' => $start,
                    'updated_at' => $start,
                ]);
            }
        }
        $this->line('  added 7 graded exam records with answers (demo weakness data)');
    }
}
