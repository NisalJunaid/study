<article class="card">
    <p class="muted mb-0 text-sm">{{ $title }}</p>
    <h3 class="h1">{{ $value }}</h3>
    @if(!empty($subtitle))
        <p class="muted mb-0 text-sm">{{ $subtitle }}</p>
    @endif
</article>
