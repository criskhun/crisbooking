@extends('layouts.app')

@section('title', 'ID document — Davao Rent Zone')
@section('body-class', 'document-viewer-body')

@section('content')
    <main class="document-viewer-shell">
        <header class="document-viewer-header">
            <a class="document-viewer-back" href="{{ $backUrl }}">← {{ $backLabel }}</a>
            <div>
                <span>Private verification document</span>
                <strong>{{ $profileUser->name }}</strong>
            </div>
        </header>

        <section class="document-viewer-stage" aria-label="Government-issued ID document">
            @if ($isPdf)
                <object data="{{ $documentUrl }}" type="application/pdf">
                    <p>PDF preview unavailable. <a href="{{ $documentUrl }}">Open the file</a>.</p>
                </object>
            @else
                <img src="{{ $documentUrl }}" alt="Government-issued ID document for {{ $profileUser->name }}">
            @endif
        </section>
    </main>
@endsection
