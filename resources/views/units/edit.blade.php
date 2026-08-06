@extends('layouts.app')

@section('title', 'Edit '.$unit->name.' — MyBooking')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div><a class="back-link" href="{{ route('units.index') }}">← Back to listings</a><h1>Edit listing</h1></div>
                @include('partials.user-badge')
            </header>
            <section class="listing-form-card">
                <div class="form-card-heading"><span class="eyebrow">Listing #{{ $unit->id }}</span><h2>{{ $unit->name }}</h2><p>Changes to availability apply immediately.</p></div>
                <form method="POST" action="{{ route('units.update', $unit) }}" enctype="multipart/form-data" class="account-edit-form">
                    @csrf
                    @method('PUT')
                    @include('units._form')
                </form>
            </section>
        </main>
    </div>
@endsection
