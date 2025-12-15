<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lugar extends Model
{
    protected $table = 'lugares';
    
    protected $fillable = [
        'categoria_id','titulo','descripcion','lng','lat',
        'direccion','localidad','orden','activo'
    ];

    public function categoria(){ return $this->belongsTo(Categoria::class); }
    public function imagenes(){ return $this->hasMany(LugarImagen::class)->orderBy('orden'); }
}
