@props(['status'])

<span {{ $attributes->class(['badge', 'text-bg-'.$status->badgeClass()]) }}>{{ $status->value }}</span>
