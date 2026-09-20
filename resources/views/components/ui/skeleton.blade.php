@props(['class' => '', 'width' => null, 'height' => null])

<span
    class="ui-skeleton {{ $class }}"
    @if($width || $height) style="{{ $width ? 'width:'.$width.';' : '' }}{{ $height ? 'height:'.$height.';' : '' }}" @endif
    aria-hidden="true"
></span>
