<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class Pillar extends Model{protected $guarded=[];public function series(){return $this->hasMany(Series::class);}}
