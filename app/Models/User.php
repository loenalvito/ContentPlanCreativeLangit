<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;
    protected $fillable=['name','email','password','department_id','is_active','profile_photo_path','last_login_at'];
    protected $hidden=['password','remember_token'];
    protected function casts():array{return ['password'=>'hashed','is_active'=>'boolean','last_login_at'=>'datetime'];}
    public function department(){return $this->belongsTo(Department::class);}
    public function assignedContents(){return $this->hasMany(Content::class,'pic_user_id');}
    public function ideas(){return $this->hasMany(Idea::class,'submitted_by');}
}
