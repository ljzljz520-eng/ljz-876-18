<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 课后练习作答记录。
 * 重要：该表与正式考试（exam_records / exam_record_answers）完全隔离，
 * 仅用于学习反馈与练习推荐，任何情况下都不计入正式成绩。
 */
class PracticeAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'question_id',
        'answer',
        'is_correct',
        'source',
        'related_question_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'question_id' => 'integer',
        'related_question_id' => 'integer',
        'is_correct' => 'boolean',
    ];

    public const SOURCE_WRONG = 'wrong_question';      // 来自真实错题
    public const SOURCE_SIMILAR = 'similar_question';  // 来自相近题

    public const SOURCES = [
        self::SOURCE_WRONG => '真实错题',
        self::SOURCE_SIMILAR => '相近题',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
