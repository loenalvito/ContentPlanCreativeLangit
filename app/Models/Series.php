<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class Series extends Model{protected $table='series';protected $guarded=[];public function pillar(){return $this->belongsTo(Pillar::class);}}
