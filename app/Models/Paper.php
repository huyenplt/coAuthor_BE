<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paper extends Model
{
    use HasFactory;
    protected $table = 'papers';
    protected $fillable = ['id', 'year', 'title', 'event_type', 'pdf_name', 'abstract', 'paper_text'];

    public function authors()
    {
        return $this->belongsToMany(Author::class, 'paper_authors');
    }
}
