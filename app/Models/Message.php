<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['chat_id', 'text', 'sender', 'slug', '1', '2', '3', '4', '5'];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
