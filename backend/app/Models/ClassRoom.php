<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassRoom extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'name',
        'teacher_id',
        'description',
        'status',
    ];

    protected $casts = [
        'teacher_id' => 'integer',
        'status' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'class_student', 'class_id', 'user_id')
            ->withTimestamps();
    }
}
