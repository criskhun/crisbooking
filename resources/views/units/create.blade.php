@extends('layouts.app')

@section('title', 'Register unit or service — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div><a class="back-link" href="{{ route('units.index') }}">← Back to listings</a><h1>Register a unit or service</h1></div>
                @include('partials.user-badge')
            </header>
            @if (session('status'))<div class="flash-message account-alert" role="status">{{ session('status') }}</div>@endif
            @error('draft')<div class="oauth-error account-alert" role="alert">{{ $message }}</div>@enderror
            @if ($drafts->isNotEmpty())
                <section class="unit-drafts-panel" aria-labelledby="unit-drafts-title">
                    <div><span class="eyebrow">Saved work</span><h2 id="unit-drafts-title">Your listing drafts</h2><p>Open a draft to continue. Photos are not stored in drafts and must be selected again.</p></div>
                    <div class="unit-draft-list">
                        @foreach ($drafts as $savedDraft)
                            <article @class(['unit-draft-card', 'active' => $draft?->is($savedDraft)])>
                                <a href="{{ route('units.create', ['draft' => $savedDraft]) }}"><strong>{{ $savedDraft->title }}</strong><small>Saved {{ $savedDraft->updated_at->diffForHumans() }}</small></a>
                                <form method="POST" action="{{ route('unit-drafts.destroy', $savedDraft) }}" onsubmit="return confirm('Delete this listing draft?')">@csrf @method('DELETE')<button type="submit" aria-label="Delete {{ $savedDraft->title }} draft">Delete</button></form>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
            <section class="listing-form-card">
                <div class="form-card-heading"><span class="eyebrow">{{ $draft ? 'Continuing draft' : 'New listing' }}</span><h2>What can clients book?</h2><p>Add the details used to calculate availability and booking totals.</p><small class="draft-save-status" data-draft-save-status aria-live="polite">{{ $draft ? 'Draft loaded. Changes save automatically.' : 'Your progress saves automatically after you begin typing.' }}</small></div>
                <form method="POST" action="{{ route('units.store') }}" enctype="multipart/form-data" class="account-edit-form" data-unit-draft-form data-draft-save-url="{{ route('unit-drafts.store') }}" data-draft-delete-base-url="{{ url('/unit-drafts') }}" data-draft-id="{{ $draft?->id }}">
                    @csrf
                    <input type="hidden" name="draft_id" value="{{ $draft?->id }}" data-draft-id-input>
                    @include('units._form')
                </form>
            </section>
        </main>
    </div>
    <dialog class="draft-leave-dialog" data-draft-leave-dialog>
        <form method="dialog">
            <span class="eyebrow">Unsaved listing</span>
            <h2>Save your work before leaving?</h2>
            <p>You entered listing details. Save them as a draft so you can continue later, or discard the draft and leave.</p>
            <div class="draft-leave-actions">
                <button class="button button-primary" type="button" data-draft-leave-save>Save draft &amp; leave</button>
                <button class="button button-danger" type="button" data-draft-leave-discard>Discard &amp; leave</button>
                <button class="button button-ghost" type="button" data-draft-leave-cancel>Keep editing</button>
            </div>
        </form>
    </dialog>
@endsection
