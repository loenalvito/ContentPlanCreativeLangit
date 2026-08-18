<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class Asset extends Model { use SoftDeletes; protected $guarded=[]; public function addedBy(){return $this->belongsTo(User::class,'added_by');} }
