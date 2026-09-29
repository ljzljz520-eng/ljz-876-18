<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticeAttempt extends Model
{
    use HasFactory;

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
        'is_correct' => 'boolean',
        'related_question_id' => 'integer',
    ];

    public const SOURCE_WRONG_QUESTION = 'wrong_question';
    public const SOURCE_SIMILAR = 'similar';
    public const SOURCE_MANUAL = 'manual';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
