<div class="pnl-recent">

    @forelse ($documents as $doc)
        @php
            $modelMap = [
                55 => ['NF-e', 'mc-nfe'],
                65 => ['NFC-e', 'mc-nfce'],
                57 => ['CT-e', 'mc-cte'],
                58 => ['MDF-e', 'mc-mdfe'],
                59 => ['Entrada', 'mc-entr'],
            ];
            [$mLabel, $mClass] = $modelMap[(int) $doc->model] ?? ['Doc', 'mc-nfe'];
            $compName = $doc->company->fantasy_name ?? ($doc->company->corporate_name ?? null);
            $docCnpj = strlen($doc->cnpj_cpf) > 11
                ? App\Helpers\Mask::run($doc->cnpj_cpf, '##.###.###/####-##')
                : App\Helpers\Mask::run($doc->cnpj_cpf, '###.###.###-##');
        @endphp

        <div class="pnl-recent__item {{ $mClass }}">
            <span class="pnl-recent__badge">{{ $mLabel }}</span>
            <div class="pnl-recent__main">
                <b>{{ $compName ?: $docCnpj }}</b>
                <span>Nº {{ $doc->number }} · {{ $docCnpj }}</span>
            </div>
            <div class="pnl-recent__val">
                <b>R$ {{ number_format($doc->vNF, 2, ',', '.') }}</b>
                <span>{{ \Carbon\Carbon::parse($doc->issue_dh)->format('d/m/Y') }}</span>
            </div>
        </div>
    @empty
        <div class="pnl-empty">
            <i class="far fa-folder-open"></i>
            <p>Nenhum documento recente</p>
        </div>
    @endforelse

</div>
