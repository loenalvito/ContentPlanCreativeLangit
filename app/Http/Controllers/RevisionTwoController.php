<?php
namespace App\Http\Controllers;

use App\Enums\IdeaStatus;
use App\Models\{Account,Asset,Content,ContentBrief,Department,Format,Idea,IdeaAsset,IdeaBrief,Pillar,Platform,User};
use App\Services\SalesDashboardData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RevisionTwoController extends Controller
{
    private function ideaPayload(Request $request): array
    {
        return $request->validate([
            'idea'=>'required|string|max:5000','pillar_id'=>'required|exists:pillars,id',
            'series_id'=>['required',Rule::exists('series','id')->where(fn($q)=>$q->where('pillar_id',$request->pillar_id))],
            'format_id'=>'nullable|exists:formats,id','platform_ids'=>'required|array|min:1',
            'platform_ids.*'=>Rule::exists('platforms','id')->where('is_active',true),
            'idea_hook'=>'nullable|string|max:5000','idea_angle'=>'nullable|string|max:5000',
            'idea_key_message'=>'nullable|string|max:5000','idea_cta'=>'nullable|string|max:5000',
            'idea_notes'=>'nullable|string|max:5000','idea_script_copy'=>'nullable|string|max:20000',
            'assets'=>'nullable|array','assets.*.name'=>'required_with:assets.*.url|max:255',
            'assets.*.type'=>'required_with:assets.*.url|max:100','assets.*.url'=>'nullable|url','assets.*.notes'=>'nullable|string|max:5000',
            'is_urgent'=>'nullable|boolean','needed_at'=>'required_if:is_urgent,1|nullable|date','urgent_purpose'=>'required_if:is_urgent,1|nullable|string|max:5000',
        ]);
    }

    public function salesDashboard(Request $request, SalesDashboardData $dashboard)
    {
        abort_unless($request->user()->can('sales_dashboard.view'),403);
        $data=$dashboard->for($request->user());
        $data['members']=$data['creativeWorkloads'];
        return view('sales-dashboard',$this->refs()+$data);
    }

    private function refs():array{return ['pillars'=>Pillar::where('is_active',true)->with(['series'=>fn($q)=>$q->where('is_active',true)])->get(),'platforms'=>Platform::where('is_active',true)->with(['accounts'=>fn($q)=>$q->where('is_active',true)])->get(),'formats'=>Format::where('is_active',true)->get()];}

    public function requestContent(Request $request)
    {
        abort_unless($request->user()->can('content_request.create'),403);$data=$this->ideaPayload($request);
        $idea=DB::transaction(function()use($request,$data){$idea=Idea::create(['idea'=>strip_tags($data['idea']),'pillar_id'=>$data['pillar_id'],'series_id'=>$data['series_id'],'format_id'=>$data['format_id']??null,'submitted_by'=>$request->user()->id,'source_department_id'=>$request->user()->department_id,'status'=>'new','is_content_request'=>true,'is_urgent'=>$request->boolean('is_urgent'),'needed_at'=>$data['needed_at']??null,'urgent_purpose'=>$data['urgent_purpose']??null]);$idea->platforms()->sync($data['platform_ids']);IdeaBrief::create(['idea_id'=>$idea->id,'hook'=>$data['idea_hook']??null,'angle'=>$data['idea_angle']??null,'key_message'=>$data['idea_key_message']??null,'cta'=>$data['idea_cta']??null,'notes'=>$data['idea_notes']??null,'script_copy'=>$data['idea_script_copy']??null]);foreach($data['assets']??[] as $a)if(filled($a['url']??null))IdeaAsset::create(['idea_id'=>$idea->id,'name'=>$a['name'],'type'=>$a['type'],'url'=>$a['url'],'notes'=>$a['notes']??null]);return $idea;});activity()->causedBy($request->user())->performedOn($idea)->log('requested content');return redirect()->route('ideas.show',$idea)->with('success','Content request sent to Ideas Bank.');
    }

    public function showIdea(Request $request,Idea $idea){abort_unless($request->user()->can('ideas.view_all')||($request->user()->can('ideas.view_own')&&$idea->submitted_by===$request->user()->id),403);return view('ideas.show',['idea'=>$idea->load(['platforms','pillar','series','format','brief','assets','submitter.department'])]);}

    public function storeIdea(Request $request)
    {
        abort_unless($request->user()->can('ideas.create'),403);$data=$this->ideaPayload($request);
        $idea=DB::transaction(function()use($request,$data){$idea=Idea::create(['idea'=>strip_tags($data['idea']),'pillar_id'=>$data['pillar_id'],'series_id'=>$data['series_id'],'format_id'=>$data['format_id']??null,'submitted_by'=>$request->user()->id,'source_department_id'=>$request->user()->department_id,'status'=>'new']);$idea->platforms()->sync($data['platform_ids']);IdeaBrief::create(['idea_id'=>$idea->id,'hook'=>$data['idea_hook']??null,'angle'=>$data['idea_angle']??null,'key_message'=>$data['idea_key_message']??null,'cta'=>$data['idea_cta']??null,'notes'=>$data['idea_notes']??null,'script_copy'=>$data['idea_script_copy']??null]);foreach($data['assets']??[] as $a)if(filled($a['url']??null))IdeaAsset::create(['idea_id'=>$idea->id,'name'=>$a['name'],'type'=>$a['type'],'url'=>$a['url'],'notes'=>$a['notes']??null]);return $idea;});activity()->causedBy($request->user())->performedOn($idea)->log('submitted an idea');return back()->with('success','Idea saved.');
    }

    public function bulkMove(Request $request)
    {
        abort_unless($request->user()->can('ideas.bulk_move_to_content')||$request->user()->can('ideas.move_to_content'),403);
        $request->merge(['items'=>collect($request->input('items',[]))->filter(fn($item)=>filled($item['publish_date']??null)&&filled($item['pic_user_id']??null))->values()->all()]);
        $data=$request->validate(['items'=>'required|array|min:1','items.*.idea_id'=>'required|distinct|exists:ideas,id','items.*.publish_date'=>'required|date','items.*.pic_user_id'=>['required',Rule::exists('users','id')->where('is_active',true)],'items.*.accounts'=>'required|array']);
        $ids=collect($data['items'])->pluck('idea_id');$ideas=Idea::with(['platforms','brief','assets'])->whereIn('id',$ids)->lockForUpdate()->get()->keyBy('id');
        foreach($data['items'] as $item){$idea=$ideas[$item['idea_id']]??null;abort_unless($idea&&in_array($idea->status,[IdeaStatus::New,IdeaStatus::Consider],true)&&!$idea->content,422,'Idea is not eligible for conversion.');foreach($idea->platforms as $platform){$accountId=$item['accounts'][$platform->id]??null;$valid=Account::whereKey($accountId)->where('platform_id',$platform->id)->where('is_active',true)->exists();abort_unless($valid,422,"{$platform->name} account is required for \"".strip_tags($idea->idea).'".');}}
        DB::transaction(function()use($data,$ideas,$request){foreach($data['items'] as $item){$idea=$ideas[$item['idea_id']];$firstAccount=collect($item['accounts'])->first();$content=Content::create(['title'=>strip_tags($idea->idea),'publish_date'=>$item['publish_date'],'account_id'=>$firstAccount,'pillar_id'=>$idea->pillar_id,'series_id'=>$idea->series_id,'format_id'=>$idea->format_id,'pic_user_id'=>$item['pic_user_id'],'status'=>'planned','source_idea_id'=>$idea->id,'created_by'=>$request->user()->id,'updated_by'=>$request->user()->id]);$sync=[];foreach($idea->platforms as $platform)$sync[$platform->id]=['account_id'=>$item['accounts'][$platform->id]];$content->platforms()->sync($sync);ContentBrief::create(['content_id'=>$content->id]+($idea->brief?->only(['hook','angle','key_message','cta','notes'])??[])+['main_copy'=>$idea->brief?->script_copy]);foreach($idea->assets as $asset)Asset::create(['content_id'=>$content->id,'title'=>$asset->name,'asset_type'=>$asset->type,'url'=>$asset->url,'notes'=>$asset->notes,'category'=>'Idea Reference','added_by'=>$request->user()->id]);$idea->update(['status'=>'converted']);activity()->causedBy($request->user())->performedOn($content)->log('converted idea to content');}});
        return redirect()->route('content.index')->with('success',count($data['items']).' ideas moved to Content Plan.');
    }

    public function accounts(Request $request){abort_unless($request->user()->can('accounts.view'),403);return view('admin.accounts',['accounts'=>Account::with('platform')->orderBy('platform_id')->paginate(30),'platforms'=>Platform::where('is_active',true)->get()]);}
    public function storeAccount(Request $request){abort_unless($request->user()->can('accounts.create'),403);$d=$this->accountData($request);Account::create($d+['name'=>$d['platform_id'].'-'.$d['username'],'slug'=>Str::slug($d['platform_id'].'-'.$d['username'])]);return back()->with('success','Social account created.');}
    public function updateAccount(Request $request,Account $account){abort_unless($request->user()->can('accounts.edit'),403);$account->update($this->accountData($request));return back()->with('success','Social account updated.');}
    private function accountData(Request $r):array{return $r->validate(['platform_id'=>'required|exists:platforms,id','account_name'=>'required|max:100','username'=>['required','max:100',Rule::unique('accounts')->where(fn($q)=>$q->where('platform_id',$r->platform_id))->ignore($r->route('account'))],'is_active'=>'required|boolean']);}
    public function toggleAccount(Request $r,Account $account){abort_unless($r->user()->can('accounts.change_status'),403);$account->update(['is_active'=>!$account->is_active]);return back()->with('success','Account status updated.');}
    public function deleteAccount(Request $r,Account $account){abort_unless($r->user()->can('accounts.delete'),403);$used=DB::table('content_platform')->where('account_id',$account->id)->exists();$used?$account->update(['is_active'=>false]):$account->delete();return back()->with('success',$used?'Account deactivated to preserve history.':'Account deleted.');}

    public function updateUser(Request $r,User $user){abort_unless($r->user()->can('users.edit'),403);$d=$r->validate(['name'=>'required|max:255','email'=>['required','email',Rule::unique('users')->ignore($user->id)],'department_id'=>'required|exists:departments,id','role'=>'required|exists:roles,name']);$user->update($d);$user->syncRoles($d['role']);return back()->with('success','User updated.');}
    public function password(Request $r,User $user){abort_unless($r->user()->can('users.change_password'),403);$d=$r->validate(['password'=>'required|min:8|confirmed']);$user->update(['password'=>Hash::make($d['password'])]);return back()->with('success','Password changed.');}
    public function deleteUser(Request $r,User $user){abort_unless($r->user()->can('users.delete'),403);abort_if($user->hasRole('Super Admin'),422,'Super Admin cannot be deleted.');$user->update(['is_active'=>false]);$user->delete();return back()->with('success','User deleted safely.');}
    public function deleteRole(Request $r,Role $role){abort_unless($r->user()->can('roles.delete'),403);abort_if($role->name==='Super Admin',422,'Super Admin role cannot be deleted.');$count=$role->users()->count();abort_if($count>0,422,"This role is currently assigned to {$count} users. Reassign those users before deleting this role.");$role->delete();return back()->with('success','Role deleted.');}
}
