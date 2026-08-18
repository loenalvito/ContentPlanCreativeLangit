<?php
namespace Database\Seeders;

use App\Models\{Account,Content,ContentBrief,Department,Format,Idea,Pillar,Platform,Series,User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Permission,Role};

class DatabaseSeeder extends Seeder
{
 public function run():void
 {
  $departments=collect(['Creative','Sales','HR','Finance','Product','Management','Other'])->mapWithKeys(fn($n)=>[$n=>Department::create(['name'=>$n,'slug'=>Str::slug($n)])]);
  $tree=['Product'=>['Kolabo Daily Use','Kolabo Features'],'News'=>['Insider Update'],'Insight / Education'=>['Tips','Business 101','Trivia'],'Entertainment'=>['Office Life','Meme','POV'],'Community'=>['KolaboUpNext'],'Brand'=>['Inside Kolabo','Team BTS']];
  foreach($tree as $p=>$items){$pillar=Pillar::create(['name'=>$p,'slug'=>Str::slug($p)]);foreach($items as $s)Series::create(['pillar_id'=>$pillar->id,'name'=>$s,'slug'=>Str::slug($s)]);}
  foreach(['Kolabo.id','Kolabo Insider'] as $n)Account::create(['name'=>$n,'slug'=>Str::slug($n)]);
  foreach(['Instagram','TikTok','Threads','LinkedIn','YouTube'] as $n)Platform::create(['name'=>$n,'slug'=>Str::slug($n)]);
  foreach(['Reels','Carousel','Single Post','Story','Threads','Short Video','Long Video'] as $n)Format::create(['name'=>$n,'slug'=>Str::slug($n)]);
  $permissions=['dashboard.view','content.view','content.create','content.edit','content.delete','content.change_status','ideas.view_all','ideas.view_own','ideas.create','ideas.edit_own','ideas.edit_all','ideas.delete','ideas.select','ideas.change_status','ideas.move_to_content','ideas.convert','ideas.bulk_import','calendar.view','calendar.edit','calendar.reschedule','production.view','production.change_status','published.view','assets.view','assets.create','assets.delete','comments.create','comments.edit_own','comments.delete_own','comments.manage','team.view','users.view','users.create','users.edit','users.deactivate','roles.view','roles.create','roles.edit','roles.delete','pillars.view','pillars.manage','series.manage','formats.manage'];
  foreach($permissions as $p)Permission::create(['name'=>$p]);
  $super=Role::create(['name'=>'Super Admin','description'=>'Protected administrator role']);$super->givePermissionTo($permissions);
  $lead=Role::create(['name'=>'Creative Lead','description'=>'Leads the creative workflow']);$lead->givePermissionTo(array_values(array_filter($permissions,fn($p)=>!str_starts_with($p,'roles.')&&!str_contains($p,'.manage')&&!str_starts_with($p,'pillars.'))));
  $member=Role::create(['name'=>'Creative Member']);$member->givePermissionTo(['dashboard.view','content.view','content.create','content.edit','content.change_status','ideas.view_all','ideas.create','ideas.edit_all','ideas.select','ideas.change_status','ideas.move_to_content','ideas.convert','calendar.view','calendar.edit','production.view','production.change_status','published.view','assets.view','assets.create','comments.create']);
  $sales=Role::create(['name'=>'Sales Contributor']);$sales->givePermissionTo(['ideas.view_own','ideas.create','ideas.bulk_import','calendar.view']);
  $viewer=Role::create(['name'=>'Viewer']);$viewer->givePermissionTo(['dashboard.view','content.view','calendar.view','published.view']);
  $defs=[['Rayhan Admin','admin@kolabo.id','Creative','Super Admin'],['Dina Lead','lead@kolabo.id','Creative','Creative Lead'],['Fadly Creative','fadly@kolabo.id','Creative','Creative Member'],['Nabila Creative','nabila@kolabo.id','Creative','Creative Member'],['Andi Sales','sales@kolabo.id','Sales','Sales Contributor']];$users=[];
  foreach($defs as [$n,$e,$d,$r]){$u=User::create(['name'=>$n,'email'=>$e,'password'=>Hash::make('password'),'department_id'=>$departments[$d]->id,'is_active'=>true]);$u->assignRole($r);$users[]=$u;}
  $titles=['Kolabo Daily Use — Finance','Insider Update — AI Rp66 Triliun','Office Life — Meeting Be Like','Business 101 — Cash Flow','Kolabo Features — Approval','Tips — Cara Kelola Stok','POV — Admin Banyak Kerjaan','KolaboUpNext — Teaser','Inside Kolabo — Office Culture','Team BTS — Shooting Day'];$statuses=['scheduled','review','in_production','approved','scheduled','in_production','planned','scheduled','published','published'];
  foreach($titles as $i=>$title){$series=Series::where('name',explode(' — ',$title)[0])->first()??Series::inRandomOrder()->first();$c=Content::create(['title'=>$title,'publish_date'=>today()->addDays($i-2),'account_id'=>Account::first()->id,'pillar_id'=>$series->pillar_id,'series_id'=>$series->id,'format_id'=>Format::inRandomOrder()->first()->id,'pic_user_id'=>$users[2+($i%2)]->id,'status'=>$statuses[$i],'final_url'=>$statuses[$i]==='published'?'https://instagram.com/kolabo.id':null,'created_by'=>$users[0]->id]);$c->platforms()->sync([Platform::inRandomOrder()->first()->id]);ContentBrief::create(['content_id'=>$c->id,'hook'=>'Buka dengan pertanyaan yang relevan untuk audiens.','angle'=>'Praktis, ringkas, dan mudah diterapkan.','key_message'=>'Kolabo membantu tim bekerja lebih rapi.','cta'=>'Simpan dan bagikan posting ini.']);}
  Idea::create(['idea'=>'Kenapa banyak lead berhenti setelah quotation?','pillar_id'=>Pillar::where('name','Insight / Education')->first()->id,'series_id'=>Series::where('name','Business 101')->first()->id,'format_id'=>Format::where('name','Reels')->first()->id,'submitted_by'=>$users[4]->id,'source_department_id'=>$departments['Sales']->id,'status'=>'new']);
 }
}
