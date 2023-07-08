<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoAuthor extends Model
{
    use HasFactory;

    protected $table = 'co_author';

    protected $fillable = [
        'id',
        'author_id1',
        'author_id2',
        'paper_id',
        'paper_year'
    ];
}
