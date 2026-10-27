unit uPrincipal;

interface

uses
  Winapi.Windows, Winapi.Messages, System.SysUtils, System.Variants,
  System.Classes, Vcl.Graphics, System.IniFiles, System.DateUtils,
  Vcl.Controls, Vcl.Forms, Vcl.Dialogs, Vcl.Menus, Vcl.ExtCtrls, Vcl.AppEvnts,
  Vcl.StdCtrls, Comobj, System.IOUtils, IdHashSHA, FileCtrl, ActiveX,
  FireDAC.Stan.ExprFuncs, FireDAC.Phys.SQLiteDef, FireDAC.Stan.Intf,
  FireDAC.Phys, FireDAC.Phys.SQLite, uFileUploadXML, Log, Vcl.WinXCtrls,
  FireDAC.Stan.Param, Vcl.ComCtrls, dxBarBuiltInMenu, cxGraphics, cxControls,
  cxLookAndFeels, cxLookAndFeelPainters, dxSkinsCore, cxPC, Vcl.Buttons, System.ImageList,
  Vcl.ImgList, cxImageList, Registry, ShlObj, ShellApi, Vcl.Imaging.pngimage,
  System.SyncObjs, System.Generics.Collections;

type
  TForm1 = class(TForm)
    TrayIcon1: TTrayIcon;
    PopupMenu1: TPopupMenu;
    Sair1: TMenuItem;
    ApplicationEvents1: TApplicationEvents;
    nfenfce_upload_xml_1: TEdit;
    Button1: TButton;
    btn_nfe_xml: TButton;
    nfenfce_upload_xml_2: TEdit;
    btn_nfce_xml: TButton;
    cte_upload_xml: TEdit;
    btn_cte_xml: TButton;
    mdfe_upload_xml: TEdit;
    btn_mdfe_xml: TButton;
    Label1: TLabel;
    Label2: TLabel;
    Label3: TLabel;
    Label4: TLabel;
    events_upload_nfenfce_xml_1: TEdit;
    btn_nfe_cancel: TButton;
    Label5: TLabel;
    events_upload_nfenfce_xml_2: TEdit;
    btn_nfce_cancel: TButton;
    Label6: TLabel;
    events_upload_cte_xml_1: TEdit;
    btn_cte_cancel: TButton;
    Label7: TLabel;
    events_upload_cte_xml_2: TEdit;
    btn_cte_carta: TButton;
    Label8: TLabel;
    inutilization_upload_nfenfce_xml_1: TEdit;
    btn_nfe_inutili: TButton;
    Label9: TLabel;
    inutilization_upload_cte_xml: TEdit;
    btn_cte_inutili: TButton;
    Label10: TLabel;
    Button12: TButton;
    ActivityIndicator1: TActivityIndicator;
    Timer1: TTimer;
    inutilization_upload_nfenfce_xml_2: TEdit;
    Label11: TLabel;
    btn_nfce_inutili: TButton;
    events_upload_cte_xml_3: TEdit;
    Label12: TLabel;
    btn_cte_eventos: TButton;
    Label13: TLabel;
    nfe_upload_xml_1: TEdit;
    btn_nfe_entrada: TButton;
    PageControl1: TPageControl;
    TabSheet1: TTabSheet;
    TabSheet2: TTabSheet;
    SpeedButton1: TSpeedButton;
    SpeedButton2: TSpeedButton;
    SpeedButton3: TSpeedButton;
    SpeedButton4: TSpeedButton;
    SpeedButton5: TSpeedButton;
    SpeedButton6: TSpeedButton;
    SpeedButton7: TSpeedButton;
    SpeedButton8: TSpeedButton;
    SpeedButton9: TSpeedButton;
    SpeedButton10: TSpeedButton;
    SpeedButton11: TSpeedButton;
    SpeedButton12: TSpeedButton;
    SpeedButton13: TSpeedButton;
    TabSheet3: TTabSheet;
    TabSheet4: TTabSheet;
    cxImageList1: TcxImageList;
    GroupBox1: TGroupBox;
    Memo1: TMemo;
    chkIniciarWindows: TCheckBox;
    gb_caminhos: TGroupBox;
    Button2: TButton;
    Button3: TButton;
    cxImageList2: TcxImageList;
    GroupBox2: TGroupBox;
    Edit1: TEdit;
    Label14: TLabel;
    Image1: TImage;
    Label15: TLabel;
    events_upload_mdfe_xml: TEdit;
    SpeedButton14: TSpeedButton;
    TabSheet5: TTabSheet;
    Label16: TLabel;
    Label17: TLabel;
    nfse_upload_xml: TEdit;
    nfse_upload_xml_2: TEdit;
    SpeedButton15: TSpeedButton;
    SpeedButton16: TSpeedButton;
    Label18: TLabel;
    Edit2: TEdit;
    procedure TrayIcon1Click(Sender: TObject);
    procedure Sair1Click(Sender: TObject);
    procedure ApplicationEvents1Minimize(Sender: TObject);
    procedure FormCreate(Sender: TObject);
    procedure Button1Click(Sender: TObject);
    procedure Button12Click(Sender: TObject);
    procedure btn_nfe_xmlClick(Sender: TObject);
    procedure btn_nfce_xmlClick(Sender: TObject);
    procedure btn_cte_xmlClick(Sender: TObject);
    procedure btn_mdfe_xmlClick(Sender: TObject);
    procedure btn_nfe_cancelClick(Sender: TObject);
    procedure btn_nfce_cancelClick(Sender: TObject);
    procedure btn_cte_cancelClick(Sender: TObject);
    procedure btn_cte_cartaClick(Sender: TObject);
    procedure btn_nfe_inutiliClick(Sender: TObject);
    procedure btn_cte_inutiliClick(Sender: TObject);
    procedure Timer1Timer(Sender: TObject);
    procedure FormCloseQuery(Sender: TObject; var CanClose: Boolean);
    procedure btn_nfce_inutiliClick(Sender: TObject);
    procedure btn_cte_eventosClick(Sender: TObject);
    procedure btn_nfe_entradaClick(Sender: TObject);
    procedure Button2Click(Sender: TObject);
    procedure Button3Click(Sender: TObject);
    procedure btn_mdfe_eventosClick(Sender: TObject);
    procedure btn_nfse_xmlClick(Sender: TObject);
    procedure btn_nfse_xml_2Click(Sender: TObject);
  private
    { Private declarations }
    function getPath(): string;
    procedure carregaConfiguracao;
    procedure OnTerminate(Sender: TObject);
    function IniciarProcessamento: Boolean;
    procedure InciarComWindows(Iniciar: Boolean);
    function ObterVersaoSO: String;
    function UsuarioLogado: string;
  public
    { Public declarations }
    FecharPrograma: Boolean;
    procedure HideToTrayIcon;
    procedure ShowFromTrayIcon;
    procedure execute;
  end;

type
  TTagsRoute = (tpNfeNfceUploadXml_1 = 0, tpNfeNfceUploadXml_2 = 0,
    tpCteUploadXml = 1, tpMdfeUploadXml = 2, tpEventsUploadNfeNfceXml_1 = 3,
    tpEventsUploadNfeNfceXml_2 = 3, tpEventsUploadCteXml_1 = 4,
    tpEventsUploadCteXml_2 = 4, tpInutilizationUploadNfeNfceXml = 5,
    tpInutilizationUploadCteXml = 6, tpNfeUploadXml_1 = 7,
    tpNfseUploadXml = 8, tpEventsUploadMdfeXml = 9,
    tpStatusUploadNfeNfce = 10);

type
  TProcessa = class(TThread)
  protected
    procedure execute; override;
  private
    // Contadores da varredura (zerados por pasta e somados no total da rodada).
    FPastaEnviados, FPastaJaEnviados, FPastaFalhas, FPastaInvalidos: Integer;
    FTotEnviados, FTotJaEnviados, FTotFalhas, FTotInvalidos: Integer;

    procedure ProcessaPasta(xPasta: string; xTag: TTagsRoute;
      const xCampo: string);
    procedure ProcessaPastaUnica(const xPasta: string; xTag: TTagsRoute;
      const xCampo: string);
    procedure LogMemo(const S: string);
    procedure ListFilesFolder(xFolder: string; xTag: TTagsRoute;
      const xSufixo: string);
    function FileSha1(xFilename: TFileName): string;
    function CheckHashSend(xHash: string; xTag: TTagsRoute): Boolean;
    procedure MarkSendFile(xHash: string; xTag: TTagsRoute);
    procedure ProcessaStatusERP;
    procedure GaranteTabelaStatusERP;
    function EstadoStatusERP: TDictionary<string, string>;
    procedure ProcessaDescartesERP;
    procedure GaranteTabelaDescartesERP;
    function EstadoDescartesERP: TDictionary<string, string>;
    procedure ReenvioTotalSolicitado;
    procedure ResetDedupNfse;
    procedure ProcessaNfseERP;
    procedure GaranteTabelaNfseERP;
    function EstadoNfseERP: TDictionary<string, string>;
  public
    constructor Create;
    destructor Destroy; override;
  end;

var
  Form1: TForm1;
  Url: String;
  ApiKey: String;
  FRunFlag: Integer = 0;   // 0 = ocioso, 1 = processando (controle atomico via TInterlocked)
  UltimaVarreduraERP: TDateTime = 0;   // ultima varredura do status no banco do ERP
  UltimaVarreduraNfseERP: TDateTime = 0;
  UltimaVarreduraDescERP: TDateTime = 0;   // ultima varredura do status no banco do ERP (throttle 5 min)
  AvisoERPLogado: Boolean = False;   // aviso de [BANCO_ERP] nao configurado ja foi logado (evita spam a cada 30s)
  AutoStart, HabilitaTimer: Boolean;
  ArrayTagsRoute: array [0 .. 10] of string = (
    'api/docs/nfenfce/upload',
    'api/docs/cte/upload',
    'api/docs/mdfe/upload',
    'api/docs/eventos/nfenfce/upload',
    'api/docs/eventos/cte/upload',
    'api/docs/inutilizacao/nfenfce/upload',
    'api/docs/inutilizacao/cte/upload',
    'api/docs/nfe/upload',
    'api/docs/nfse/upload',
    'api/docs/eventos/mdfe/upload',
    'api/docs/status/upload'
  );

var
  Processa: TProcessa;

const
  SELDIRHELP = 1000;

implementation

{$R *.dfm}

uses Connection, System.StrUtils, System.JSON;

function IsValidXMLFile(const XmlFile: TFileName): Boolean;
var
  XmlDoc: OleVariant;
begin
  XmlDoc := CreateOleObject('Msxml2.DOMDocument.6.0');
  try
    XmlDoc.Async := false;
    XmlDoc.validateOnParse := True;
    Result := (XmlDoc.Load(XmlFile)) and (XmlDoc.parseError.errorCode = 0);
  finally
    XmlDoc := Unassigned;
  end;
end;

function NormalizaUrlWebservice(const xUrl: string; out xNorm: string): Boolean;
var
  vTmp, vHost: string;
  vIni, vPos: Integer;
