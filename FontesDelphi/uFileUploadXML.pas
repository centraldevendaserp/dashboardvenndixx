unit uFileUploadXML;

interface

uses System.Net.HttpClient, System.Net.Mime, System.JSON, log;

type
  { Resultado da conferencia da chave. INDETERMINADO existe de proposito: rede
    caida ou servidor fora do ar NAO e chave errada, e tratar como se fosse
    suspenderia os envios de um cliente por causa de um soluco de rede. }
  TResultadoChave = (rcValida, rcInvalida, rcIndeterminado);

function UploadXML(Url, Key, Arquivo: String): Boolean;
function UploadJSON(Url, Body: String): Boolean;
function ValidaChave(Url, Key: String; out Motivo: String): TResultadoChave;

implementation

uses
  System.SysUtils, System.Classes;

// Confere a chave ANTES de varrer as pastas.
//
// Sem isto, chave errada nao parava nada: a varredura rodava inteira, cada
// arquivo levava 403, nada era gravado como enviado, e na tela parecia que
// estava tudo funcionando. Relatado em 2026-07-31 com uma chave digitada como
// 'teste' - o sincronizador rodou igual.
//
// Como se descobre, sem endpoint novo (funciona tambem contra portal antigo):
// um POST com a chave e SEM arquivo. Medido no servidor:
//
//   chave certa  -> 422, corpo com msg "Arquivo XML invalido ou ausente."
//   chave errada -> 403, corpo com msg "Chave de acesso invalida. Gere a ..."
//   rota errada  -> 404
//
// Ou seja, o que decide e o STATUS 403 - nao o texto, que pode mudar. E o
// proprio servidor ja manda a explicacao pronta para mostrar a quem instala.
//
// COMENTARIO DE LINHA, nao { }: o bloco do Pascal NAO ANINHA e o primeiro `}`
// fecha. Como este texto cita JSON, com { } o compilador fechava o comentario
// no meio e o resto virava codigo (foi o E2029/E2038 de 2026-07-31).
function ValidaChave(Url, Key: String; out Motivo: String): TResultadoChave;
var
  LRequest: THTTPClient;
  LFormData: TMultipartFormData;
  LResponse: TStringStream;
  LRet: IHTTPResponse;
  vJsonAux: TJSONValue;
  vJson: TJsonObject;
begin
  Result := rcIndeterminado;
  Motivo := '';
  vJsonAux := nil;

  LRequest := THTTPClient.Create;
  LRequest.ConnectionTimeout := 15000;
  LRequest.ResponseTimeout := 30000;
  LFormData := TMultipartFormData.Create();
  LResponse := TStringStream.Create('', TEncoding.UTF8);

  try
    LFormData.AddField('key', Key);

    try
      LRet := LRequest.Post(Url + 'api/docs/nfenfce/upload', LFormData, LResponse);
    except
      on E: Exception do
      begin
        // Rede/servidor: nao da para afirmar nada sobre a chave.
        Motivo := 'nao foi possivel falar com o portal: ' + E.Message;
        Exit;
      end;
    end;

    if LRet.StatusCode = 403 then
    begin
      Result := rcInvalida;
      // O servidor manda a orientacao pronta; so usamos o texto dele se vier.
      vJsonAux := TJsonObject.ParseJSONValue(LResponse.DataString);
      if vJsonAux <> nil then
      begin
        vJson := vJsonAux as TJsonObject;
        if vJson.Get('msg') <> nil then
          Motivo := vJson.GetValue('msg').Value;
      end;
      if Motivo = '' then
        Motivo := 'o portal recusou a chave (403).';
    end
    else if LRet.StatusCode = 404 then
      Motivo := 'a URL do portal parece errada (404 em ' + Url + ').'
    else
      // 422 e o esperado (chave aceita, sem arquivo). Qualquer outro status
      // tambem passa: se nao e 403, nao e problema de chave.
      Result := rcValida;
  finally
    vJsonAux.Free;
    LFormData.Free;
    LResponse.Free;
    LRequest.Free;
  end;
end;

