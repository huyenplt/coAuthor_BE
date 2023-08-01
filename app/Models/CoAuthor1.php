<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoAuthor1 extends Model
{
    use HasFactory;

    protected $table = 'coauthor1';

    protected $fillable = [
        'id',
        'author_id1',
        'author_id2',
        'paper_year'
    ];
}
