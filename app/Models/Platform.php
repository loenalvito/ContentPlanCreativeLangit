<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class Platform extends Model{protected $guarded=[];public function contents(){return $this->belongsToMany(Content::class);}}
