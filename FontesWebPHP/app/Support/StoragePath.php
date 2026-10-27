<?php

namespace App\Support;

/**
 * Monta caminhos do Storage a partir de dado NÃO CONFIÁVEL.
 *
 * O caminho de arquivamento do XML é montado com campos que vêm do próprio XML
 * enviado (CNPJ, IE, modelo, mês/ano), então quem envia escolheria o destino.
 * Use sempre isto nas rotas de upload.
 *
 * ⚠️ Não confie no Flysystem: ele só barra `..` que sobe ACIMA da raiz. Sair de
 * `docs/` e pousar em outra subpasta de `storage/app` ele normaliza sem
 * reclamar — a defesa é sanear o segmento.
 */
final class StoragePath
{
    /**
     * Um segmento de caminho seguro: remove (não escapa) tudo fora de
     * [A-Za-z0-9._-]. Segmento vazio vira '_', para não colapsar a estrutura de
     * pastas e fazer dois documentos disputarem o mesmo caminho.
     */
    public static function segmento($valor): string
    {
        $limpo = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $valor);

        // Em laço: um único str_replace transformaria '....' em '..'.
        while (str_contains($limpo, '..')) {
            $limpo = str_replace('..', '', $limpo);
        }

        $limpo = trim($limpo, '.');

        return $limpo !== '' ? $limpo : '_';
    }

    /**
     * Caminho completo: prefixo FIXO (escrito no código, nunca saneado) +
     * segmentos não confiáveis (sempre saneados).
     */
    public static function montar(string $prefixoFixo, ...$segmentos): string
    {
        $partes = array_map([self::class, 'segmento'], $segmentos);

        return rtrim($prefixoFixo, '/') . '/' . implode('/', $partes);
    }

    /** Nome de arquivo seguro: `basename` (o cliente controla o nome no multipart) + segmento(). */
    public static function arquivo($nome): string
    {
        return self::segmento(basename((string) $nome));
    }
}
