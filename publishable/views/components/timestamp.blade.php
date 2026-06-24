@if($value)
    <time datetime="{{ $value->toIso8601String() }}" data-ts="{{ $value->toIso8601String() }}" title="{{ $value->format('Y-m-d H:i:s') }}">{{ $value->format('Y-m-d H:i:s') }}</time>
@else
    <span class="text-ink-soft">·</span>
@endif
