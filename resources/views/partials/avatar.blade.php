@php
    $avatarUrl = $avatarUser->avatarUrl();
    $avatarClass = trim(($avatarClass ?? '').' user-avatar');
    $avatarAlt = $avatarAlt ?? $avatarUser->name;
@endphp
<span class="{{ $avatarClass }}" aria-label="{{ $avatarAlt }}">
    @if ($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="{{ $avatarAlt }}" @if(!$avatarUser->profile_image_path) referrerpolicy="no-referrer" @endif>
    @else
        <span aria-hidden="true">{{ strtoupper(substr($avatarUser->name, 0, 1)) }}</span>
    @endif
</span>
