unit uFileUploadXML;

interface

uses
  System.Net.HttpClient,
  System.Net.Mime,
  System.JSON,
  System.SysUtils,
  System.Classes,
  log;

type
  TResultadoChave = (
    rcValida,
    rcInvalida,
    rcIndeterminado
  );

function UploadXML(
  Url,
  Key,
  Arquivo: string
): Boolean;

function UploadJSON(
  Url,
  Key,
  Body: string
): Boolean;

function ValidaChave(
  Url,
  Key: string;
  out Motivo: string
): TResultadoChave;

implementation

function NormalizaURL(const Url: string): string;
begin
  Result := Trim(Url);

  if Result = '' then
    Exit;

  if not Result.EndsWith('/') then
    Result := Result + '/';
end;

function LerMensagemJSON(const Conteudo: string): string;
var
  JsonValue: TJSONValue;
  JsonObject: TJSONObject;
  ValorMsg: TJSONValue;
begin
  Result := '';

  JsonValue := TJSONObject.ParseJSONValue(Conteudo);

  try
    if not (JsonValue is TJSONObject) then
      Exit;

    JsonObject := JsonValue as TJSONObject;
    ValorMsg := JsonObject.GetValue('msg');

    if Assigned(ValorMsg) then
      Result := ValorMsg.Value;
  finally
    JsonValue.Free;
  end;
end;

function ValidaChave(
  Url,
  Key: string;
  out Motivo: string
): TResultadoChave;
var
  Request: THTTPClient;
  FormData: TMultipartFormData;
  Response: TStringStream;
  HttpResponse: IHTTPResponse;
  UrlValidacao: string;
  Mensagem: string;
begin
  Result := rcIndeterminado;
  Motivo := '';

  Url := NormalizaURL(Url);
  Key := Trim(Key);

TLog.GetInstance.ERRO(
  'ValidaChave',
  'DEBUG V2 - função executada. Tamanho: ' +
  Length(Key).ToString
);




  if Url = '' then
  begin
    Motivo := 'A URL do portal está vazia.';
    Exit;
  end;

  if Key = '' then
  begin
    Result := rcInvalida;
    Motivo := 'A chave do contador está vazia.';
    Exit;
  end;

  Request := THTTPClient.Create;
  FormData := TMultipartFormData.Create;
  Response := TStringStream.Create('', TEncoding.UTF8);

  try
    Request.ConnectionTimeout := 30000;
    Request.ResponseTimeout := 120000;

    FormData.AddField('key', Key);

    UrlValidacao := Url + 'api/docs/nfenfce/upload';

    try
      HttpResponse := Request.Post(
        UrlValidacao,
        FormData,
        Response
      );


     TLog.GetInstance.ERRO(
  'ValidaChave',
  'HTTP ' +
  HttpResponse.StatusCode.ToString +
  ' - ' +
  Copy(Response.DataString, 1, 500)
);



    except
      on E: Exception do
      begin
        Motivo := 'Não foi possível conectar ao portal: ' + E.Message;
        Exit;
      end;
    end;

    if HttpResponse.StatusCode = 403 then
    begin
      Result := rcInvalida;

      Mensagem := LerMensagemJSON(Response.DataString);

      if Mensagem <> '' then
        Motivo := Mensagem
      else
        Motivo := 'O portal recusou a chave. HTTP 403.';
    end
    else if HttpResponse.StatusCode = 404 then
    begin
      Result := rcIndeterminado;
      Motivo :=
        'A URL da API não foi encontrada. HTTP 404: ' +
        UrlValidacao;
    end
   else if (HttpResponse.StatusCode >= 200) and
        (HttpResponse.StatusCode <= 299) then
begin
  Result := rcValida;
  Motivo := 'Chave aceita pelo portal.';
end
    else
    begin
      Result := rcIndeterminado;
      Motivo :=
        'Resposta inesperada do portal. HTTP ' +
        HttpResponse.StatusCode.ToString;
    end;

  finally
    Response.Free;
    FormData.Free;
    Request.Free;
  end;
end;

function UploadXML(
  Url,
  Key,
  Arquivo: string
): Boolean;
var
  Request: THTTPClient;
  FormData: TMultipartFormData;
  Response: TStringStream;
  HttpResponse: IHTTPResponse;
  JsonValue: TJSONValue;
  JsonObject: TJSONObject;
  ValorMsg: TJSONValue;
  Resposta: string;
