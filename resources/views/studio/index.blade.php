@extends('studio.layout')

@section('title', 'Your uploads')

@section('content')
    <div class="studio-toolbar">
        <div>
            <p class="studio-kicker">Your channel</p>
            <h1 class="font-display studio-title">Uploads</h1>
            <p class="studio-copy">{{ $channel->name ?? 'My Channel' }}</p>
        </div>
        <a class="btn btn-brand" href="{{ route('studio.upload') }}">Upload video</a>
    </div>

    @if ($videos->isEmpty())
        <div class="studio-empty studio-card">
            <h2>No uploads yet</h2>
            <p>Publish a video or short from the web. It will show in the AMOSHWZ app too.</p>
            <a class="btn btn-brand" href="{{ route('studio.upload') }}">Upload your first video</a>
        </div>
    @else
        <div class="studio-grid">
            @foreach ($videos as $video)
                <article class="studio-card studio-item">
                    <div class="studio-thumb" style="@if($video->thumbnail) background-image: url('{{ $video->thumbnail }}'); @endif">
                        <span>{{ $video->duration }}</span>
                    </div>
                    <div class="studio-item-body">
                        <h2>{{ $video->title }}</h2>
                        <p>
                            {{ $video->platform === 'shorts' ? 'Short' : 'Video' }}
                            · {{ $video->visibility }}
                            · {{ $video->views }}
                        </p>
                        <p class="studio-meta">{{ $video->created }}</p>
                        <form method="POST" action="{{ route('studio.videos.destroy', $video->id) }}" onsubmit="return confirm('Delete this upload?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="studio-delete">Delete</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
        @if ($videos->hasPages())
            <div class="studio-pager">
                @if ($videos->previousPageUrl())
                    <a class="btn btn-ghost" href="{{ $videos->previousPageUrl() }}">Previous</a>
                @endif
                @if ($videos->nextPageUrl())
                    <a class="btn btn-ghost" href="{{ $videos->nextPageUrl() }}">Next</a>
                @endif
            </div>
        @endif
    @endif
@endsection