begin
  // Aceita somente http(s):// com host real (nao vazio e sem espaco) e
  // garante a barra final exigida pela concatenacao Url + rota ('https:'
  // sem dominio no .ini derrubava todos os envios com Invalid URL).
  vTmp := Trim(xUrl);
  Result := False;
  if SameText(Copy(vTmp, 1, 8), 'https://') then
    vIni := 9
  else if SameText(Copy(vTmp, 1, 7), 'http://') then
    vIni := 8
  else
    vIni := 0;
  if vIni > 0 then
  begin
    vHost := Copy(vTmp, vIni, MaxInt);
    vPos := Pos('/', vHost);
    if vPos > 0 then
      vHost := Copy(vHost, 1, vPos - 1);
    Result := (vHost <> '') and (Pos(' ', vHost) = 0);
  end;
  if Result and (vTmp[Length(vTmp)] <> '/') then
    vTmp := vTmp + '/';
  xNorm := vTmp;
end;

procedure TForm1.ApplicationEvents1Minimize(Sender: TObject);
begin
  Self.Hide();
  Self.WindowState := wsMinimized;
  TrayIcon1.Visible := True;
  TrayIcon1.Animate := True;
end;

function SufixoObrigatorio(xTag: TTagsRoute): string;
begin
  // Filtro pelo NOME do arquivo (guia de integracao): nas rotas de evento e
  // inutilizacao de NF-e/NFC-e so sobem os proc oficiais. E o que permite
  // varrer NFE\Xml (manifestacao 210xxx) ignorando os envelopes da SEFAZ
  // (-sta, -lot, -sit, -ped-*, -pro-*, ...).
  if xTag = tpEventsUploadNfeNfceXml_1 then
    Result := '-procEventoNFe.xml'
  else if xTag = tpInutilizationUploadNfeNfceXml then
    Result := '-procInutNFe.xml'
  else if xTag = tpEventsUploadCteXml_1 then
    Result := '-procEventoCTe.xml'
  else if xTag = tpInutilizationUploadCteXml then
    Result := '-procInutCTe.xml'
  else if xTag = tpEventsUploadMdfeXml then
    Result := '-procEventoMDFe.xml'
  else if xTag = tpStatusUploadNfeNfce then
    Result := '-pro-lot.xml;-sit.xml;!-ped-sit.xml'
  else
    Result := '';
end;

function SufixoAceito(const xNome, xSufixos: string): Boolean;
var
  vToken: string;
  vTemInclusao: Boolean;
begin
  // Lista separada por ';'. Token com prefixo '!' EXCLUI (prioridade);
  // os demais INCLUEM. Vazio = aceita tudo. Necessario porque
  // '...-ped-sit.xml' termina com '-sit.xml' (inclusao sozinha nao basta).
  if xSufixos = '' then
    Exit(True);

  for vToken in xSufixos.Split([';']) do
    if (vToken <> '') and (vToken[1] = '!') then
      if EndsText(Copy(vToken, 2, MaxInt), xNome) then
        Exit(False);

  vTemInclusao := False;
  for vToken in xSufixos.Split([';']) do
    if (vToken <> '') and (vToken[1] <> '!') then
    begin
      vTemInclusao := True;
      if EndsText(vToken, xNome) then
        Exit(True);
    end;

  // Lista so com exclusoes: aceita o que nao foi excluido acima.
  Result := not vTemInclusao;
end;

procedure TProcessa.ListFilesFolder(xFolder: string; xTag: TTagsRoute;
  const xSufixo: string);
var
  sr: TSearchRec;
  searchResult: Integer;
  hash: string;
  arquivo: TFileName;
begin
  searchResult := FindFirst(xFolder + '/*.*', faAnyFile, sr);
  try
    while searchResult = 0 do
    begin
      if sr.Name[1] <> '.' then
      begin
        if not(sr.attr and FILE_ATTRIBUTE_DIRECTORY > 0) then
        begin
          if (TPath.GetExtension(sr.Name) = '.xml') and
            SufixoAceito(sr.Name, xSufixo) then
          begin
            arquivo := xFolder + '/' + sr.Name;
            // UM arquivo problematico NAO pode derrubar a pasta inteira. Sem
            // este try, uma excecao aqui (XML aberto/travado pelo emissor,
            // permissao negada, disco) abortava a varredura no meio e TODOS os
            // arquivos seguintes ficavam sem enviar - e, como so o enviado e
            // marcado, a proxima rodada travava no MESMO arquivo, para sempre.
            try
              if IsValidXMLFile(arquivo) then
              begin
                hash := FileSha1(arquivo);
                if CheckHashSend(hash, xTag) then
                  Inc(FPastaJaEnviados)
                else if UploadXML(Url + ArrayTagsRoute[Integer(xTag)], ApiKey,
                  arquivo) then
                begin
                  MarkSendFile(hash, xTag);
                  Inc(FPastaEnviados);
                  TLog.GetInstance.INFO('TForm1.ListFilesFolder',
                    'ARQUIVO: ' + arquivo + ' ENVIADO.');
                end
                else
                  Inc(FPastaFalhas);   // recusado/rede: re-tenta na proxima rodada
              end
              else
              begin
                Inc(FPastaInvalidos);
                TLog.GetInstance.ERRO('TForm1.ListFilesFolder',
                  'ARQUIVO: ' + arquivo + ' XML INVÁLIDO.');
              end;
            except
              on E: Exception do
              begin
                Inc(FPastaFalhas);
                TLog.GetInstance.ERRO('TForm1.ListFilesFolder',
                  'ARQUIVO: ' + arquivo + ' PULADO (' + E.Message +
                  ') - a varredura continua nos demais.');
              end;
            end;
          end;
        end
        else
        begin
          ListFilesFolder(xFolder + '/' + sr.Name, xTag, xSufixo)
        end;
      end;
      searchResult := FindNext(sr);
    end;
  finally
    FindClose(sr);   // sem o finally, excecao no meio vazava o handle da busca
  end;
end;

procedure TProcessa.MarkSendFile(xHash: string; xTag: TTagsRoute);
var
  con: TConnection;
begin
  con := TConnection.Create(false);
  try
    con.Query.SQL.Text :=
      'insert into arquivos (path, hash) values (:path, :hash)';
    con.Query.ParamByName('path').AsString := ArrayTagsRoute[Integer(xTag)];
    con.Query.ParamByName('hash').AsString := xHash;
    con.Query.ExecSQL();
  finally
    con.Free;
  end;
end;

procedure TForm1.OnTerminate(Sender: TObject);
begin
  TInterlocked.Exchange(FRunFlag, 0);   // libera o "ocupado"; CoUninitialize agora e na propria worker
  Form1.ActivityIndicator1.Animate := false;
  Timer1.Enabled := True;
end;

procedure TForm1.btn_nfe_inutiliClick(Sender: TObject);
begin
  inutilization_upload_nfenfce_xml_1.Text := getPath();
end;

procedure TForm1.btn_cte_inutiliClick(Sender: TObject);
begin
  inutilization_upload_cte_xml.Text := getPath();
end;

procedure TForm1.Button12Click(Sender: TObject);
var
  Ini: TIniFile;
  IniFile: string;
  vUrlDigitada, vUrlNorm: string;
  vUrlValida: Boolean;
  vChave: string;
  vChaveValida: Boolean;
  vAvisos: string;
  i: Integer;
begin
  // URL invalida nao e gravada (salvar 'https:' sem dominio derrubava todos
  // os envios), mas as demais configuracoes sao salvas mesmo assim - o .ini
  // legado ja vem com 'https:' e nao pode travar o salvamento das pastas.
  vUrlDigitada := Trim(edit1.Text);
  vUrlNorm := vUrlDigitada;
  vUrlValida := (vUrlDigitada = '') or
    NormalizaUrlWebservice(vUrlDigitada, vUrlNorm);
  if vUrlValida then
    edit1.Text := vUrlNorm;

  // Chave do contador: mesma regra da URL - o que nao presta nao e gravado, e a
  // anterior fica. Gravar chave quebrada derruba TODOS os envios daquele
  // cliente, e o unico sinal disso e o log do agente.
  //
  // VAZIA e valida de proposito: e o estado do .ini modelo e significa "envios
  // suspensos" ate alguem colar a chave (ver TProcessa.execute). O que nao pode
  // e caractere branco NO MEIO - token nao tem nenhum, e colar a linha inteira
  // ("Chave: 3|abc...") ou um pedaco com quebra de linha tem.
  vChave := Trim(Edit2.Text);
  vChaveValida := True;
  for i := 1 to Length(vChave) do
    if vChave[i] <= ' ' then
    begin
      vChaveValida := False;
      Break;
    end;

  IniFile := ChangeFileExt(Application.ExeName, '.ini');
  Ini := TIniFile.Create(IniFile);
  try

    Ini.WriteString('DATA', 'nfenfce_upload_xml_1', nfenfce_upload_xml_1.Text);
    Ini.WriteString('DATA', 'nfenfce_upload_xml_2', nfenfce_upload_xml_2.Text);
    Ini.WriteString('DATA', 'cte_upload_xml', cte_upload_xml.Text);
    Ini.WriteString('DATA', 'mdfe_upload_xml', mdfe_upload_xml.Text);
    Ini.WriteString('DATA', 'events_upload_nfenfce_xml_1',
      events_upload_nfenfce_xml_1.Text);
    Ini.WriteString('DATA', 'events_upload_nfenfce_xml_2',
      events_upload_nfenfce_xml_2.Text);
    Ini.WriteString('DATA', 'events_upload_cte_xml_1',
      events_upload_cte_xml_1.Text);
    Ini.WriteString('DATA', 'events_upload_cte_xml_2',
      events_upload_cte_xml_2.Text);
    Ini.WriteString('DATA', 'events_upload_cte_xml_3',
      events_upload_cte_xml_3.Text);
    Ini.WriteString('DATA', 'inutilization_upload_nfenfce_xml_1',
      inutilization_upload_nfenfce_xml_1.Text);
    Ini.WriteString('DATA', 'inutilization_upload_nfenfce_xml_2',
      inutilization_upload_nfenfce_xml_2.Text);
    Ini.WriteString('DATA', 'inutilization_upload_cte_xml',
      inutilization_upload_cte_xml.Text);
    Ini.WriteString('DATA', 'nfe_upload_xml_1', nfe_upload_xml_1.Text);
    Ini.WriteString('DATA', 'events_upload_mdfe_xml',
      events_upload_mdfe_xml.Text);
    Ini.WriteString('DATA', 'nfse_upload_xml', nfse_upload_xml.Text);
    Ini.WriteString('DATA', 'nfse_upload_xml_2', nfse_upload_xml_2.Text);
    if vUrlValida then
      Ini.WriteString('WEBSERVICE', 'Url', vUrlNorm);
    if vChaveValida then
    begin
      Ini.WriteString('WEBSERVICE', 'Key', vChave);
      // A varredura le o .INI a cada passada (TProcessa.execute), entao a chave
      // nova ja vale na proxima. Este ApiKey do form so alimenta a tela.
      ApiKey := vChave;
    end;

    Ini.WriteBool('config','inciarwindows',chkIniciarWindows.Checked);

    InciarComWindows(chkIniciarWindows.Checked);

  finally
    Ini.Free;
  end;

  // Dois campos podem recusar a gravacao, entao o aviso e uma LISTA. Antes era
  // um if/else para a URL sozinha; com dois, o encadeamento esconderia um dos
  // problemas e a pessoa corrigiria um e salvaria de novo achando que acabou.
  vAvisos := '';
  if not vUrlValida then
    vAvisos := vAvisos + sLineBreak + sLineBreak +
      '- A URL do webservice e invalida e NAO foi gravada (a anterior foi ' +
      'mantida). Informe a URL completa do portal, por exemplo: ' +
      'https://cliente.seudominio.com.br/';
  if not vChaveValida then
    vAvisos := vAvisos + sLineBreak + sLineBreak +
      '- A chave do contador tem espaco no meio e NAO foi gravada (a anterior ' +
      'foi mantida). Cole so o valor gerado no painel, sem texto em volta.';

  if vAvisos <> '' then
  begin
    ShowMessage('Pastas e demais opcoes foram salvas, mas:' + vAvisos);
    if not vUrlValida then
      edit1.SetFocus
    else
      Edit2.SetFocus;
    Exit;
  end;

  // Salvou tudo. Chave vazia nao e erro, mas e o motivo numero um de "instalei e
  // nao chega nada no portal" - entao a tela diz, em vez de deixar so no log.
  if vChave = '' then
    ShowMessage('Configuracoes salvas.' + sLineBreak + sLineBreak +
      'ATENCAO: a chave do contador esta VAZIA, entao o agente NAO vai enviar ' +
      'nada. Gere a chave no painel, no cadastro do usuario deste cliente, e ' +
      'cole no campo "Chave do Contador".')
  else
    ShowMessage('Configuracoes salvas. Ja valem na proxima varredura, ' +
      'sem precisar reiniciar o agente.');
