program PortalContador;

uses
  Vcl.Forms,
  uPrincipal in 'uPrincipal.pas' {Form1},
  Vcl.Themes,
  Vcl.Styles, windows, Dialogs;

{$R *.res}

begin
var
  MutexHandle: THandle;
begin

  MutexHandle := CreateMutex(nil, True, 'PortalContador');
    if (MutexHandle <> 0) and (GetLastError = ERROR_ALREADY_EXISTS) then
      begin
        ShowMessage('Já existe uma instância da aplicação em execução.');
        Halt;
      end;

  Application.Initialize;
  Application.MainFormOnTaskbar := True;
  TStyleManager.TrySetStyle('Slate Classico');
  Application.CreateForm(TForm1, Form1);
  Application.Run;

  if MutexHandle <> 0 then
    CloseHandle(MutexHandle);
end;

end.
