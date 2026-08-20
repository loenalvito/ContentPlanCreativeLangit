@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-bold">Published Library</h1>
<p class="text-xs text-slate-500 mb-5">Arsip semua konten yang sudah dipublish</p>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    @forelse($contents as $content)
        <article class="panel overflow-hidden">
            <div class="h-32 bg-gradient-to-br from-slate-800 to-emerald-900 grid place-items-center text-white text-3xl">▶</div>
            <div class="p-4">
                <a class="font-bold text-sm" href="{{ route('content.show',$content) }}">{{ $content->title }}</a>
                <p class="text-xs text-slate-400 mt-2">{{ $content->publish_date?->format('d M Y') }} · {{ $content->pillar->name }}</p>
                <p class="text-xs text-slate-400">{{ $content->platforms->pluck('name')->join(', ') }} · {{ $content->pic?->name }}</p>
                @if($content->is_not_for_public)
                    <span class="text-xs text-slate-500 block mt-3">Not for Public</span>
                @elseif($content->final_url)
                    <a class="text-xs text-blue-600 block mt-3" target="_blank" rel="noopener" href="{{ $content->final_url }}">Open Post ↗</a>
                @else
                    <span class="text-xs text-amber-600 block mt-3">Link not recorded</span>
                @endif
            </div>
        </article>
    @empty
        <p class="text-slate-400">Belum ada published content.</p>
    @endforelse
</div>
<div class="mt-4">{{ $contents->links() }}</div>
@endsection
