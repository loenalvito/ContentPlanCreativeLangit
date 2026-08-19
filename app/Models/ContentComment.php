<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentComment extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['resolved_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class)->withTrashed(); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by')->withTrashed(); }
    public function content() { return $this->belongsTo(Content::class); }
}
