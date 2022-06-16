<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Numero extends Model
{
    use HasFactory;
    protected $fillable = [
        'num',
    ];
    public function User(){
        return $this->hasMany(User::class);
    }
}
