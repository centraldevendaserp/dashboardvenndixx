{{-- Selo de HOMOLOGAÇÃO (tpAmb = 2). Espera $ambiente.

     Emissão de teste convive com a real na mesma tabela, e homologação não
     gasta número — várias emissões repetem o mesmo, o que parece duplicata.

     ⚠️ Classe própria, não 'badge-round': esse é exclusivo de coluna de
     situação, e há teste travando isso. --}}
@if ((string) ($ambiente ?? '') === '2')
    <span class="doc-selo-homolog"
        title="Emissão em ambiente de homologação (teste) — não é documento fiscal válido">Homologação</span>
@endif