begin
  Result := False;
  JsonValue := nil;

  Url := Trim(Url);
  Key := Trim(Key);
  Arquivo := Trim(Arquivo);

  if not Url.EndsWith('/') then
    Url := Url + '/';

  if Key = '' then
  begin
    TLog.GetInstance.ERRO(
      'UploadXML',
      'A chave do contador está vazia.'
    );
    Exit;
  end;

  if Arquivo = '' then
  begin
    TLog.GetInstance.ERRO(
      'UploadXML',
      'O caminho do arquivo XML está vazio.'
    );
    Exit;
  end;

  if not FileExists(Arquivo) then
  begin
    TLog.GetInstance.ERRO(
      'UploadXML',
      'Arquivo não encontrado: ' + Arquivo
    );
    Exit;
  end;

  Request := THTTPClient.Create;
  FormData := TMultipartFormData.Create;
  Response := TStringStream.Create('', TEncoding.UTF8);

  try
    Request.ConnectionTimeout := 30000;
    Request.ResponseTimeout := 120000;

    FormData.AddField('key', Key);
    FormData.AddFile('file', Arquivo);

    try
      HttpResponse := Request.Post(
        Url,
        FormData,
        Response
      );
    except
      on E: Exception do
      begin
        TLog.GetInstance.ERRO(
          'UploadXML',
          'Falha na comunicação com o portal: ' +
          E.Message
        );
        Exit;
      end;
    end;

    Resposta := Response.DataString;

    TLog.GetInstance.ERRO(
      'UploadXML',
      'HTTP ' +
      HttpResponse.StatusCode.ToString +
      ' - ' +
      Copy(Resposta, 1, 500)
    );

    JsonValue := TJSONObject.ParseJSONValue(Resposta);

    if not (JsonValue is TJSONObject) then
    begin
      TLog.GetInstance.ERRO(
        'UploadXML',
        'O portal não retornou JSON válido.'
      );
      Exit;
    end;

    JsonObject := JsonValue as TJSONObject;
    ValorMsg := JsonObject.GetValue('msg');

    if not Assigned(ValorMsg) then
    begin
      TLog.GetInstance.ERRO(
        'UploadXML',
        'A resposta não contém o campo msg: ' +
        Copy(Resposta, 1, 500)
      );
      Exit;
    end;

    if ValorMsg.Value = '100' then
      Result := True
    else
      TLog.GetInstance.ERRO(
        'UploadXML',
        'Arquivo recusado pelo portal: ' +
        Copy(Resposta, 1, 500)
      );

  finally
    JsonValue.Free;
    Response.Free;
    FormData.Free;
    Request.Free;
  end;
end;

function UploadJSON(
  Url,
  Key,
  Body: string
): Boolean;
var
  Request: THTTPClient;
  FormData: TMultipartFormData;
  Response: TStringStream;
  HttpResponse: IHTTPResponse;
  JsonValue: TJSONValue;
  JsonObject: TJSONObject;
  ValorMsg: TJSONValue;
  Resposta: string;
begin
  Result := False;
  JsonValue := nil;

  Url := Trim(Url);
  Key := Trim(Key);

  if not Url.EndsWith('/') then
    Url := Url + '/';

  if Key = '' then
  begin
    TLog.GetInstance.ERRO(
      'UploadJSON',
      'A chave do contador está vazia.'
    );
    Exit;
  end;

  Request := THTTPClient.Create;
  FormData := TMultipartFormData.Create;
  Response := TStringStream.Create('', TEncoding.UTF8);

  try
    Request.ConnectionTimeout := 30000;
    Request.ResponseTimeout := 120000;

    // A API recebe a chave no campo chamado key.
    FormData.AddField('key', Key);

    // O JSON é enviado no campo chamado json.
    FormData.AddField('json', Body);

    try
      HttpResponse := Request.Post(
        Url,
        FormData,
        Response
      );
    except
      on E: Exception do
      begin
        TLog.GetInstance.ERRO(
          'UploadJSON',
          'Falha na comunicação com o portal: ' +
          E.Message
        );
        Exit;
      end;
    end;

    Resposta := Response.DataString;

    TLog.GetInstance.ERRO(
      'UploadJSON',
      'HTTP ' +
      HttpResponse.StatusCode.ToString +
      ' - ' +
      Copy(Resposta, 1, 500)
    );

    JsonValue := TJSONObject.ParseJSONValue(Resposta);

    if not (JsonValue is TJSONObject) then
    begin
      TLog.GetInstance.ERRO(
        'UploadJSON',
        'O portal não retornou JSON válido.'
      );
      Exit;
    end;

    JsonObject := JsonValue as TJSONObject;
    ValorMsg := JsonObject.GetValue('msg');

    if not Assigned(ValorMsg) then
    begin
      TLog.GetInstance.ERRO(
        'UploadJSON',
        'A resposta não contém o campo msg: ' +
        Copy(Resposta, 1, 500)
      );
      Exit;
    end;

    if ValorMsg.Value = '100' then
      Result := True
    else
      TLog.GetInstance.ERRO(
        'UploadJSON',
        'Resposta recusada pelo portal: ' +
        Copy(Resposta, 1, 500)
      );

  finally
    JsonValue.Free;
    Response.Free;
    FormData.Free;
    Request.Free;
  end;
end;

end.
