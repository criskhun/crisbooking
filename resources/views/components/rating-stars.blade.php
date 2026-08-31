@props(['rating', 'max' => 5])
<span {{ $attributes->class('fa-rating-stars') }} aria-label="{{ $rating }} out of {{ $max }} stars">
    @foreach(range(1, $max) as $star)
        <x-fa-icon name="star" @class(['is-empty' => $star > (int) $rating]) />
    @endforeach
</span>