end;

procedure TForm1.btn_nfce_inutiliClick(Sender: TObject);
begin
  inutilization_upload_nfenfce_xml_2.Text := getPath();
end;

procedure TForm1.btn_cte_eventosClick(Sender: TObject);
begin
  events_upload_cte_xml_3.Text := getPath();
end;

procedure TForm1.btn_nfe_entradaClick(Sender: TObject);
begin
  nfe_upload_xml_1.Text := getPath();
end;

procedure TForm1.btn_mdfe_eventosClick(Sender: TObject);
begin
  events_upload_mdfe_xml.Text := getPath();
end;

{ A NFS-e tem DUAS pastas (Enviadas e Canceladas). Elas dividiam um campo so,
  separadas por ';', e o botao tinha de ACRESCENTAR em vez de substituir - senao
  apagava em silencio a pasta ja escolhida. Ficava impossivel saber, olhando a
  tela, qual das duas era qual. Agora cada uma tem campo e botao proprios, como
  na MDF-e, e cada botao manda so no seu. }
procedure TForm1.btn_nfse_xmlClick(Sender: TObject);
var
  vPasta: string;
begin
  vPasta := Trim(getPath());
  if vPasta <> '' then
    nfse_upload_xml.Text := vPasta;
end;

procedure TForm1.btn_nfse_xml_2Click(Sender: TObject);
var
  vPasta: string;
begin
  vPasta := Trim(getPath());
  if vPasta <> '' then
    nfse_upload_xml_2.Text := vPasta;
end;

procedure TForm1.Button1Click(Sender: TObject);
begin
  execute;
end;

procedure TForm1.Button2Click(Sender: TObject);
var
  SR: TSearchRec;
  I: integer;
