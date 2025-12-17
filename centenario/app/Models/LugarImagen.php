<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LugarImagen extends Model
{
    protected $table = 'lugar_imagenes';

    protected $fillable = ['lugar_id','path','drive_file_id','titulo','orden'];

    public function lugar(){ 
        return $this->belongsTo(Lugar::class); 
    }
}