function UploadXML(Url, Key, Arquivo: String): Boolean;
var
  LRequest: THTTPClient;
  LFormData: TMultipartFormData;
  LResponse: TStringStream;
  vRetJson: TJsonObject;
  vRetJsonAux: TJSONValue;
  vResult: string;
begin

  Result := false;
  vRetJsonAux := nil;   // garante que o Free no finally é seguro mesmo se o Post falhar

  LRequest := THTTPClient.Create;
  LRequest.ConnectionTimeout := 30000;   // 30s para conectar
  LRequest.ResponseTimeout := 120000;    // 120s para a resposta (servidor lento não trava a thread)
  LFormData := TMultipartFormData.Create();
  LResponse := TStringStream.Create;

  try
    LFormData.AddField('key', Key);
    LFormData.AddFile('file', Arquivo);

    try
      LRequest.Post(Url, LFormData, LResponse);
    except
      on E: Exception do
      begin
        TLog.GetInstance.ERRO('UploadXML', 'Falha HTTP em ' + Arquivo + ': ' + E.Message);
        Result := False;
        Exit;   // o finally ainda roda e libera tudo
      end;
    end;

    vResult := LResponse.DataString;
    vRetJsonAux := TJsonObject.ParseJSONValue(vResult);
    if vRetJsonAux <> nil then
    begin
      vRetJson := vRetJsonAux as TJsonObject;
      if vRetJson.Get('msg') <> nil then
      begin
        if vRetJson.GetValue('msg').Value = '100' then
          Result := true
        else
          // Recusa do servidor (422/403/...). Antes isto era SILENCIOSO: o
          // arquivo entrava na conta de 'com falha' e nada dizia o porque,
          // deixando quem le o log sem saber se era chave errada, layout
          // recusado ou outra coisa.
          TLog.GetInstance.ERRO('UploadXML',
            'RECUSADO em ' + Arquivo + ' -> ' + vResult);
      end
      else
        TLog.GetInstance.ERRO('UploadXML',
          'RESPOSTA SEM msg em ' + Arquivo + ' -> ' + vRetJson.ToJSON);
    end
    else
      // Corpo que nem e JSON: pagina de erro do servidor (500/502, HTML de
      // proxy) ou URL apontando para lugar errado.
      TLog.GetInstance.ERRO('UploadXML',
        'RESPOSTA NAO E JSON em ' + Arquivo + ' -> ' + Copy(vResult, 1, 300));

  finally
    vRetJsonAux.Free;   // vRetJson é o mesmo objeto (typecast) - liberar só a referência dona
    LFormData.Free;
    LResponse.Free;
    LRequest.Free;
  end;

end;

function UploadJSON(Url, Body: String): Boolean;
var
  LRequest: THTTPClient;
  LBody: TStringStream;
  LResponse: TStringStream;
  vRetJsonAux: TJSONValue;
  vRetJson: TJsonObject;
begin
  Result := false;
  vRetJsonAux := nil;

  LRequest := THTTPClient.Create;
  LRequest.ConnectionTimeout := 30000;
  LRequest.ResponseTimeout := 120000;
  LRequest.ContentType := 'application/json';
  LBody := TStringStream.Create(Body, TEncoding.UTF8);
  LResponse := TStringStream.Create('', TEncoding.UTF8);

  try
    try
      LRequest.Post(Url, LBody, LResponse);
    except
      on E: Exception do
      begin
        TLog.GetInstance.ERRO('UploadJSON', 'Falha HTTP: ' + E.Message);
        Exit;
      end;
    end;

    vRetJsonAux := TJsonObject.ParseJSONValue(LResponse.DataString);
    if vRetJsonAux <> nil then
    begin
      vRetJson := vRetJsonAux as TJsonObject;
      if (vRetJson.Get('msg') <> nil) and
        (vRetJson.GetValue('msg').Value = '100') then
        Result := true
      else
        TLog.GetInstance.ERRO('UploadJSON', vRetJson.ToJSON);
    end;
  finally
    vRetJsonAux.Free;
    LBody.Free;
    LResponse.Free;
    LRequest.Free;
  end;
end;

end.
