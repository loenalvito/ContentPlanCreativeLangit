@extends('layouts.app')
@section('content')
<div x-data="{open:false,edit:null}">
 <div class="flex justify-between"><div><h1 class="page-title">Users</h1><p class="page-subtitle">Accounts, roles, access status, and safe lifecycle management.</p></div>@can('users.create')<button data-testid="add-user" @click="open=true" class="btn btn-blue">＋ Add User</button>@endcan</div>
 <div class="panel mt-5 overflow-x-auto"><table class="table w-full"><thead><tr><th>User</th><th>Department</th><th>Role</th><th>Status</th><th>Last Login</th><th>Action</th></tr></thead><tbody>
 @foreach($users as $u)
  @php($protected=$u->hasRole('Super Admin'))
  <tr data-testid="user-row"><td><b>{{ $u->name }}</b><span class="block text-xs text-slate-500">{{ $u->email }}</span></td><td>{{ $u->department?->name }}</td><td>{{ $u->roles->pluck('name')->join(', ') }}</td><td>
   @if($u->trashed())<span class="badge bg-slate-100">Deleted</span>
   @else<span class="sr-only">{{ $u->is_active?'Active':'Inactive' }}</span><form method="post" action="{{ route('users.toggle',$u) }}">@csrf @method('PATCH')<button data-testid="toggle-user" @disabled($protected) title="{{ $protected?'Super Admin cannot be deactivated.':'' }}" class="relative h-6 w-11 rounded-full transition {{ $u->is_active?'bg-green-500':'bg-red-500' }} disabled:opacity-40"><span class="absolute top-1 h-4 w-4 rounded-full bg-white transition-all {{ $u->is_active?'right-1':'left-1' }}"></span></button></form>
   @endif
  </td><td>{{ $u->last_login_at?->diffForHumans()??'Never' }}</td><td>
   @unless($u->trashed())<button data-testid="edit-user" @click="edit={{ $u->id }}" class="text-xs text-blue-600">Edit</button>
    @can('users.delete')<form class="inline" method="post" action="{{ route('users.delete',$u) }}" onsubmit="return confirm('Delete this user safely?')">@csrf @method('DELETE')<button @disabled($protected) class="ml-2 text-xs text-red-600 disabled:opacity-30">Delete</button></form>@endcan
   @endunless
  </td></tr>
  @unless($u->trashed())
  <tr x-cloak x-show="edit==={{ $u->id }}"><td colspan="6"><div class="grid gap-4 p-4 md:grid-cols-2">
   <form method="post" action="{{ route('users.update',$u) }}" class="grid gap-2">@csrf @method('PUT')<h3 class="font-bold">Edit User</h3><input class="field" name="name" value="{{ $u->name }}" required><input class="field" type="email" name="email" value="{{ $u->email }}" required><select class="field" name="department_id">@foreach($departments as $d)<option value="{{ $d->id }}" @selected($u->department_id===$d->id)>{{ $d->name }}</option>@endforeach</select><select class="field" name="role">@foreach($roles as $role)<option @selected($u->hasRole($role))>{{ $role->name }}</option>@endforeach</select><button class="btn btn-blue">Save User</button></form>
   @can('users.change_password')<form method="post" action="{{ route('users.password',$u) }}" class="grid content-start gap-2">@csrf @method('PUT')<h3 class="font-bold">Change Password</h3><input class="field" type="password" name="password" placeholder="New Password" required><input class="field" type="password" name="password_confirmation" placeholder="Confirm New Password" required><button class="btn">Change Password</button></form>@endcan
  </div></td></tr>
  @endunless
 @endforeach
 </tbody></table></div>{{ $users->links() }}
 <div data-testid="user-modal" x-cloak x-show="open" @click.self="open=false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"><form method="post" action="{{ route('users.store') }}" class="relative w-full max-w-lg rounded-xl bg-white p-6">@csrf<button type="button" @click="open=false" class="absolute right-5 top-4">✕</button><h2 class="font-bold">Add User</h2><div class="mt-4 grid gap-3"><input data-testid="user-name" class="field" name="name" placeholder="Name" required><input data-testid="user-email" class="field" type="email" name="email" placeholder="Email" required><input data-testid="user-password" class="field" type="password" name="password" placeholder="Password" required><select data-testid="user-department" class="field" name="department_id">@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select><select data-testid="user-role" class="field" name="role">@foreach($roles->where('is_active',true) as $role)<option>{{ $role->name }}</option>@endforeach</select></div><div class="mt-5 text-right"><button type="button" @click="open=false" class="btn">Cancel</button><button data-testid="user-submit" class="btn btn-blue">Create User</button></div></form></div>
</div>
@endsection
