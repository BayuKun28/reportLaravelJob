<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportJob extends Model
{
    use HasFactory;

    protected $fillable = ['filename', 'request_data', 'status', 'output_path', 'error_message'];

    protected $casts = [
        'request_data' => 'array',
    ];
}
