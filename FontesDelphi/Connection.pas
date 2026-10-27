unit Connection;

interface

uses FireDAC.Comp.Client, FireDAC.Stan.def, FireDAC.DApt, FireDAC.VCLUI.Wait,
  FireDAC.UI.Intf, FireDAC.Phys.SQLite, FireDAC.Phys.FB, FireDAC.Phys.FBDef,
  FireDAC.Stan.Intf, FireDAC.Comp.UI,
  FireDAC.Phys, FireDAC.Phys.SQLiteDef, Data.DB, FireDAC.Stan.Async;

type
  TConnection = class(TObject)
  public
    DataSouce: TDataSource;
    Query: TFDQuery;
    destructor Destroy; override;
    procedure Commit;
    constructor Create(hasTransaction: boolean;
      const DataSource: boolean = false; const xERP: boolean = false);
    procedure RoolbBack;

    class var DriverID, Server, Port, Database, User_Name, Password: string;
    class var ERPServer, ERPPort, ERPDatabase, ERPUser, ERPPassword: string;
  private
    hasTransaction, DataSource: boolean;
    FDConnection: TFDConnection;
    Transaction: TFDTransaction;
    FDGUIxWaitCursor1: TFDGUIxWaitCursor;
    FDPhysMySQLDriverLink1: TFDPhysSQLiteDriverLink;
    FDPhysFBDriverLink1: TFDPhysFBDriverLink;

  end;

implementation

{ TConection }

procedure TConnection.Commit;
begin
  if hasTransaction then
    Transaction.Commit;
end;

constructor TConnection.Create(hasTransaction: boolean;
  const DataSource: boolean = false; const xERP: boolean = false);
begin
  FDGUIxWaitCursor1 := TFDGUIxWaitCursor.Create(nil);
  FDPhysMySQLDriverLink1 := TFDPhysSQLiteDriverLink.Create(nil);
  FDPhysFBDriverLink1 := TFDPhysFBDriverLink.Create(nil);
  self.hasTransaction := hasTransaction;
  self.DataSource := DataSource;
  FDConnection := TFDConnection.Create(nil);

  FDConnection.Params.Clear;
  if xERP then
  begin
    // Banco do ERP (Firebird), read-only por disciplina: uma SELECT por tabela,
    // nada de escrita. Sem CharacterSet: respeita o charset do banco do cliente.
    FDConnection.Params.Add('DriverID=FB');
    FDConnection.Params.Add('Server=' + ERPServer);
    FDConnection.Params.Add('Port=' + ERPPort);
    FDConnection.Params.Add('Database=' + ERPDatabase);
    FDConnection.Params.Add('User_Name=' + ERPUser);
    FDConnection.Params.Add('Password=' + ERPPassword);
  end
  else
  begin
    FDConnection.Params.Add('DriverID=' + DriverID);
    FDConnection.Params.Add('Server=' + Server);
    FDConnection.Params.Add('Port=' + Port);
    FDConnection.Params.Add('Database=' + Database);
    FDConnection.Params.Add('CharacterSet=utf8');
    FDConnection.Params.Add('User_Name=' + User_Name);
    FDConnection.Params.Add('Password=' + Password);
  end;

  FDConnection.LoginPrompt := false;
  Query := TFDQuery.Create(nil);
  Query.Connection := self.FDConnection;
  if DataSource then
  begin
    DataSouce := TDataSource.Create(nil);
    DataSouce.DataSet := Query;
  end;

  if hasTransaction then
  begin
    Transaction := TFDTransaction.Create(nil);
    FDConnection.TxOptions.AutoCommit := false;
    Transaction.Connection := FDConnection;
    Query.Transaction := Transaction;
    Transaction.StartTransaction;
  end;
  FDConnection.Open();

end;

destructor TConnection.Destroy;
begin
  if hasTransaction then
    Transaction.Free;
  Query.Close;
  Query.Free;
  FDConnection.Close;
  FDConnection.Free;
  if DataSource then
    DataSouce.Free;
  FDPhysMySQLDriverLink1.Free;   // faltava: o driver link vazava a cada conexão
  FDPhysFBDriverLink1.Free;
  FDGUIxWaitCursor1.Free;        // faltava: o wait cursor vazava a cada conexão
  inherited Destroy;
end;

procedure TConnection.RoolbBack;
begin
  if hasTransaction then
  begin
    Query.Close;
    Transaction.Rollback;
  end;
end;

end.
