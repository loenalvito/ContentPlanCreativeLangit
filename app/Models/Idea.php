<?php
namespace App\Models;
use App\Enums\IdeaStatus; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class Idea extends Model { use SoftDeletes; protected $guarded=[]; protected function casts():array{return ['status'=>IdeaStatus::class];} public function pillar(){return $this->belongsTo(Pillar::class);} public function series(){return $this->belongsTo(Series::class);} public function format(){return $this->belongsTo(Format::class);} public function submitter(){return $this->belongsTo(User::class,'submitted_by');} public function department(){return $this->belongsTo(Department::class,'source_department_id');} public function content(){return $this->hasOne(Content::class,'source_idea_id');} }
