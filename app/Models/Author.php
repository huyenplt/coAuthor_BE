<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use HasFactory;
    protected $table = 'authors';
    protected $fillable = ['id', 'name'];

    public function papers()
    {
        return $this->belongsToMany(Paper::class, 'paper_authors');
    }
}
