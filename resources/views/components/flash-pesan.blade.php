@if (session('sukses') || $errors->any())
    <div {{ $attributes->merge(['class' => 'space-y-3']) }}>
        @if (session('sukses'))
            <x-alert jenis="success">{{ session('sukses') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert jenis="error">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $pesan)
                        <li>{{ $pesan }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif
    </div>
@endif
