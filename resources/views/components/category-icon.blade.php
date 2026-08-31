@props(['category'])
@php
    $icon = [
        'car' => 'car-side',
        'condo' => 'building',
        'cleaning' => 'broom',
        'laundry' => 'shirt',
        'delivery' => 'truck-fast',
        'car_wash' => 'soap',
        'vehicle_maintenance' => 'screwdriver-wrench',
        'driving' => 'road',
        'massage' => 'spa',
        'consultancy' => 'briefcase',
        'pet_transport' => 'paw',
        'other' => 'shapes',
    ][$category] ?? 'shapes';
@endphp
<x-fa-icon :name="$icon" {{ $attributes }} />
