<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'course_id',
        'course_name',
        'prefecture',
        'nickname',
        'rating',
        'comment',
        'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }
}
