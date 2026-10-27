@if ($paginator->hasPages())

    <div class="pagination">

        <div class="col">
            <p>
                Mostrando de {{ $paginator->firstItem() }}
                até {{ $paginator->lastItem() }}
                de {{ $paginator->total() }} registros
            </p>
        </div>

        <div class="col">
            <ul>

                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <li class="page-item">
                        <span class="page-link" title="Anterior">«</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" title="Anterior" href="#"
                            wire:click="previousPage('{{ $paginator->getPageName() }}')">«</a>
                    </li>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <li class="page-item">
                            <span class="page-link">{{ $element }}</span>
                        </li>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active"
                                    wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}">
                                    <span class="page-link">{{ $page }}</span>
                                </li>
                            @else
                                <li class="page-item"
                                    wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}">
                                    <a class="page-link" href="#"
                                        wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')">{{ $page }}</a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" title="Próxima" href="#"
                            wire:click="nextPage('{{ $paginator->getPageName() }}')">»</a>
                    </li>
                @else
                    <li class="page-item">
                        <span class="page-link" title="Próxima">»</span>
                    </li>
                @endif

            </ul>

        </div>

    </div>
    <!-- pagination -->

@endif
