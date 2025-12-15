<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LugarImagen extends Model
{
    protected $fillable = ['lugar_id','path','titulo','orden'];
    public function lugar(){ return $this->belongsTo(Lugar::class); }
}

