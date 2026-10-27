#!/usr/bin/env python3
"""
Confere os .pas antes de abrir o Delphi.

Existe por causa de um erro real (2026-07-31): um comentario `{ ... }` com JSON
dentro. O bloco do Pascal NAO ANINHA, entao o primeiro `}` do JSON fechou o
comentario no meio e o resto do texto virou codigo:

    [dcc32 Error] uFileUploadXML.pas(33): E2029 Declaration expected...
    [dcc32 Error] uFileUploadXML.pas(37): E2038 Illegal character '}'

A regra que pega isso: FORA de comentario e de string, o Pascal nunca tem `{`
nem `}` soltos. Se aparecer um, o comentario fechou onde nao devia.

Confere tambem o que ja mordeu antes: encoding (os fontes sao latin-1, e
misturar UTF-8 quebra acento) e quebra de linha (uPrincipal.pas e LF,
uFileUploadXML.pas e CRLF — casar padrao com o errado nao acha nada).

Uso:  python3 FontesDelphi/verifica-fontes.py
"""
import sys, glob, os

def analisa(caminho):
    with open(caminho, 'rb') as f:
        bruto = f.read()

    problemas = []

    # --- encoding: tem de abrir como latin-1 sem estourar ---
    try:
        texto = bruto.decode('latin-1')
    except Exception as e:
        return [f'nao abre como latin-1: {e}']

    # --- mojibake: bytes UTF-8 gravados dentro de arquivo ANSI ---
    # Sintoma: 'Ã©' onde devia estar 'é'. Nao quebra o build (fica em comentario
    # e em string), mas aparece torto na IDE e na tela do agente. Se TODOS os
    # bytes >127 formam UTF-8 valido, o arquivo foi gravado como UTF-8 por
    # engano — um .pas ANSI com acento de verdade NAO decodifica como UTF-8.
    if any(b > 127 for b in bruto):
        try:
            bruto.decode('utf-8')
            problemas.append(
                'parece UTF-8 gravado num arquivo ANSI (mojibake tipo "Ã©" no '
                'lugar de "é") — regrave em latin-1')
        except UnicodeDecodeError:
            pass   # nao e UTF-8 valido => e ANSI de verdade, correto

    # --- chaves soltas fora de comentario/string (o erro de 2026-07-31) ---
    i, n = 0, len(texto)
    linha = 1
    estado = 'codigo'
    while i < n:
        c = texto[i]
        if c == '\n':
            linha += 1

        if estado == 'codigo':
            if c == '{':
                estado = 'chaves'
            elif texto.startswith('(*', i):
                estado = 'parens'; i += 2; continue
            elif texto.startswith('//', i):
                estado = 'linha'; i += 2; continue
            elif c == "'":
                estado = 'texto'
            elif c == '}':
                problemas.append(
                    f'linha {linha}: `}}` solto fora de comentario — algum '
                    f'comentario {{ }} fechou antes da hora (E2038)')
        elif estado == 'chaves':
            if c == '}':
                estado = 'codigo'
        elif estado == 'parens':
            if texto.startswith('*)', i):
                estado = 'codigo'; i += 2; continue
        elif estado == 'linha':
            if c == '\n':
                estado = 'codigo'
        elif estado == 'texto':
            if c == "'":
                # '' dentro de string e aspa escapada, nao fim
                if texto.startswith("''", i):
                    i += 2; continue
                estado = 'codigo'
        i += 1

    if estado != 'codigo':
        problemas.append(f'o arquivo termina dentro de {estado} — comentario ou string sem fechar')

    return problemas


def main():
    raiz = os.path.dirname(os.path.abspath(__file__))
    arquivos = sorted(glob.glob(os.path.join(raiz, '*.pas')))
    if not arquivos:
        print('nenhum .pas encontrado'); return 1

    houve = False
    for a in arquivos:
        nome = os.path.basename(a)
        bruto = open(a, 'rb').read()
        fim = 'CRLF' if b'\r\n' in bruto else 'LF'
        problemas = analisa(a)
        marca = 'ERRO' if problemas else ' ok '
        print(f'  [{marca}] {nome:24} {fim:5} {len(bruto):>7} bytes')
        for p in problemas:
            houve = True
            print(f'          -> {p}')

    print()
    print('  Nenhum problema encontrado.' if not houve
          else '  CORRIJA antes de abrir o Delphi.')
    return 1 if houve else 0


if __name__ == '__main__':
    sys.exit(main())
