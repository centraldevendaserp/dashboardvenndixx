unit Log;

interface

uses Diretory, system.SysUtils, system.Classes;

// type
// TEnviarLog = class(TThread)
// procedure execute; override;
//
// end;

type
  TLog = class
  strict private
    class var FInstance: TLog;
    constructor create;
    procedure MakeFile;
    procedure AtualizaArquivoDoDia;
    function getHorario: string;

  var
    Filename: string;
    arq: TextFile;
    // Dia coberto pelo Filename atual. Ver AtualizaArquivoDoDia.
    FDiaDoArquivo: TDate;

  public
    class function GetInstance(): TLog;
    procedure INFO(Classe, messagem: string);
    procedure ERRO(Classe, messagem: string);
    procedure debug(Classe, messagem: string);
  end;

implementation

{ TLog }

procedure Removerlog(xFolder: string);
var
  sr: TSearchRec;
  searchResult, I: integer;
  Lista: TStringList;
begin
  // Coleta todos os .txt, ordena pela data de modificacao REAL e mantem os 30
  // mais recentes (antes: off-by-one apagava 1 a mais e a ordem por nome ddmm...
  // podia apagar o log recente e manter o antigo).
  Lista := TStringList.Create;
  try
    searchResult := FindFirst(xFolder + '/*.txt', faAnyFile, sr);
    try
      while searchResult = 0 do
      begin
        if (sr.Name[1] <> '.') and (sr.Attr and faDirectory = 0) then
          Lista.Add(FormatDateTime('yyyymmddhhnnsszzz', sr.TimeStamp) + '=' + sr.Name);
        searchResult := FindNext(sr);
      end;
    finally
      FindClose(sr);
    end;
    Lista.Sort;                        // do mais antigo para o mais recente
    for I := 0 to Lista.Count - 31 do  // <= 30 arquivos: Count-31 < 0, nada apaga
      DeleteFile(xFolder + '/' + Lista.ValueFromIndex[I]);
  finally
    Lista.Free;
  end;
end;

{ Nome do arquivo por DIA, e nao por execucao.

  Antes o nome vinha de 'yyyymmddhhnnss' fixado no construtor e o Removerlog so
  rodava ali: num agente que fica meses no ar - que e o caso - tudo ia para UM
  arquivo, crescendo sem limite, e os 30 arquivos guardados cobriam 30
  REINICIOS, nao 30 dias. O log tambem carrega chave de acesso e CNPJ, entao
  arquivo eterno e dado pessoal acumulado sem prazo.

  Com o nome por dia, o corte de 30 arquivos do Removerlog vira 30 DIAS. }
constructor TLog.create;
begin
  FDiaDoArquivo := 0;
  AtualizaArquivoDoDia;
end;

procedure TLog.AtualizaArquivoDoDia;
begin
  if FDiaDoArquivo = Date then
    Exit;

  FDiaDoArquivo := Date;
  Filename := FormatDateTime('yyyymmdd', now()) + '.txt';
  // Na virada do dia tambem: sem isto a limpeza so aconteceria no reinicio.
  Removerlog(TDiretory.GetInstance.getLOCALLOG);
end;

procedure TLog.debug(Classe, messagem: string);
begin
  try
    try
      AtualizaArquivoDoDia;
      AssignFile(arq, TDiretory.GetInstance.getLOCALLOG + Filename);
      MakeFile;
      Writeln(arq, getHorario + '-' + 'DEBUG-Class: ' + Classe + '-mensagem: ' +
        messagem);
    finally
      CloseFile(arq);
    end;
  except

  end;
end;

procedure TLog.ERRO(Classe, messagem: string);
begin
  try
    try
      AtualizaArquivoDoDia;
      AssignFile(arq, TDiretory.GetInstance.getLOCALLOG + Filename);
      MakeFile;
      Writeln(arq, getHorario + '-' + 'ERRO-Class: ' + Classe + '-mensagem: ' +
        messagem);
    finally
      CloseFile(arq);
    end;
  except

  end;
end;

function TLog.getHorario: string;
begin
  Result := FormatDateTime('dd/mm/yyyy hh:nn:ss', now());
end;

class function TLog.GetInstance: TLog;
begin
  if not Assigned(Self.FInstance) then
    Self.FInstance := TLog.create;
  Result := Self.FInstance;

end;

procedure TLog.INFO(Classe, messagem: string);
begin
  try
    try
      AtualizaArquivoDoDia;
      AssignFile(arq, TDiretory.GetInstance.getLOCALLOG + Filename);
      MakeFile;
      Writeln(arq, getHorario + '-' + 'INFO-Class: ' + Classe + '-mensagem: ' +
        messagem);
    finally
      CloseFile(arq);
    end;
  except

  end;
end;

procedure TLog.MakeFile;
begin
  if not FileExists(TDiretory.GetInstance.getLOCALLOG + Filename) then
  begin
    Rewrite(arq);
  end
  else
  begin
    Append(arq);
  end;
end;

{ TEnviarLog }

end.
