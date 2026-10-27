unit Diretory;

interface

uses system.ioutils, system.SysUtils, Vcl.Forms;

type
  TDiretory = class(TObject)
  private
    LOCALAPLICACAO: string;
    LOCALLOG: STRING;
    LOCALDASHBORD: STRING;
    LOCALRELATORIO: STRING;
    LOCALRELATORIOACBR: STRING;
    LOCALNFESHEMA: STRING;

  strict private
    class var FInstance: TDiretory;
  public
    constructor Create;
    function getLOCALAPLICACAO: string;
    function getLOCALLOG: string;
    function getLOCALDASHBORD: string;
    function getLOCALRELATORIO: string;
    function getLOCALRELATORIOACBR: string;
    class function GetInstance(): TDiretory;
  end;

const
  ServidorWEB = '';

implementation

{ TDiretory }

constructor TDiretory.Create;
begin
  LOCALAPLICACAO := ExtractFilePath(Application.ExeName);

  LOCALNFESHEMA := LOCALNFESHEMA + '/NFESHEMA/';

  LOCALRELATORIO := LOCALAPLICACAO + '/RELATORIO/';

  LOCALRELATORIOACBR := LOCALAPLICACAO + '/RELATORIOACBR/';

  LOCALLOG := LOCALAPLICACAO + '/LOG/';

  LOCALDASHBORD := LOCALAPLICACAO + 'Dashbord/pages/';

end;

class function TDiretory.GetInstance: TDiretory;
begin
  if not Assigned(Self.FInstance) then
    Self.FInstance := TDiretory.Create;
  Result := Self.FInstance;
end;

function TDiretory.getLOCALAPLICACAO: string;
begin
  if not DirectoryExists(LOCALAPLICACAO) then
    MkDir(LOCALAPLICACAO);
  Result := LOCALAPLICACAO
end;

function TDiretory.getLOCALDASHBORD: string;
begin
  Result := LOCALDASHBORD;
end;

function TDiretory.getLOCALLOG: string;
begin
  if not DirectoryExists(LOCALLOG) then
    MkDir(LOCALLOG);
  Result := LOCALLOG
end;

function TDiretory.getLOCALRELATORIO: string;
begin
  if not DirectoryExists(LOCALRELATORIO) then
    MkDir(LOCALRELATORIO);
  Result := LOCALRELATORIO
end;

function TDiretory.getLOCALRELATORIOACBR: string;
begin
  if not DirectoryExists(LOCALRELATORIOACBR) then
    MkDir(LOCALRELATORIOACBR);
  Result := LOCALRELATORIOACBR
end;

end.
