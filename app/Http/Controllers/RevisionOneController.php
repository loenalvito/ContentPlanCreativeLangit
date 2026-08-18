<?php
namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\{Asset,Content,ContentBrief,ContentComment,Format,Idea,Pillar,Series};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\{Permission,Role};

class RevisionOneController extends Controller
{
    public function updateContentStatus(Request $request, Content $content)
    {
        return app(KolaboController::class)->updateStatus($request, $content);
    }

    public function brief(Request $request, Content $content)
    {
        abort_unless($request->user()->can('content.edit'), 403);
        $data = $request->validate(['hook'=>'nullable|string','angle'=>'nullable|string','key_message'=>'nullable|string','cta'=>'nullable|string','notes'=>'nullable|string','main_copy'=>'nullable|string','reels_script'=>'nullable|string','carousel_copy'=>'nullable|string','caption'=>'nullable|string','threads_copy'=>'nullable|string']);
        ContentBrief::updateOrCreate(['content_id'=>$content->id], $data);
        return back()->with('success', 'Content detail saved successfully.');
    }

    public function asset(Request $request, Content $content)
    {
        abort_unless($request->user()->can('assets.create'), 403);
        $data=$request->validate(['title'=>'required|max:255','asset_type'=>'required|max:100','url'=>'required|url','notes'=>'nullable|string']);
        Asset::create($data+['content_id'=>$content->id,'category'=>'Content Reference','added_by'=>$request->user()->id]);
        return back()->with('success','Asset reference added.');
    }

    public function comment(Request $request, Content $content)
    {
        abort_unless($request->user()->can('comments.create'),403);
        $data=$request->validate(['body'=>'required|string|max:5000']);
        $content->comments()->create($data+['user_id'=>$request->user()->id]);
        return back()->with('success','Comment added.');
    }

    public function revision(Request $request, Content $content)
    {
        abort_unless($request->user()->can('production.change_status'),403);
        abort_unless($content->status===ContentStatus::Review,422,'Content must be in Review.');
        $data=$request->validate(['body'=>'nullable|string|max:5000']);
        DB::transaction(function()use($content,$request,$data){
            if(filled($data['body']??null)){$content->comments()->create(['user_id'=>$request->user()->id,'body'=>$data['body']]);$content->revisions()->create(['created_by'=>$request->user()->id,'note'=>$data['body']]);}
            $content->update(['status'=>ContentStatus::InProduction,'updated_by'=>$request->user()->id]);
            activity()->causedBy($request->user())->performedOn($content)->withProperties(['old'=>'Review','new'=>'In Production'])->log('requested revision');
        });
        return back()->with('success','Revision requested; content returned to In Production.');
    }

    public function masters(Request $request)
    {
        abort_unless($request->user()->can('pillars.view'),403);
        return view('admin.masters',['pillars'=>Pillar::with('series')->orderBy('sort_order')->get(),'formats'=>Format::orderBy('sort_order')->get()]);
    }
    public function pillar(Request $request, ?Pillar $pillar=null)
    {
        abort_unless($request->user()->can('pillars.manage'),403);
        $data=$request->validate(['name'=>'required|max:100','sort_order'=>'nullable|integer|min:0']);$data['slug']=Str::slug($data['name']);
        $pillar?->exists?$pillar->update($data):Pillar::create($data);
        return back()->with('success','Pillar saved.');
    }
    public function series(Request $request, ?Series $series=null)
    {
        abort_unless($request->user()->can('series.manage'),403);
        $data=$request->validate(['name'=>'required|max:100','pillar_id'=>'required|exists:pillars,id','sort_order'=>'nullable|integer|min:0']);$data['slug']=Str::slug($data['name']);
        $series?->exists?$series->update($data):Series::create($data);
        return back()->with('success','Series saved.');
    }
    public function format(Request $request, ?Format $format=null)
    {
        abort_unless($request->user()->can('formats.manage'),403);
        $data=$request->validate(['name'=>'required|max:100','sort_order'=>'nullable|integer|min:0']);$data['slug']=Str::slug($data['name']);
        $format?->exists?$format->update($data):Format::create($data);
        return back()->with('success','Format saved.');
    }
    public function toggle(Request $request,string $type,int $id)
    {
        $map=['pillar'=>[Pillar::class,'pillars.manage'],'series'=>[Series::class,'series.manage'],'format'=>[Format::class,'formats.manage']];abort_unless(isset($map[$type]),404);abort_unless($request->user()->can($map[$type][1]),403);$item=$map[$type][0]::findOrFail($id);$item->update(['is_active'=>!$item->is_active]);return back()->with('success','Status updated.');
    }

    public function roles(Request $request)
    {
        abort_unless($request->user()->can('roles.view'),403);
        return view('admin.roles',['roles'=>Role::with('permissions')->get(),'permissions'=>Permission::orderBy('name')->get()->groupBy(fn($p)=>Str::before($p->name,'.'))]);
    }
    public function roleStore(Request $request)
    {
        abort_unless($request->user()->can('roles.create'),403);$data=$request->validate(['name'=>'required|max:100|unique:roles,name','description'=>'nullable|max:1000','permissions'=>'array','permissions.*'=>'exists:permissions,name']);$role=Role::create(['name'=>$data['name'],'description'=>$data['description']??null]);$role->syncPermissions($data['permissions']??[]);return back()->with('success','Role created.');
    }
    public function roleUpdate(Request $request,Role $role)
    {
        abort_unless($request->user()->can('roles.edit'),403);abort_if($role->name==='Super Admin',422,'Super Admin role is protected.');$data=$request->validate(['name'=>['required','max:100',Rule::unique('roles')->ignore($role->id)],'description'=>'nullable|max:1000','permissions'=>'array','permissions.*'=>'exists:permissions,name']);$role->update(['name'=>$data['name'],'description'=>$data['description']??null]);$role->syncPermissions($data['permissions']??[]);return back()->with('success','Role updated.');
    }
}
