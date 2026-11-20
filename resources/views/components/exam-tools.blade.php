@props(['tools' => []])

@php
    $alat = \App\Support\ExamTools::normalize($tools);
@endphp

@if ($alat !== [])
    <div x-data="examToolsPanel(@js($alat))" class="contents">
        <button
            type="button"
            x-ref="openButton"
            x-on:click="open()"
            class="btn btn-secondary min-h-11"
            aria-haspopup="dialog"
        >
            <x-icon name="calculator" :size="18" />
            Alat bantu
        </button>

        <dialog
            x-ref="dialog"
            x-on:cancel.prevent="close()"
            class="m-auto max-h-[calc(100dvh-2rem)] w-[min(94vw,38rem)] overflow-y-auto rounded-2xl border border-outline bg-page p-0 text-ink shadow-2xl backdrop:bg-slate-950/60"
            aria-labelledby="exam-tools-title"
        >
            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="exam-tools-title" class="font-display text-2xl font-extrabold">Alat bantu</h2>
                        <p class="mt-1 text-sm text-ink-muted">Perhitungan berlangsung di perangkat dan tidak mengubah jawaban.</p>
                    </div>
                    <button type="button" x-ref="closeButton" x-on:click="close()" class="btn btn-ghost min-h-11 px-3" aria-label="Tutup alat bantu">Tutup</button>
                </div>

                @if (count($alat) > 1)
                    <div class="mt-5 grid grid-cols-2 gap-2" role="tablist" aria-label="Pilih alat bantu">
                        @if (in_array('calculator', $alat, true))
                            <button type="button" role="tab" class="btn min-h-11" x-bind:class="activeTool === 'calculator' ? 'btn-primary' : 'btn-secondary'" x-bind:aria-selected="activeTool === 'calculator'" x-on:click="activeTool = 'calculator'">Kalkulator</button>
                        @endif
                        @if (in_array('subnet_calculator', $alat, true))
                            <button type="button" role="tab" class="btn min-h-11" x-bind:class="activeTool === 'subnet_calculator' ? 'btn-primary' : 'btn-secondary'" x-bind:aria-selected="activeTool === 'subnet_calculator'" x-on:click="activeTool = 'subnet_calculator'">Subnet IPv4</button>
                        @endif
                    </div>
                @endif

                @if (in_array('calculator', $alat, true))
                    <section x-show="activeTool === 'calculator'" class="mt-5" aria-label="Kalkulator standar">
                        <label for="exam-calculator-expression" class="label">Ekspresi</label>
                        <input id="exam-calculator-expression" type="text" x-model="expression" maxlength="200" inputmode="decimal" class="input font-mono text-lg" placeholder="Contoh: (12 + 8) × 5">
                        <div class="mt-2 min-h-8 rounded-xl bg-surface px-3 py-2 font-mono text-lg" aria-live="polite">
                            <span x-show="calculatorResult !== ''" x-text="'= ' + calculatorResult"></span>
                            <span x-show="calculatorError" class="text-coral-deep" x-text="calculatorError"></span>
                        </div>

                        <div class="mt-3 grid grid-cols-5 gap-2">
                            @foreach ([
                                ['C', 'clearCalculator()'], ['⌫', 'backspace()'], ['(', "append('(')"], [')', "append(')')"], ['÷', "append('÷')"],
                                ['7', "append('7')"], ['8', "append('8')"], ['9', "append('9')"], ['%', "append('%')"], ['×', "append('×')"],
                                ['4', "append('4')"], ['5', "append('5')"], ['6', "append('6')"], ['√', "append('√(')"], ['−', "append('-')"],
                                ['1', "append('1')"], ['2', "append('2')"], ['3', "append('3')"], ['2ˣ', "append('2^(')"], ['+', "append('+')"],
                                ['0', "append('0')"], ['.', "append('.')"],
                            ] as [$label, $action])
                                <button type="button" class="btn btn-secondary min-h-11 px-2 font-mono" x-on:click="{{ $action }}">{{ $label }}</button>
                            @endforeach
                            <button type="button" class="btn btn-primary col-span-3 min-h-11" x-on:click="evaluateCalculator()">Hitung</button>
                        </div>
                    </section>
                @endif

                @if (in_array('subnet_calculator', $alat, true))
                    <section x-show="activeTool === 'subnet_calculator'" class="mt-5" aria-label="Kalkulator subnet IPv4">
                        <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                            <div>
                                <label for="exam-subnet-ip" class="label">Alamat IPv4</label>
                                <input id="exam-subnet-ip" type="text" x-model="subnetIp" class="input font-mono" placeholder="192.168.10.0" inputmode="decimal">
                            </div>
                            <div>
                                <label for="exam-subnet-prefix" class="label">Prefix</label>
                                <div class="flex items-center gap-2"><span class="font-bold">/</span><input id="exam-subnet-prefix" type="number" min="0" max="32" x-model="subnetPrefix" class="input font-mono"></div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary mt-4 min-h-11 w-full" x-on:click="calculateSubnet()">Hitung subnet</button>
                        <p x-show="subnetError" x-text="subnetError" class="field-error mt-3" aria-live="polite"></p>

                        <div x-show="subnetResult" x-cloak class="mt-4 overflow-x-auto rounded-xl border border-outline">
                            <table class="w-full min-w-[28rem] text-sm">
                                <tbody class="divide-y divide-outline">
                                    <template x-for="row in [
                                        ['Subnet mask', subnetResult?.subnetMask],
                                        ['Network address', subnetResult?.networkAddress],
                                        ['Broadcast address', subnetResult?.broadcastAddress],
                                        ['First host', subnetResult?.firstHost],
                                        ['Last host', subnetResult?.lastHost],
                                        ['Jumlah alamat', subnetResult?.totalAddresses],
                                        ['Host usable', subnetResult?.usableHosts],
                                        ['Ukuran blok', subnetResult ? subnetResult.blockSize + ' (oktet ke-' + subnetResult.relevantOctet + ')' : ''],
                                    ]" :key="row[0]">
                                        <tr><th scope="row" class="px-3 py-2 text-left font-bold text-ink-muted" x-text="row[0]"></th><td class="px-3 py-2 font-mono" x-text="row[1]"></td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <p x-show="subnetResult" x-text="subnetResult?.note" class="mt-3 text-sm leading-relaxed text-ink-muted"></p>
                    </section>
                @endif
            </div>
        </dialog>
    </div>
@endif
