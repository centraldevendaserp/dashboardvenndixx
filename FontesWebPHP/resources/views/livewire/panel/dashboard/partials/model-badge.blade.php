{{-- Badge de MODELO com cor própria por tipo (NF-e/NFC-e/CT-e/MDF-e/Entrada),
     para identificar de relance quem é quem nas tabelas. Espera $model. --}}
@php
    $modelMap = [
        55 => ['NF-e', 'mc-nfe'],
        65 => ['NFC-e', 'mc-nfce'],
        57 => ['CT-e', 'mc-cte'],
        67 => ['CT-e OS', 'mc-cteos'],
        58 => ['MDF-e', 'mc-mdfe'],
        59 => ['Entrada', 'mc-entr'],
    ];
    [$mLabel, $mClass] = $modelMap[(int) $model] ?? [null, null];
@endphp
@if ($mLabel)
    <span class="badge badge-model {{ $mClass }}">{{ $mLabel }}</span>
@endif
