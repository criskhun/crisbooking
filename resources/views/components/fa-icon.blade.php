@props(['name', 'style' => 'solid'])
<i {{ $attributes->class(["fa-{$style}", "fa-{$name}"]) }} aria-hidden="true"></i>