begin
  I := FindFirst(ExtractFilePath(Application.ExeName)+'\LOG\*.*', faAnyFile, SR);
    while I = 0 do begin
      if (SR.Attr and faDirectory) <> faDirectory then
      if not DeleteFile(ExtractFilePath(Application.ExeName)+'\LOG\' + SR.Name) then
        ShowMessage('Não consegui excluir ...\Portal Contador\LOG\' + SR.Name);
        I := FindNext(SR);
    end;
    memo1.Clear;
end;

procedure TForm1.Button3Click(Sender: TObject);
begin
  ShellExecute(Application.HANDLE, 'open', PChar(ExtractFilePath(Application.ExeName)+'\LOG\'),nil,nil,SW_SHOWNORMAL);
end;

procedure TForm1.btn_nfe_xmlClick(Sender: TObject);
begin
  nfenfce_upload_xml_1.Text := getPath();
end;

procedure TForm1.btn_nfce_xmlClick(Sender: TObject);
begin
  nfenfce_upload_xml_2.Text := getPath();
end;

procedure TForm1.btn_cte_xmlClick(Sender: TObject);
begin
  cte_upload_xml.Text := getPath();
end;

procedure TForm1.btn_mdfe_xmlClick(Sender: TObject);
begin
  mdfe_upload_xml.Text := getPath();
end;

procedure TForm1.btn_nfe_cancelClick(Sender: TObject);
begin
  events_upload_nfenfce_xml_1.Text := getPath();
end;

procedure TForm1.btn_nfce_cancelClick(Sender: TObject);
begin
  events_upload_nfenfce_xml_2.Text := getPath();
end;

procedure TForm1.btn_cte_cancelClick(Sender: TObject);
begin
  events_upload_cte_xml_1.Text := getPath();
end;

procedure TForm1.btn_cte_cartaClick(Sender: TObject);
begin
  events_upload_cte_xml_2.Text := getPath();
end;

procedure TForm1.carregaConfiguracao;
var
  Ini: TIniFile;
  IniFile: string;
begin
  IniFile := ChangeFileExt(Application.ExeName, '.ini');
  Ini := TIniFile.Create(IniFile);
  try

    nfenfce_upload_xml_1.Text := Ini.ReadString('DATA',
      'nfenfce_upload_xml_1', '');
    nfenfce_upload_xml_2.Text := Ini.ReadString('DATA',
      'nfenfce_upload_xml_2', '');
    cte_upload_xml.Text := Ini.ReadString('DATA', 'cte_upload_xml', '');
    mdfe_upload_xml.Text := Ini.ReadString('DATA', 'mdfe_upload_xml', '');
    events_upload_nfenfce_xml_1.Text :=
      Ini.ReadString('DATA', 'events_upload_nfenfce_xml_1', '');
    events_upload_nfenfce_xml_2.Text :=
      Ini.ReadString('DATA', 'events_upload_nfenfce_xml_2', '');
    events_upload_cte_xml_1.Text := Ini.ReadString('DATA',
      'events_upload_cte_xml_1', '');
    events_upload_cte_xml_2.Text := Ini.ReadString('DATA',
      'events_upload_cte_xml_2', '');
      events_upload_cte_xml_3.Text := Ini.ReadString('DATA',
      'events_upload_cte_xml_3', '');
    inutilization_upload_nfenfce_xml_1.Text :=
      Ini.ReadString('DATA', 'inutilization_upload_nfenfce_xml_1', '');
    inutilization_upload_nfenfce_xml_2.Text :=
      Ini.ReadString('DATA', 'inutilization_upload_nfenfce_xml_2', '');
    inutilization_upload_cte_xml.Text :=
      Ini.ReadString('DATA', 'inutilization_upload_cte_xml', '');
    nfe_upload_xml_1.Text := Ini.ReadString('DATA',
      'nfe_upload_xml_1', '');
    events_upload_mdfe_xml.Text :=
      Ini.ReadString('DATA', 'events_upload_mdfe_xml', '');
    nfse_upload_xml.Text := Ini.ReadString('DATA', 'nfse_upload_xml', '');
    nfse_upload_xml_2.Text := Ini.ReadString('DATA', 'nfse_upload_xml_2', '');
    { .ini ANTIGO trazia as duas pastas da NFS-e no MESMO campo, separadas por
      ';'. Reparte na leitura E GRAVA de volta no arquivo.

      Gravar e o que fecha a atualizacao sozinha. A varredura le o .INI, nao a
      tela (ver Envia), entao sem isto o campo 'nfse_upload_xml_2' ficaria vazio
      no arquivo e o ProcessaPasta soltaria "pasta nao preenchida" a cada 30
      SEGUNDOS, em todo cliente atualizado, ate alguem abrir a tela e clicar em
      Gravar. Aviso repetido assim e o que faz ninguem mais ler o log. }
    if (Trim(nfse_upload_xml_2.Text) = '') and
      (Pos(';', nfse_upload_xml.Text) > 0) then
    begin
      nfse_upload_xml_2.Text :=
        Trim(Copy(nfse_upload_xml.Text, Pos(';', nfse_upload_xml.Text) + 1, MaxInt));
      nfse_upload_xml.Text :=
        Trim(Copy(nfse_upload_xml.Text, 1, Pos(';', nfse_upload_xml.Text) - 1));
      Ini.WriteString('DATA', 'nfse_upload_xml', nfse_upload_xml.Text);
      Ini.WriteString('DATA', 'nfse_upload_xml_2', nfse_upload_xml_2.Text);
      TLog.GetInstance.INFO('TForm1.carregaConfiguracao',
        'CONFIGURACAO MIGRADA: as pastas da NFS-e estavam num campo unico e ' +
        'foram separadas em nfse_upload_xml (Enviadas) e nfse_upload_xml_2 ' +
        '(Canceladas) no .ini.');
    end;
    Url := Ini.ReadString('WEBSERVICE', 'Url', '');
    Edit1.text := Ini.ReadString('WEBSERVICE', 'Url', '');
    // Sem default: ver o comentario em TProcessa.execute. Aqui e so para exibir.
    ApiKey := Trim(Ini.ReadString('WEBSERVICE', 'Key', ''));
    // O campo nasce com o que esta no .ini. Se abrisse vazio num cliente que ja
    // tem chave, o primeiro "Gravar Config." APAGARIA a chave dele e o agente
    // pararia de enviar em silencio. Ler e gravar andam juntos.
    Edit2.Text := ApiKey;
    chkIniciarWindows.Checked :=  Ini.ReadBool('config','inciarwindows',False);

  finally
    Ini.Free;
  end;
end;

function TForm1.IniciarProcessamento: Boolean;
begin
  // Marca "ocupado" de forma ATOMICA antes do Start: fecha a janela em que um 2o
  // Timer/clique criava uma segunda TProcessa (sobrescrevendo a global Processa).
  Result := TInterlocked.CompareExchange(FRunFlag, 1, 0) = 0;
  if not Result then
    Exit;
  Processa := TProcessa.Create;
  Processa.OnTerminate := OnTerminate;
  Processa.FreeOnTerminate := True;
  Processa.Start;
end;

procedure TForm1.execute;
begin
  if not IniciarProcessamento then
    ShowMessage('Já está executando.');
end;

function TProcessa.CheckHashSend(xHash: string; xTag: TTagsRoute): Boolean;
var
  con: TConnection;
begin
  con := TConnection.Create(false);
  try

    con.Query.SQL.Text :=
      'select 1 from arquivos where path = :path and hash = :hash';
    con.Query.ParamByName('path').AsString := ArrayTagsRoute[Integer(xTag)];
    con.Query.ParamByName('hash').AsString := xHash;
    con.Query.Open();

    if con.Query.RecordCount > 0 then
    begin
      Result := True;
    end
    else
    begin
      Result := false;
    end;
  finally
    con.Free;
  end;
end;

function TProcessa.FileSha1(xFilename: TFileName): string;
var
  pSHA: TIdHashSHA1;
  pStream: TFileStream;
begin
  pSHA := TIdHashSHA1.Create;
  pStream := TFileStream.Create(xFilename, fmOpenRead or fmShareDenyWrite);
  try
    Result := pSHA.HashStreamAsHex(pStream);
  finally
    pStream.Free;
    pSHA.Free;
  end;
end;

procedure TForm1.FormCloseQuery(Sender: TObject; var CanClose: Boolean);
begin
  CanClose := FecharPrograma;

  if not(CanClose) then
  begin
    HideToTrayIcon;
  end;
end;

procedure TForm1.FormCreate(Sender: TObject);
var
  Ini: TIniFile;
  IniFile: string;
begin
  TLog.GetInstance.INFO('TForm1.FormCreate', 'INICIANDO APLICAÇÃO');
        Memo1.Lines.Add(DateToStr(now)+' - '+TimeToStr(now) + ' - Iniciando a Aplicação.');
        Memo1.Lines.Add('');

  HideToTrayIcon;

  IniFile := ChangeFileExt(Application.ExeName, '.ini');
  Ini := TIniFile.Create(IniFile);

  try
    TConnection.DriverID := Ini.ReadString('BANCO', 'DRIVERID', '');
    TConnection.Server := Ini.ReadString('BANCO', 'SERVER', '');
    TConnection.Port := Ini.ReadString('BANCO', 'PORT', '');
    // Caminho RELATIVO resolve contra o diretorio de trabalho do processo, nao
    // contra a pasta do exe - e quem inicia pelo HKCU\Run nao define working
    // directory. O agente podia abrir (ou CRIAR) um banco.sqlite noutro lugar:
    // dedup zerado, e tudo reenviado do zero. O .ini e o LOG ja usam ExeName;
    // so o banco tinha ficado de fora.
    TConnection.Database := Ini.ReadString('BANCO', 'DATABASE', '');
    if (TConnection.Database <> '')
      and (ExtractFileDrive(TConnection.Database) = '')
      and (Copy(TConnection.Database, 1, 1) <> '\\') then
      TConnection.Database :=
        ExtractFilePath(Application.ExeName) + TConnection.Database;
    TConnection.User_Name := Ini.ReadString('BANCO', 'USER', '');
    TConnection.Password := Ini.ReadString('BANCO', 'PASSWORD', '');
  finally
    Ini.Free;
  end;

  carregaConfiguracao();

  execute;
end;

function TForm1.getPath: string;
var
  Dir: string;
begin
  Result := '';
  Dir := ExtractFileDir(Application.ExeName);
  if Win32MajorVersion >= 6 then
    with TFileOpenDialog.Create(nil) do
    try
      Title := 'Selecione o Diretório';
      Options := [fdoPickFolders, fdoPathMustExist, fdoForceFileSystem];
      OkButtonLabel := 'Selecione';
      DefaultFolder := Dir;
      FileName := Dir;
      if Execute then
        Result := FileName;
    finally
      Free;
    end
  else
  begin
    if SelectDirectory(Dir, [sdAllowCreate, sdPerformCreate, sdPrompt], SELDIRHELP) then
      Result := Dir;
  end;
end;

procedure TForm1.HideToTrayIcon;
begin
  Self.Hide();
  Self.WindowState := wsMinimized;
  TrayIcon1.Visible := True;
end;

procedure TForm1.Sair1Click(Sender: TObject);
begin
  Application.Terminate;
end;

procedure TForm1.ShowFromTrayIcon;
begin
  TrayIcon1.Visible := false;
  Show();
  WindowState := wsNormal;
  Application.BringToFront();
end;

procedure TForm1.Timer1Timer(Sender: TObject);
begin
  IniciarProcessamento;
end;

procedure TForm1.TrayIcon1Click(Sender: TObject);
begin
  ShowFromTrayIcon;
end;

{ TProcessa }

constructor TProcessa.Create;
begin
  inherited Create(True);
end;

destructor TProcessa.Destroy;
begin

  inherited;
end;

procedure TProcessa.execute;
var
  Ini: TIniFile;
  IniFile: string;
  vUrlNorm: string;
  vResumoTotal: string;
  vMotivoChave: string;

  procedure Envia(const xCampo: string; xTag: TTagsRoute);
  begin
    ProcessaPasta(Ini.ReadString('DATA', xCampo, ''), xTag, xCampo);
  end;

begin
  inherited;
  CoInitialize(nil);
  try
    TThread.Synchronize(TThread.CurrentThread,
      procedure
      begin
        Form1.ActivityIndicator1.Animate := True;
        Form1.Timer1.Enabled := false;
      end);

    IniFile := ChangeFileExt(Application.ExeName, '.ini');
    Ini := TIniFile.Create(IniFile);

    try
      // Rele [WEBSERVICE] a cada varredura: o Salvar da tela passa a
      // valer sem reiniciar o agente.
      if not NormalizaUrlWebservice(Ini.ReadString('WEBSERVICE', 'Url', ''),
        vUrlNorm) then
      begin
        TLog.GetInstance.ERRO('TProcessa.execute',
          'URL do webservice nao configurada ou incompleta no .ini ' +
          '([WEBSERVICE] Url) - envios suspensos ate configurar.');
        LogMemo(DateToStr(now) + ' - ' + TimeToStr(now) +
          ' - Aviso: URL do webservice nao configurada - envios suspensos.');
        LogMemo('');
        Exit;
      end;
      Url := vUrlNorm;

      // Default VAZIO, de proposito. Estava 'Sistema' - e se o TIniFile devolver
      // o default para chave presente-e-vazia (comportamento da RTL que varia), o
      // agente mandaria a chave compartilhada sem ninguem pedir, justamente
      // quando o .ini foi deixado em branco para usar token. Lendo com '' e
      // tratando o vazio aqui, o resultado nao depende desse detalhe.
      //
      // Vazio SUSPENDE os envios, como a URL incompleta: melhor parar com o
      // motivo no log do que gravar na base fiscal com a credencial de todo mundo.
      ApiKey := Trim(Ini.ReadString('WEBSERVICE', 'Key', ''));
      if ApiKey = '' then
      begin
        TLog.GetInstance.ERRO('TProcessa.execute',
          'Chave do agente nao configurada no .ini ([WEBSERVICE] Key) - envios ' +
          'suspensos. Gere a chave no painel, no cadastro do usuario deste ' +
          'cliente, e cole o valor aqui.');
        LogMemo(DateToStr(now) + ' - ' + TimeToStr(now) +
          ' - Aviso: chave do agente nao configurada - envios suspensos.');
        LogMemo('');
        Exit;
      end;

      // Chave PREENCHIDA nao quer dizer chave CERTA. Sem esta conferencia, uma
      // chave digitada errada deixava a varredura rodar inteira levando 403 em
      // cada arquivo - nada era gravado e na tela parecia normal.
      //
      // So o 403 suspende. Rede caida / portal fora do ar cai em
      // rcIndeterminado e a varredura SEGUE: um soluco de rede nao pode parar
      // o envio de um cliente, e o erro real aparece arquivo a arquivo.
      case ValidaChave(Url, ApiKey, vMotivoChave) of
        rcInvalida:
          begin
            TLog.GetInstance.ERRO('TProcessa.execute',
              'CHAVE RECUSADA PELO PORTAL - envios suspensos. ' + vMotivoChave);
            LogMemo(DateToStr(now) + ' - ' + TimeToStr(now) +
              ' - CHAVE INCORRETA - envios suspensos.');
            LogMemo('    ' + vMotivoChave);
            LogMemo('    Confira o campo "Chave do Contador" e clique em Gravar Config.');
            LogMemo('');
            Exit;
          end;
        rcIndeterminado:
          if vMotivoChave <> '' then
            TLog.GetInstance.ERRO('TProcessa.execute',
              'Nao consegui conferir a chave (a varredura segue mesmo assim): ' +
              vMotivoChave);
      end;

      FTotEnviados := 0; FTotJaEnviados := 0;
      FTotFalhas := 0;   FTotInvalidos := 0;
      TLog.GetInstance.INFO('TProcessa.execute', 'VARREDURA INICIADA.');

      ReenvioTotalSolicitado;   // so se o .ini pedir ([config] reenviar_tudo=1)
      ResetDedupNfse;           // uma vez por instalacao (ver o comentario da rotina)

      Envia('nfenfce_upload_xml_1', tpNfeNfceUploadXml_1);
      Envia('nfenfce_upload_xml_2', tpNfeNfceUploadXml_2);
      Envia('cte_upload_xml', tpCteUploadXml);
      Envia('mdfe_upload_xml', tpMdfeUploadXml);
      Envia('events_upload_nfenfce_xml_1', tpEventsUploadNfeNfceXml_1);
      Envia('events_upload_nfenfce_xml_2', tpEventsUploadNfeNfceXml_2);
      Envia('events_upload_cte_xml_1', tpEventsUploadCteXml_1);
      Envia('events_upload_cte_xml_2', tpEventsUploadCteXml_2);
      Envia('events_upload_cte_xml_3', tpEventsUploadCteXml_2);
      Envia('inutilization_upload_nfenfce_xml_1',
        tpInutilizationUploadNfeNfceXml);
      Envia('inutilization_upload_nfenfce_xml_2',
        tpInutilizationUploadNfeNfceXml);
      Envia('inutilization_upload_cte_xml', tpInutilizationUploadCteXml);
      Envia('nfe_upload_xml_1', tpNfeUploadXml_1);
      Envia('nfse_upload_xml', tpNfseUploadXml);
      Envia('nfse_upload_xml_2', tpNfseUploadXml);   // Canceladas: mesma rota
      Envia('events_upload_mdfe_xml', tpEventsUploadMdfeXml);
      Envia('status_upload_xml', tpStatusUploadNfeNfce);
      ProcessaStatusERP;   // canal novo (banco do ERP) - spec 2026-07-16
      ProcessaNfseERP;     // situacao da NFS-e: so o NFSE_MASTER sabe
      ProcessaDescartesERP; // notas descartadas no sistema (nunca foram a SEFAZ)

      // Fecho da rodada: sem isto o log terminava no meio e nao dava para
      // saber se a varredura chegou ao fim nem quanto subiu.
      vResumoTotal := IntToStr(FTotEnviados) + ' enviado(s), ' +
        IntToStr(FTotJaEnviados) + ' ja enviado(s) antes, ' +
        IntToStr(FTotInvalidos) + ' invalido(s), ' +
        IntToStr(FTotFalhas) + ' com falha';

      if (FTotFalhas > 0) or (FTotInvalidos > 0) then
        TLog.GetInstance.ERRO('TProcessa.execute',
          'VARREDURA CONCLUIDA COM PENDENCIAS: ' + vResumoTotal +
          '. Os que falharam serao re-tentados na proxima rodada.')
      else
        TLog.GetInstance.INFO('TProcessa.execute',
          'VARREDURA CONCLUIDA: ' + vResumoTotal + '.');

      // Mesma redacao da leitura por pasta: com "concluída" numa e "concluida"
      // na outra, a mesma tela ficaria com a palavra escrita de dois jeitos.
      // O log em ARQUIVO continua numa linha so, porque la o que importa e o grep.
      LogMemo(DateToStr(now) + ' - ' + TimeToStr(now) + ' - Varredura concluída:');
      LogMemo(IntToStr(FTotEnviados) + ' Novos enviados, ' +
        IntToStr(FTotJaEnviados) + ' já enviados antes - ' +
        IntToStr(FTotInvalidos) + ' inválidos, ' +
        IntToStr(FTotFalhas) + ' com falhas');
      LogMemo('');
    finally
      Ini.Free;
    end;
  finally
    CoUninitialize;   // fecha o COM na MESMA thread que abriu (antes: no OnTerminate/main)
  end;

end;

procedure TProcessa.LogMemo(const S: string);
begin
  // Log de UI a partir da worker: assincrono e thread-safe (nao bloqueia a worker).
  TThread.Queue(nil,
    procedure
    begin
      Form1.Memo1.Lines.Add(S);
    end);
end;

procedure TProcessa.ProcessaPasta(xPasta: string; xTag: TTagsRoute;
  const xCampo: string);
var
  vCaminho: string;
  vAchou: Boolean;
begin
  // Um campo pode listar VARIOS caminhos separados por ';' (guia de
  // integracao), ex.: C:\Sistema\NFCe\Cancelamento;C:\Sistema\NFCe\Evento
  // - cobre CCe, manifestacao (NFE\Xml) e pastas de transicao sem mexer na tela.
  vAchou := False;
  for vCaminho in xPasta.Split([';']) do
    if Trim(vCaminho) <> '' then
    begin
      vAchou := True;
      ProcessaPastaUnica(Trim(vCaminho), xTag, xCampo);
    end;

  if not vAchou then
    ProcessaPastaUnica('', xTag, xCampo); // mantem o aviso de campo em branco
end;

procedure TProcessa.ProcessaPastaUnica(const xPasta: string; xTag: TTagsRoute;
  const xCampo: string);
var
  vResumo: string;
begin
  try
    if Trim(xPasta) = '' then
    begin
      // Campo em branco no .ini e escolha valida (cliente nao usa esse
      // documento): aviso informativo, sem poluir o log com ERRO.
      TLog.GetInstance.INFO('TProcessa.ProcessaPasta',
        'AVISO: pasta "' + xCampo + '" nao preenchida na configuracao - ignorada.');
      LogMemo(DateToStr(now) + ' - ' + TimeToStr(now) +
        ' - Aviso: pasta "' + xCampo + '" nao preenchida na configuracao - ignorada.');
      LogMemo('');
      Exit;
    end;

    TLog.GetInstance.INFO('TProcessa.ProcessaPasta', 'PROCESSANDO :' + xPasta);

    if DirectoryExists(xPasta) then
    begin
      TLog.GetInstance.INFO('TProcessa.ProcessaPasta', 'PASTA: ' + xPasta);
      // Caminho na MESMA linha: com ele sozinho a linha fica em ~70 colunas e
      // cabe. O que estourava a largura eram os NUMEROS grudados no fim (130+
      // colunas) - e sao eles que descem, logo abaixo.
      LogMemo(DateToStr(now)+' - '+TimeToStr(now) +
        ' - Iniciando leitura: ' + xPasta);

      FPastaEnviados := 0; FPastaJaEnviados := 0;
      FPastaFalhas := 0;   FPastaInvalidos := 0;

      ListFilesFolder(xPasta, xTag, SufixoObrigatorio(xTag));

      Inc(FTotEnviados, FPastaEnviados);
      Inc(FTotJaEnviados, FPastaJaEnviados);
      Inc(FTotFalhas, FPastaFalhas);
      Inc(FTotInvalidos, FPastaInvalidos);

      // Fecha a leitura da pasta dizendo O QUE aconteceu. Antes so havia o
      // "Processando", entao nao dava para saber se terminou, se varreu tudo
      // ou se parou no meio.
      // O resumo em UMA linha continua indo para o log em ARQUIVO, porque la o
      // que importa e o grep. Na TELA ele e quebrado (abaixo).
      vResumo := IntToStr(FPastaEnviados) + ' enviado(s), ' +
        IntToStr(FPastaJaEnviados) + ' ja enviado(s) antes, ' +
        IntToStr(FPastaInvalidos) + ' invalido(s), ' +
        IntToStr(FPastaFalhas) + ' com falha';

      if (FPastaFalhas > 0) or (FPastaInvalidos > 0) then
        TLog.GetInstance.ERRO('TProcessa.ProcessaPasta',
          'LEITURA CONCLUIDA COM PENDENCIAS: ' + xPasta + ' - ' + vResumo + '.')
      else
        TLog.GetInstance.INFO('TProcessa.ProcessaPasta',
          'LEITURA CONCLUIDA: ' + xPasta + ' - ' + vResumo + '.');

      // Duas linhas: a de cima diz O QUE e ONDE, a de baixo os numeros. Formato
      // definido pelo time em 2026-07-31.
      LogMemo(DateToStr(now)+' - '+TimeToStr(now) +
        ' - Leitura concluída: ' + xPasta);
      LogMemo(IntToStr(FPastaEnviados) + ' Novos enviados, ' +
        IntToStr(FPastaJaEnviados) + ' já enviados antes - ' +
        IntToStr(FPastaInvalidos) + ' inválidos, ' +
        IntToStr(FPastaFalhas) + ' com falhas');
      LogMemo('');
    end
    else
    begin
      TLog.GetInstance.ERRO('TProcessa.ProcessaPasta',
        'PASTA: ' + xPasta + ' (campo "' + xCampo + '") - NÃO EXISTE.');
      LogMemo(DateToStr(now)+' - '+TimeToStr(now) +
        ' - Aviso: a pasta do campo "' + xCampo + '" não existe: ' + xPasta);
      LogMemo('');
    end;
  except
    on e: Exception do
    begin
      TLog.GetInstance.ERRO('TProcessa.ProcessaPasta', 'ERRO: ' + e.ToString);
      LogMemo('Ocorreu um erro: ' + e.ToString);
      LogMemo(DateToStr(now)+' - '+TimeToStr(now));
      LogMemo('');
    end;
  end;

end;

procedure TProcessa.GaranteTabelaStatusERP;
var
  con: TConnection;
begin
  con := TConnection.Create(false);
  try
    con.Query.SQL.Text := 'create table if not exists status_erp ' +
      '(chave text primary key, situacao text)';
    con.Query.ExecSQL();
  finally
    con.Free;
  end;
end;

function TProcessa.EstadoStatusERP: TDictionary<string, string>;
var
  con: TConnection;
begin
  Result := TDictionary<string, string>.Create;
  con := TConnection.Create(false);
  try
    con.Query.SQL.Text := 'select chave, situacao from status_erp';
    con.Query.Open();
    while not con.Query.Eof do
    begin
      Result.AddOrSetValue(con.Query.FieldByName('chave').AsString,
        con.Query.FieldByName('situacao').AsString);
      con.Query.Next;
    end;
  finally
    con.Free;
  end;
end;

{ Notas DESCARTADAS no sistema de vendas, lidas do banco do ERP.

  A venda existiu, ganhou numero e foi jogada fora ANTES de virar documento
  fiscal: nao tem XML (nunca foi a SEFAZ) e nao e evento de inutilizacao de
  FAIXA. So o ERP sabe dela.

  ATENCAO ao nome: o ERP chama isto de "Inutilizada", igual ao evento fiscal de
  inutilizacao de faixa - e foi o que fez os totais divergirem. Das 30 notas 'I'
  de NFC-e do cliente, so 5 tinham protocolo da SEFAZ (essas sobem pelos
  -procInutNFe.xml, rota de inutilizacao); as outras 25 sao descarte interno e
  vem por aqui.

  Mapa PROVADO em 2026-07-29 (cruzamento por chave e por soma de TOTAL):
    NFE_MASTER.SITUACAO  = '5'  -> descartada
    NFCE_MASTER.SITUACAO = 'I'  -> descartada
  Nao inventar outros codigos: 1/3/G/R/D seguem sem confirmacao. }
procedure TProcessa.ProcessaDescartesERP;
const
  TAM_LOTE = 200;
var
  Ini: TIniFile;
  vLote: TJSONArray;
  conERP: TConnection;
  vRow: TJSONObject;
  vBody: TJSONObject;
  vTabela: Integer;
  vTotal, vEnviadas: Integer;
  vModelo, vSituacao, vEmissao, vSql: string;
  vCnpj, vNumero, vSerie, vMesAno, vIdent, vAssin: string;
  vEstado: TDictionary<string, string>;
  vPend: TStringList;         // 'identidade=assinatura' confirmados apos msg=100
  conLocal: TConnection;

  function EnviaLote: Boolean;
  var
    i: Integer;
  begin
    Result := True;
    if vLote.Count = 0 then
      Exit;
    vBody := TJSONObject.Create;
    try
      vBody.AddPair('key', ApiKey);
      vBody.AddPair('rows', vLote);   // vBody passa a ser dono do array
      Result := UploadJSON(Url + 'api/docs/descartes-erp/upload', vBody.ToJSON);
    finally
      vBody.Free;
      vLote := TJSONArray.Create;     // proximo lote
    end;
    if Result then
    begin
      // So marca o estado local APOS msg=100 - mesma disciplina dos outros
      // canais: falhou, nao marca, e a proxima varredura tenta de novo.
      conLocal := TConnection.Create(false);
      try
        for i := 0 to vPend.Count - 1 do
        begin
          conLocal.Query.SQL.Text := 'insert or replace into descartes_erp ' +
            '(identidade, assinatura) values (:i, :a)';
          conLocal.Query.ParamByName('i').AsString := vPend.Names[i];
          conLocal.Query.ParamByName('a').AsString := vPend.ValueFromIndex[i];
          conLocal.Query.ExecSQL();
        end;
      finally
        conLocal.Free;
      end;
      Inc(vEnviadas, vPend.Count);
      TLog.GetInstance.INFO('TProcessa.ProcessaDescartesERP',
        'LOTE DE DESCARTES ENVIADO: ' + IntToStr(vPend.Count) + ' linha(s).');
    end;
    vPend.Clear;
  end;

begin
  Ini := TIniFile.Create(ChangeFileExt(Application.ExeName, '.ini'));
  try
    TConnection.ERPServer := Ini.ReadString('BANCO_ERP', 'SERVER', 'localhost');
    TConnection.ERPPort := Ini.ReadString('BANCO_ERP', 'PORT', '3050');
    TConnection.ERPDatabase := Ini.ReadString('BANCO_ERP', 'DATABASE', '');
    TConnection.ERPUser := Ini.ReadString('BANCO_ERP', 'USER', 'SYSDBA');
    TConnection.ERPPassword := Ini.ReadString('BANCO_ERP', 'PASSWORD', 'masterkey');
  finally
    Ini.Free;
  end;

  if Trim(TConnection.ERPDatabase) = '' then
    Exit;   // aviso ja sai no ProcessaStatusERP

  if (UltimaVarreduraDescERP > 0) and
    (System.DateUtils.MinutesBetween(Now, UltimaVarreduraDescERP) < 5) then
    Exit;
  UltimaVarreduraDescERP := Now;

  vTotal := 0;
  vEnviadas := 0;
  try
    GaranteTabelaDescartesERP;
    vEstado := EstadoDescartesERP;
    vPend := TStringList.Create;
    vLote := TJSONArray.Create;
    try
      // So sobe o que MUDOU (ver GaranteTabelaDescartesERP): a versao anterior
      // reenviava a lista inteira a cada 5 minutos, para sempre.
      for vTabela := 0 to 1 do
      begin
        if vTabela = 0 then
        begin
          vModelo := '55';
          vSituacao := '5';
          vSql := 'select m.NUMERO, m.SERIE, m.DATA_EMISSAO, m.TOTAL, m.CHAVE, ' +
            'e.CNPJ from NFE_MASTER m ' +
            'left join EMPRESA e on e.CODIGO = m.FKEMPRESA ' +
            'where m.SITUACAO = ''5''';
        end
        else
        begin
          vModelo := '65';
          vSituacao := 'I';
          vSql := 'select m.NUMERO, m.SERIE, m.DATA_EMISSAO, m.TOTAL, m.CHAVE, ' +
            'e.CNPJ from NFCE_MASTER m ' +
            'left join EMPRESA e on e.CODIGO = m.FKEMPRESA ' +
            'where m.SITUACAO = ''I''';
        end;

        conERP := TConnection.Create(false, false, True);
        try
          conERP.Query.SQL.Text := vSql;
          conERP.Query.Open();
          while not conERP.Query.Eof do
          begin
            // DATA_EMISSAO NULL viraria '1899-12-30' no AsDateTime: manda vazio.
            if conERP.Query.FieldByName('DATA_EMISSAO').IsNull then
              vEmissao := ''
            else
              vEmissao := FormatDateTime('yyyy-mm-dd',
                conERP.Query.FieldByName('DATA_EMISSAO').AsDateTime);

            Inc(vTotal);

            vCnpj := ReplaceStr(ReplaceStr(ReplaceStr(
              Trim(conERP.Query.FieldByName('CNPJ').AsString), '.', ''), '/', ''), '-', '');
            vNumero := Trim(conERP.Query.FieldByName('NUMERO').AsString);
            vSerie := Trim(conERP.Query.FieldByName('SERIE').AsString);
            // MESMA identidade que o servidor monta (ErpDiscardController):
            // cnpj|modelo|serie|numero|aaamm. Divergir aqui faria os dois lados
            // discordarem sobre o que e "a mesma nota".
            if vEmissao = '' then
              vMesAno := ''
            else
              vMesAno := Copy(vEmissao, 1, 4) + Copy(vEmissao, 6, 2);
            vIdent := vCnpj + '|' + vModelo + '|' + vSerie + '|' + vNumero + '|' + vMesAno;
            // Assinatura: situacao + total. Mudou uma das duas, reenvia.
            vAssin := vSituacao + '|' + FormatFloat('0.00',
              conERP.Query.FieldByName('TOTAL').AsFloat);

            if (not vEstado.ContainsKey(vIdent)) or (vEstado[vIdent] <> vAssin) then
            begin
              vRow := TJSONObject.Create;
              vRow.AddPair('model', TJSONNumber.Create(StrToIntDef(vModelo, 0)));
              vRow.AddPair('situacao', vSituacao);
              vRow.AddPair('cnpj_cpf', Trim(conERP.Query.FieldByName('CNPJ').AsString));
              vRow.AddPair('number', vNumero);
              vRow.AddPair('series', vSerie);
              // A NFC-e descartada traz o TEXTO 'INUTILIZADA' aqui; o servidor so
              // aceita 44 digitos, entao mandar como veio e seguro.
              vRow.AddPair('key', Trim(conERP.Query.FieldByName('CHAVE').AsString));
              if vEmissao <> '' then
                vRow.AddPair('emissao', vEmissao);
              vRow.AddPair('valor', TJSONNumber.Create(
                conERP.Query.FieldByName('TOTAL').AsFloat));
              vLote.AddElement(vRow);
              vPend.Add(vIdent + '=' + vAssin);

              if vLote.Count >= TAM_LOTE then
                if not EnviaLote then
                  Exit;
            end;
            conERP.Query.Next;
          end;
        finally
          conERP.Free;
        end;
      end;

      EnviaLote;   // resto do lote

      TLog.GetInstance.INFO('TProcessa.ProcessaDescartesERP',
        'DESCARTES DO ERP: ' + IntToStr(vEnviadas) + ' enviado(s), ' +
        IntToStr(vTotal) + ' na varredura.');
    finally
      vLote.Free;
      vPend.Free;
      vEstado.Free;
    end;
  except
    on E: Exception do
      TLog.GetInstance.ERRO('TProcessa.ProcessaDescartesERP', 'ERRO: ' + E.ToString);
  end;
end;

{ Reenvio TOTAL a pedido do .ini ([config] reenviar_tudo=1).

  Existe para o caso em que o dedup local e o portal ficam DESSINCRONIZADOS: o
  sqlite diz "ja enviei" para arquivos que o portal nao tem (banco do portal
  recriado, instalacao apontada para outra URL, importacao antiga perdida). Como
  o agente so reenvia o que nao esta marcado, sem isto a unica saida seria
  apagar o banco.sqlite na mao, maquina por maquina.

  Zera a tabela `arquivos` INTEIRA (todas as rotas) e DESLIGA o proprio flag no
  .ini, para nao repetir na rodada seguinte. Reenvio e inofensivo no servidor
  (reimport e delete+insert), mas custa uma varredura completa - por isso e
  manual e pontual, nunca automatico. }
procedure TProcessa.ReenvioTotalSolicitado;
var
  Ini: TIniFile;
  con: TConnection;
begin
  try
    Ini := TIniFile.Create(ChangeFileExt(Application.ExeName, '.ini'));
    try
      if Ini.ReadInteger('config', 'reenviar_tudo', 0) <> 1 then
        Exit;

      con := TConnection.Create(false);
      try
        con.Query.SQL.Text := 'delete from arquivos';
        con.Query.ExecSQL();
      finally
        con.Free;
      end;

      // Desliga ANTES de varrer: se o envio falhar no meio, a proxima rodada
      // continua de onde parou em vez de zerar tudo de novo.
      Ini.WriteInteger('config', 'reenviar_tudo', 0);

      TLog.GetInstance.INFO('TProcessa.ReenvioTotalSolicitado',
        'REENVIO TOTAL: dedup local zerado a pedido do .ini ' +
        '([config] reenviar_tudo). TODOS os XMLs sobem de novo nesta rodada; ' +
        'o flag ja foi desligado.');
      LogMemo(DateToStr(now) + ' - ' + TimeToStr(now) +
        ' - Reenvio total solicitado: todos os XMLs serao enviados novamente.');
      LogMemo('');
    finally
      Ini.Free;
    end;
  except
    on E: Exception do
      TLog.GetInstance.ERRO('TProcessa.ReenvioTotalSolicitado', 'ERRO: ' + E.ToString);
  end;
end;

{ Reenvio UNICO dos XMLs de NFS-e ja marcados como enviados.

  Por que e necessario: versoes anteriores do PORTAL respondiam msg=100 SEM
  gravar a nota em varios casos (XML fora do layout que o parser reconhecia,
  municipio sem chave nacional, nota que o parser nao completava). Como o agente
  marca o arquivo como enviado justamente pelo msg=100, esses XMLs ficaram
  marcados no sqlite do cliente e NUNCA mais seriam reenviados - a nota ficaria
  faltando no portal para sempre, mesmo com o servidor ja corrigido.

  Limpa as marcas SO da rota de NFS-e (as outras nunca tiveram esse problema) e
  registra a migracao, para rodar uma vez so por instalacao. O reenvio e
  inofensivo: no servidor, reimport e delete+insert pela identidade. }
procedure TProcessa.ResetDedupNfse;
const
  MIGRACAO = 'reset_dedup_nfse_2026_07_28';
var
  con: TConnection;
  vJaFeita: Boolean;
begin
  try
    con := TConnection.Create(false);
    try
      con.Query.SQL.Text :=
        'create table if not exists migracoes (nome text primary key)';
      con.Query.ExecSQL();

      con.Query.SQL.Text := 'select 1 from migracoes where nome = :n';
      con.Query.ParamByName('n').AsString := MIGRACAO;
      con.Query.Open();
      vJaFeita := con.Query.RecordCount > 0;
      con.Query.Close;

      if vJaFeita then
        Exit;

      con.Query.SQL.Text := 'delete from arquivos where path = :p';
      con.Query.ParamByName('p').AsString :=
        ArrayTagsRoute[Integer(tpNfseUploadXml)];
      con.Query.ExecSQL();

      con.Query.SQL.Text := 'insert into migracoes (nome) values (:n)';
      con.Query.ParamByName('n').AsString := MIGRACAO;
      con.Query.ExecSQL();

      TLog.GetInstance.INFO('TProcessa.ResetDedupNfse',
        'DEDUP DA NFS-e REINICIADO (uma vez): os XMLs de NFS-e voltam a ser ' +
        'enviados nesta rodada. Motivo: o portal antigo respondia msg=100 sem ' +
        'gravar em alguns layouts, e o agente os marcou como enviados.');
      LogMemo(DateToStr(now) + ' - ' + TimeToStr(now) +
        ' - Reenviando os XMLs de NFS-e uma vez (correcao de importacoes antigas).');
      LogMemo('');
    finally
      con.Free;
    end;
  except
    on E: Exception do
      // Nunca pode impedir a varredura: no pior caso o reenvio nao acontece.
      TLog.GetInstance.ERRO('TProcessa.ResetDedupNfse', 'ERRO: ' + E.ToString);
  end;
end;

procedure TProcessa.GaranteTabelaNfseERP;
var
  con: TConnection;
begin
  con := TConnection.Create(false);
  try
    con.Query.SQL.Text := 'create table if not exists nfse_erp ' +
      '(cod text primary key, situacao text)';
    con.Query.ExecSQL();
  finally
    con.Free;
  end;
end;

function TProcessa.EstadoNfseERP: TDictionary<string, string>;
var
  con: TConnection;
begin
  Result := TDictionary<string, string>.Create;
  con := TConnection.Create(false);
  try
    con.Query.SQL.Text := 'select cod, situacao from nfse_erp';
    con.Query.Open();
    while not con.Query.Eof do
    begin
      Result.AddOrSetValue(con.Query.FieldByName('cod').AsString,
        con.Query.FieldByName('situacao').AsString);
      con.Query.Next;
    end;
  finally
    con.Free;
  end;
end;

{ Situacao da NFS-e lida DIRETO do NFSE_MASTER (Firebird do ERP).

  Por que existe: o XML do provedor NAO e fonte confiavel de situacao. Medido
  no banco de teste do cliente - a NFS-e no 6 esta CANCELADA no ERP e o XML (o
  do disco E o do BLOB NFSE_MASTER.XML) continua dizendo "Emitida"; o emissor
  nem sempre regrava o arquivo ao cancelar. E nota recente pode nem ter arquivo
  na pasta. So o banco sabe a verdade.

  SITUACAO da NFSE_MASTER e NUMERICA (2=autorizada, 3=cancelada) - NAO e o
  T/C/R/O do NFE_MASTER/NFCE_MASTER. Nao reaproveite o mapa do outro canal. }
procedure TProcessa.ProcessaNfseERP;
const
  TAM_LOTE = 200;
var
  vEnviadas: Integer;
  Ini: TIniFile;
  vEstado: TDictionary<string, string>;
  vLote: TJSONArray;
  vPend: TStringList;         // 'cod=situacao' confirmados apos msg=100
  conERP, conLocal: TConnection;
  vCod, vProt, vChaveEstado, vSituacao, vEmissao: string;
  vRow: TJSONObject;
  vBody: TJSONObject;

  function EnviaLote: Boolean;
  var
    i: Integer;   // LOCAL: variavel de controle de `for` tem de ser local a
                  // propria rotina (E1019) - nao vale herdar do escopo pai.
  begin
    Result := True;
    if vLote.Count = 0 then
      Exit;
    vBody := TJSONObject.Create;
    try
      vBody.AddPair('key', ApiKey);
      vBody.AddPair('rows', vLote);   // vBody passa a ser dono do array
      Result := UploadJSON(Url + 'api/docs/nfse-erp/upload', vBody.ToJSON);
    finally
      vBody.Free;                     // libera o lote junto
      vLote := TJSONArray.Create;     // proximo lote
    end;
    if Result then
    begin
      // So marca o estado local APOS msg=100 (mesma disciplina do dedup).
      conLocal := TConnection.Create(false);
      try
        for i := 0 to vPend.Count - 1 do
        begin
          conLocal.Query.SQL.Text :=
            'insert or replace into nfse_erp (cod, situacao) values (:c, :s)';
          conLocal.Query.ParamByName('c').AsString := vPend.Names[i];
          conLocal.Query.ParamByName('s').AsString := vPend.ValueFromIndex[i];
          conLocal.Query.ExecSQL();
        end;
      finally
        conLocal.Free;
      end;
      Inc(vEnviadas, vPend.Count);
      TLog.GetInstance.INFO('TProcessa.ProcessaNfseERP',
        'LOTE NFSE ERP ENVIADO: ' + IntToStr(vPend.Count) + ' linha(s).');
    end;
    vPend.Clear;
  end;

begin
  Ini := TIniFile.Create(ChangeFileExt(Application.ExeName, '.ini'));
  try
    TConnection.ERPServer := Ini.ReadString('BANCO_ERP', 'SERVER', 'localhost');
    TConnection.ERPPort := Ini.ReadString('BANCO_ERP', 'PORT', '3050');
    TConnection.ERPDatabase := Ini.ReadString('BANCO_ERP', 'DATABASE', '');
    TConnection.ERPUser := Ini.ReadString('BANCO_ERP', 'USER', 'SYSDBA');
    TConnection.ERPPassword := Ini.ReadString('BANCO_ERP', 'PASSWORD', 'masterkey');
  finally
    Ini.Free;
  end;

  // [BANCO_ERP] vazio = recurso desligado. O aviso ja sai no ProcessaStatusERP,
  // que roda antes - nao duplica log a cada varredura.
  if Trim(TConnection.ERPDatabase) = '' then
    Exit;

  if (UltimaVarreduraNfseERP > 0) and
    (System.DateUtils.MinutesBetween(Now, UltimaVarreduraNfseERP) < 5) then
    Exit;
  // Marca ANTES de varrer: o piso de 5 min vale mesmo com o portal fora (o
  // estado local nao marcado ja garante a redeteccao).
  UltimaVarreduraNfseERP := Now;

  try
    vEnviadas := 0;
    GaranteTabelaNfseERP;
    vEstado := EstadoNfseERP;
    vLote := TJSONArray.Create;
    vPend := TStringList.Create;
    try
      conERP := TConnection.Create(false, false, True);
      try
        // CODIGO_VERIFICACAO e o vinculo com a nota no portal: o unico campo
        // estavel entre o banco, o retorno autorizado e o recibo de cancelamento.
        conERP.Query.SQL.Text :=
          'select m.NUMERO_NFSE, m.SERIE_RPS, m.CODIGO_VERIFICACAO, ' +
          'm.PROTOCOLO, m.SITUACAO, m.DATA_EMISSAO, m.VALOR_SERVICOS, ' +
          'm.CHAVE_NACIONAL, e.CNPJ ' +
          'from NFSE_MASTER m ' +
          'left join EMPRESA e on e.CODIGO = m.FKEMPRESA ' +
          'where m.CODIGO_VERIFICACAO is not null';
        conERP.Query.Open();
        while not conERP.Query.Eof do
        begin
          vCod := Trim(conERP.Query.FieldByName('CODIGO_VERIFICACAO').AsString);
          vProt := Trim(conERP.Query.FieldByName('PROTOCOLO').AsString);
          vSituacao := Trim(conERP.Query.FieldByName('SITUACAO').AsString);
          // O PROTOCOLO e unico por emissao; o CODIGO_VERIFICACAO pode vir
          // sobrescrito (as emissoes de homologacao da mesma nota ficam com
          // o codigo da seguinte). Sem isso, 13 registros virariam 9 linhas.
          if vProt <> '' then
            vChaveEstado := vProt
          else
            vChaveEstado := vCod;

          // So situacao conhecida entra. Codigo novo do ERP e ignorado de
          // proposito - chutar um rotulo mentiria sobre a nota do contador.
          if (vChaveEstado <> '') and ((vSituacao = '2') or (vSituacao = '3')) and
            ((not vEstado.ContainsKey(vChaveEstado)) or
             (vEstado[vChaveEstado] <> vSituacao)) then
          begin
            // DATA_EMISSAO NULL viraria '1899-12-30' no AsDateTime: manda vazio.
            if conERP.Query.FieldByName('DATA_EMISSAO').IsNull then
              vEmissao := ''
            else
              vEmissao := FormatDateTime('yyyy-mm-dd',
                conERP.Query.FieldByName('DATA_EMISSAO').AsDateTime);

            vRow := TJSONObject.Create;
            vRow.AddPair('cod_verificacao', vCod);
            vRow.AddPair('protocolo', vProt);
            vRow.AddPair('situacao', vSituacao);
            vRow.AddPair('cnpj_prestador',
              Trim(conERP.Query.FieldByName('CNPJ').AsString));
            vRow.AddPair('numero',
              Trim(conERP.Query.FieldByName('NUMERO_NFSE').AsString));
            vRow.AddPair('serie',
              Trim(conERP.Query.FieldByName('SERIE_RPS').AsString));
            vRow.AddPair('chave',
              Trim(conERP.Query.FieldByName('CHAVE_NACIONAL').AsString));
            if vEmissao <> '' then
              vRow.AddPair('emissao', vEmissao);
            vRow.AddPair('valor', TJSONNumber.Create(
              conERP.Query.FieldByName('VALOR_SERVICOS').AsFloat));
            vLote.AddElement(vRow);
            vPend.Add(vChaveEstado + '=' + vSituacao);

            if vLote.Count >= TAM_LOTE then
              if not EnviaLote then
                Exit;   // falhou: nao marca estado, re-tenta na proxima
          end;
          conERP.Query.Next;
        end;
      finally
        conERP.Free;
      end;

      EnviaLote;   // resto do lote

      TLog.GetInstance.INFO('TProcessa.ProcessaNfseERP',
        'NFS-e DO ERP: ' + IntToStr(vEnviadas) + ' situacao(oes) enviada(s).');
    finally
      vLote.Free;
      vPend.Free;
      vEstado.Free;
    end;
  except
    on E: Exception do
      // O canal NUNCA pode travar o resto do agente: loga e segue.
      TLog.GetInstance.ERRO('TProcessa.ProcessaNfseERP', 'ERRO: ' + E.ToString);
  end;
end;

{ Estado local dos DESCARTES, para so mandar o que mudou.

  Este canal nascia SEM estado: reenviava a lista inteira a cada 5 minutos, para
  sempre - 21 lotes em 2 horas num log real. Nao corrompia nada (o servidor faz
  upsert pela identidade, e ha teste travando isso), mas era trabalho jogado
  fora, e no cliente com muitos descartes vira trafego constante de graca.

  A chave espelha a IDENTIDADE que o servidor usa
  (ErpDiscardController: cnpj|modelo|serie|numero|mesano) para os dois lados
  concordarem sobre "e a mesma nota". O valor guardado e uma ASSINATURA
  (situacao + total): mudou a situacao ou o valor, reenvia; igual, pula. }
procedure TProcessa.GaranteTabelaDescartesERP;
var
  con: TConnection;
begin
  con := TConnection.Create(false);
  try
    con.Query.SQL.Text := 'create table if not exists descartes_erp ' +
      '(identidade text primary key, assinatura text)';
    con.Query.ExecSQL();
  finally
    con.Free;
  end;
end;

function TProcessa.EstadoDescartesERP: TDictionary<string, string>;
var
  con: TConnection;
begin
  Result := TDictionary<string, string>.Create;
  con := TConnection.Create(false);
  try
    con.Query.SQL.Text := 'select identidade, assinatura from descartes_erp';
    con.Query.Open();
    while not con.Query.Eof do
    begin
      Result.AddOrSetValue(con.Query.FieldByName('identidade').AsString,
        con.Query.FieldByName('assinatura').AsString);
      con.Query.Next;
    end;
  finally
    con.Free;
  end;
end;

{ SITUACAO que ESTE canal coleta, por tabela do ERP.

  So entra o que o portal nao descobre de outro jeito. Autorizada e cancelada
  chegam pelo XML (canal normal) e "inutilizada" do ERP e venda descartada, que
  tem canal proprio (ProcessaDescartesERP) - repetir aqui duplicaria o
  documento.

  ATENCAO: o dominio e POR TABELA. NFE_MASTER usa NUMEROS (1=Aberta,
  2=Autorizada, 3=Cancelada, 5=Inutilizada; 4 sem confirmacao) e NFCE_MASTER usa
  LETRAS (G=Gravada, T=Autorizada, C=Cancelada, I=Inutilizada, R=Rejeitada,
  D=DUPLICIDADE - nao denegada). Era por isso que o braco de NF-e estava inerte:
  procurava letra numa coluna de numero.

  O '4' da NF-e entra de proposito mesmo sem confirmacao: o servidor NAO o
  mapeia, so registra no log. Assim o primeiro cliente que tiver um revela o
  significado - em vez de o agente decidir por conta.

  Fora da lista de proposito: 'G'/'1' (gravada/aberta - nunca foi a SEFAZ, nao
  ha status a mostrar e quase nenhuma tem chave) e 'I'/'5' (descarte, que tem
  canal proprio).

  Quem manda no significado e o PHP (ErpStatusController): o agente so entrega a
  letra crua. Mapa que muda vira deploy da web, nao recompilacao aqui. }
procedure TProcessa.ProcessaStatusERP;
const
  TAM_LOTE = 200;
  SIT_NFCE = '''D'', ''O'', ''R''';
  SIT_NFE  = '''4'', ''O'', ''R''';
var
  Ini: TIniFile;
  vEstado: TDictionary<string, string>;
  vVistas: TDictionary<string, Boolean>;
  vLote: TJSONArray;
  vPend: TStringList;         // 'chave=situacao' confirmados apos msg=100
  conERP, conLocal: TConnection;
  vChave, vSituacao, vSql, vEmissao: string;
  vRow: TJSONObject;
  vBody: TJSONObject;
  vTabela: Integer;
  vEnviadas: Integer;

  procedure AdicionaLinha(const xChave, xSituacao, xEmissao: string;
    xCstat: Integer; const xMotivo: string);
  begin
    vRow := TJSONObject.Create;
    vRow.AddPair('chave', xChave);
    vRow.AddPair('situacao', xSituacao);
    if xEmissao <> '' then
      vRow.AddPair('emissao', xEmissao);
    if xCstat > 0 then
      vRow.AddPair('cstat', TJSONNumber.Create(xCstat));
    if xMotivo <> '' then
      vRow.AddPair('xmotivo', xMotivo);
    vLote.AddElement(vRow);
    vPend.Add(xChave + '=' + xSituacao);
  end;

  { A situacao ainda esta na lista que este canal coleta? Decide se a chave
    continua no estado local (para detectar mudanca) ou sai dele. }
  function SituacaoColetada(const xChave, xSituacao: string): Boolean;
  var
    vLista: string;
  begin
    if Copy(xChave, 21, 2) = '65' then
      vLista := SIT_NFCE
    else
      vLista := SIT_NFE;
    Result := Pos('''' + xSituacao + '''', vLista) > 0;
  end;

  function EnviaLote: Boolean;
  var
    i: Integer;
    vSit: string;
  begin
    Result := True;
    if vLote.Count = 0 then
      Exit;
    vBody := TJSONObject.Create;
    try
      vBody.AddPair('key', ApiKey);
      vBody.AddPair('rows', vLote);   // vBody passa a ser dono do array
      Result := UploadJSON(Url + 'api/docs/status-erp/upload', vBody.ToJSON);
    finally
      vBody.Free;                     // libera o lote junto
      vLote := TJSONArray.Create;     // proximo lote
    end;
    if Result then
    begin
      // So marca o estado local APOS msg=100 (disciplina do dedup atual).
      conLocal := TConnection.Create(false);
      try
        for i := 0 to vPend.Count - 1 do
        begin
          vSit := vPend.ValueFromIndex[i];
          // SITUACAO ainda na lista coletada -> guarda (para detectar MUDANCA na
          // proxima varredura). Saiu da lista -> some do estado: ja foi enviada,
          // nao ha mais o que reconciliar.
          if SituacaoColetada(vPend.Names[i], vSit) then
            conLocal.Query.SQL.Text :=
              'insert or replace into status_erp (chave, situacao) values (:c, :s)'
          else
            conLocal.Query.SQL.Text := 'delete from status_erp where chave = :c';
          conLocal.Query.ParamByName('c').AsString := vPend.Names[i];
          if SituacaoColetada(vPend.Names[i], vSit) then
            conLocal.Query.ParamByName('s').AsString := vSit;
          conLocal.Query.ExecSQL();
        end;
      finally
        conLocal.Free;
      end;
      Inc(vEnviadas, vPend.Count);
      TLog.GetInstance.INFO('TProcessa.ProcessaStatusERP',
        'LOTE STATUS ERP ENVIADO: ' + IntToStr(vPend.Count) + ' linha(s).');
    end;
    vPend.Clear;
  end;

begin
  // Recurso ligado so quando [BANCO_ERP] Database esta preenchido; e o bloco
  // roda no maximo a cada 5 minutos (status nao precisa de frescor de 30s e a
  // SELECT varre a tabela inteira do ERP - gentileza com o banco do cliente).
  Ini := TIniFile.Create(ChangeFileExt(Application.ExeName, '.ini'));
  try
    TConnection.ERPServer := Ini.ReadString('BANCO_ERP', 'SERVER', 'localhost');
    TConnection.ERPPort := Ini.ReadString('BANCO_ERP', 'PORT', '3050');
    TConnection.ERPDatabase := Ini.ReadString('BANCO_ERP', 'DATABASE', '');
    TConnection.ERPUser := Ini.ReadString('BANCO_ERP', 'USER', 'SYSDBA');
    TConnection.ERPPassword := Ini.ReadString('BANCO_ERP', 'PASSWORD', 'masterkey');
  finally
    Ini.Free;
  end;

  if Trim(TConnection.ERPDatabase) = '' then
  begin
    if not AvisoERPLogado then
    begin
      // Uma vez por execucao, mas com o CAMINHO do .ini lido: quem abre o log
      // no meio da rodada nao via este aviso e concluia que o canal estava
      // funcionando em silencio. Dizer QUAL .ini foi lido corta a duvida mais
      // comum - exe novo em pasta com .ini velho, sem a secao [BANCO_ERP].
      TLog.GetInstance.INFO('TProcessa.ProcessaStatusERP',
        'AVISO: [BANCO_ERP] DATABASE vazio em "' +
        ChangeFileExt(Application.ExeName, '.ini') +
        '" - os 3 canais do banco do ERP (status, NFS-e e descartes) estao DESLIGADOS.');
      AvisoERPLogado := True;
    end;
    Exit;
  end;

  if (UltimaVarreduraERP > 0) and
    (System.DateUtils.MinutesBetween(Now, UltimaVarreduraERP) < 5) then
    Exit;
  // Marca ANTES de varrer: o piso de 5 min contra o banco do ERP vale mesmo
  // com o portal fora (o estado local nao marcado ja garante a redeteccao).
  UltimaVarreduraERP := Now;

  try
    vEnviadas := 0;
    GaranteTabelaStatusERP;
    vEstado := EstadoStatusERP;
    vVistas := TDictionary<string, Boolean>.Create;
    vLote := TJSONArray.Create;
    vPend := TStringList.Create;
    try
      // 1) O/R atuais das duas tabelas do ERP: novidade ou mudanca entra no lote.
      for vTabela := 0 to 1 do
      begin
        if vTabela = 0 then
          vSql := 'select CHAVE, SITUACAO, DATA_EMISSAO, ' +
            'HORA_EMISSAO, ULTIMO_CSTAT, ' +
            'coalesce(ULTIMO_XMOTIVO, XERRO) as MOTIVO ' +
            'from NFCE_MASTER where SITUACAO in (' + SIT_NFCE + ')'
        else
          vSql := 'select CHAVE, SITUACAO, DATA_EMISSAO, ' +
            'HORA_EMISSAO, cast(null as integer) as ULTIMO_CSTAT, ' +
            'cast(null as varchar(10)) as MOTIVO ' +
            'from NFE_MASTER where SITUACAO in (' + SIT_NFE + ')';

        conERP := TConnection.Create(false, false, True);
        try
          conERP.Query.SQL.Text := vSql;
          conERP.Query.Open();
          while not conERP.Query.Eof do
          begin
            vChave := Trim(conERP.Query.FieldByName('CHAVE').AsString);
            vSituacao := Trim(conERP.Query.FieldByName('SITUACAO').AsString);
            if Length(vChave) = 44 then
            begin
              vVistas.AddOrSetValue(vChave, True);
              if (not vEstado.ContainsKey(vChave)) or
                (vEstado[vChave] <> vSituacao) then
              begin
                // DATA_EMISSAO NULL viraria '1899-12-30' no AsDateTime: manda vazio.
                // HORA_EMISSAO NULL nao e meia-noite: e "nao sei a hora". O
                // AsDateTime de campo nulo devolve 0, que virava '00:00:00' e o
                // portal mostrava "15/06/2026 00:00" como se fosse fato. Sem a
                // hora, manda so a data.
                if conERP.Query.FieldByName('DATA_EMISSAO').IsNull then
                  vEmissao := ''
                else
                begin
                  vEmissao := FormatDateTime('yyyy-mm-dd',
                    conERP.Query.FieldByName('DATA_EMISSAO').AsDateTime);
                  if not conERP.Query.FieldByName('HORA_EMISSAO').IsNull then
                    vEmissao := vEmissao + ' ' + FormatDateTime('hh:nn:ss',
                      conERP.Query.FieldByName('HORA_EMISSAO').AsDateTime);
                end;
                AdicionaLinha(vChave, vSituacao, vEmissao,
                  conERP.Query.FieldByName('ULTIMO_CSTAT').AsInteger,
                  Trim(conERP.Query.FieldByName('MOTIVO').AsString));
                if vLote.Count >= TAM_LOTE then
                  if not EnviaLote then
                    Exit;   // falhou: nao marca estado, re-tenta na proxima
              end;
            end;
            conERP.Query.Next;
          end;
        finally
          conERP.Free;
        end;
      end;

      // 2) Chaves que SAIRAM de O/R (estao no estado local mas nao voltaram na
      //    consulta): busca pontual da situacao atual para o portal atualizar.
      for vChave in vEstado.Keys do
        if not vVistas.ContainsKey(vChave) then
        begin
          if Copy(vChave, 21, 2) = '65' then
            vSql := 'select SITUACAO from NFCE_MASTER where CHAVE = :chave'
          else
            vSql := 'select SITUACAO from NFE_MASTER where CHAVE = :chave';
          conERP := TConnection.Create(false, false, True);
          try
            conERP.Query.SQL.Text := vSql;
            conERP.Query.ParamByName('chave').AsString := vChave;
            conERP.Query.Open();
            if not conERP.Query.Eof then
            begin
              vSituacao := Trim(conERP.Query.FieldByName('SITUACAO').AsString);
              if vSituacao <> '' then
              begin
                // Manda a situacao CRUA, seja letra ou numero: quem decide o
                // significado (e o que ignorar) e o servidor. Filtrar aqui foi o
                // que fez a NF-e nunca reconciliar - o teste so aceitava letra.
                AdicionaLinha(vChave, vSituacao, '', 0, '');
                if vLote.Count >= TAM_LOTE then
                  if not EnviaLote then
                    Exit;
              end
              else
                TLog.GetInstance.ERRO('TProcessa.ProcessaStatusERP',
                  'SITUACAO vazia na chave ' + vChave +
                  ' - mantida no estado local para nova tentativa.');
            end;
          finally
            conERP.Free;
          end;
        end;

      EnviaLote;   // resto do lote

      // Fecho SEMPRE, mesmo com zero linhas. Sem isto o canal era mudo: nao
      // dava para separar "desligado" de "nada novo" de "esperando o piso de
      // 5 min" olhando o log - e a varredura de pastas ja tinha essa linha
      // justamente por isso.
      TLog.GetInstance.INFO('TProcessa.ProcessaStatusERP',
        'STATUS DO ERP: ' + IntToStr(vEnviadas) + ' situacao(oes) enviada(s), ' +
        IntToStr(vVistas.Count) + ' na varredura.');
    finally
      vLote.Free;
      vPend.Free;
      vVistas.Free;
      vEstado.Free;
    end;
  except
    on E: Exception do
      // O canal novo NUNCA pode travar o resto do agente (spec): loga e segue.
      TLog.GetInstance.ERRO('TProcessa.ProcessaStatusERP', 'ERRO: ' + E.ToString);
  end;
end;

procedure TForm1.InciarComWindows(Iniciar: Boolean);
var
  Reg: TRegistry;
  S: string;
begin
  // HKCU\...\Run: vale para todas as versoes do Windows, dispensa admin, deteccao
  // de SO e path hardcoded (antes: HKLM exigia admin; "Windows 10" pegava tambem o
  // Win11; path C:\Users fixo).
  Reg := TRegistry.Create(KEY_READ or KEY_WRITE or KEY_WOW64_64KEY);
  try
    Reg.RootKey := HKEY_CURRENT_USER;
    if Reg.OpenKey('SOFTWARE\Microsoft\Windows\CurrentVersion\Run', True) then
    try
      S := '"' + Application.ExeName + '"';
      if Iniciar then
        Reg.WriteString('PortalContador', S)
      else if Reg.ValueExists('PortalContador') then
        Reg.DeleteValue('PortalContador');
    except
      on E: Exception do
        TLog.GetInstance.ERRO('InciarComWindows', E.Message);
    end;
  finally
    Reg.CloseKey;
    Reg.Free;
  end;
end;

function TForm1.ObterVersaoSO: String;
var
  vNome,
  vVersao,
  vCurrentBuild: String;
  Reg: TRegistry;
begin
  Reg := TRegistry.Create; //Criando um Registro na Memória
  Reg.Access := KEY_READ; //Colocando nosso Registro em modo Leitura
  Reg.RootKey := HKEY_LOCAL_MACHINE; //Definindo a Raiz

  //Abrindo a chave desejada
  Reg.OpenKey('\SOFTWARE\Microsoft\Windows NT\CurrentVersion\', true);

  //Obtendo os Parâmetros desejados
  vNome := Reg.ReadString('ProductName');
  vVersao := Reg.ReadString('CurrentVersion');
  vCurrentBuild := Reg.ReadString('CurrentBuild');

  Result  :=  vNome;
end;

function TForm1.UsuarioLogado: string;
var
  nsize: Cardinal;
  UserName: string;
begin
  nsize := 25;
  SetLength(UserName,nsize);
  if GetUserName(PChar(UserName), nsize) then
    begin
      SetLength(UserName,nsize-1);
      Result := UserName;
    end;
end;

end.