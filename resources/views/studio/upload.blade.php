@extends('studio.layout')

@section('title', 'Upload')

@section('content')
    <div class="studio-toolbar">
        <div>
            <p class="studio-kicker">Publish</p>
            <h1 class="font-display studio-title">Upload</h1>
            <p class="studio-copy">Videos and shorts go live on your channel and in the app.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('studio.index') }}">Back to uploads</a>
    </div>

    <form id="studio-upload-form" method="POST" action="{{ route('studio.upload.store') }}" enctype="multipart/form-data" class="studio-card studio-form studio-upload-form" data-initiate="{{ route('studio.upload.initiate') }}" data-chunk="{{ route('studio.upload.chunk') }}" data-finalize="{{ route('studio.upload.finalize') }}">
        @csrf
        <div class="studio-kind">
            <label>
                <input type="radio" name="kind" value="video" checked>
                Video
            </label>
            <label>
                <input type="radio" name="kind" value="short">
                Short
            </label>
        </div>
        <label>
            Title
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="255">
        </label>
        <label>
            Description
            <textarea name="description" rows="4" maxlength="2000">{{ old('description') }}</textarea>
        </label>
        <div class="studio-row">
            <label>
                Visibility
                <select name="visibility">
                    <option value="public" selected>Public</option>
                    <option value="unlisted">Unlisted</option>
                    <option value="private">Private</option>
                </select>
            </label>
            <label>
                Genre
                <select name="genre">
                    @foreach ($genres as $genre)
                        <option value="{{ $genre }}" @selected($genre === 'Other')>{{ $genre }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label>
            Video file
            <input id="studio-video" type="file" name="video" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,.mp4,.mov,.webm,.avi" required>
        </label>
        <label>
            Thumbnail <span>(optional)</span>
            <input id="studio-thumb" type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
        </label>
        <div id="studio-progress" class="studio-progress" hidden>
            <div class="studio-progress-bar"><span></span></div>
            <p id="studio-progress-label">Uploading…</p>
        </div>
        <button type="submit" class="btn btn-brand btn-block" id="studio-submit">Upload</button>
    </form>
@endsection

@section('scripts')
    <script src="{{ asset('js/studio-upload.js') }}"></script>
@endsection
