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
            <section class="listing-form-card">
                <div class="form-card-heading"><span class="eyebrow">New listing</span><h2>What can clients book?</h2><p>Add the details used to calculate availability and booking totals.</p></div>
                <form method="POST" action="{{ route('units.store') }}" enctype="multipart/form-data" class="account-edit-form">
                    @csrf
                    @include('units._form')
                </form>
            </section>
        </main>
    </div>
@endsection
