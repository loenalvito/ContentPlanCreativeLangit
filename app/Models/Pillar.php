<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class Pillar extends Model{protected $guarded=[];protected function casts():array{return ['is_active'=>'boolean'];}public function series(){return $this->hasMany(Series::class);}}
