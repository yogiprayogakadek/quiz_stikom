@props(['mode'])

<span {{ $attributes->merge(['class' => 'pill '.($mode === 'serempak' ? 'pill-sun' : 'pill-brand')]) }}>
    {{ $mode === 'serempak' ? 'Serempak' : 'Acak' }}
</span>
