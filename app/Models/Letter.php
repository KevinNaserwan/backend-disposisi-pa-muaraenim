<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Letter extends Model
{
    use HasFactory;

    protected $fillable = [
        'agenda_number',
        'letter_number',
        'origin',
        'letter_date',
        'received_date',
        'type',
        'classification',
        'subject',
        'file_path',
        'status',
        'category',
    ];

    protected $casts = [
        'letter_date' => 'date',
        'received_date' => 'date',
    ];

    public function dispositions()
    {
        return $this->hasMany(Disposition::class);
    }
}
