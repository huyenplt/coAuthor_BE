<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoAuthor2 extends Model
{
    use HasFactory;

    protected $table = 'coauthor2';

    protected $fillable = [
        'id',
        'author_id1',
        'author_id2',
        'paper_year'
    ];
}
