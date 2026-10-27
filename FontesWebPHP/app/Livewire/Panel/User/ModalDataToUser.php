<?php

namespace App\Livewire\Panel\User;

use Livewire\Component;
use Livewire\Attributes\Locked;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ModalDataToUser extends Component
{
    // Só o servidor define o alvo (eventAction): impede trocar o usuário pelo
    // payload do /livewire/update.
    #[Locked]
    public $user_id;
    public $name;
    public $email;
    public $password;
    // Default 'N': sem ele o select2 do campo Administrador abre vazio e a
    // validação falha sem mensagem. edit() sobrescreve com o valor do usuário.
    public $is_admin = 'N';
    public $related_companies = [];
    public $action;

    // Modo da tela, calculado no servidor (eventAction):
    // create | manage | profile | password | self_locked
    public $mode = 'create';

    // "Alterar senha": só a senha é editável. Auto-edição: não pode mexer no próprio admin.
    public $passwordOnly = false;
    public $lockAdmin = false;
    public $password_confirmation;

    // Gestão abre somente-leitura até "Habilitar edição"; nos demais modos é
    // true. É guarda de UX — o submit continua protegido no servidor.
    public bool $editEnabled = true;

    // Token do agente (Sanctum): só admin gera/revoga. O texto puro aparece
    // UMA vez e nunca é persistido em claro.
    public $tokenInstallationName = 'Instalação principal';
    public $generatedToken = null;
    public $generatedTokenName = null;
    public bool $showTokenPanel = false;
    // true quando o painel do token abre logo após cadastrar um usuário novo.
    public bool $userJustCreated = false;

    protected $listeners = ['eventAction'];

    protected $messages = [
        'name.required' => 'Obrigatório.',
        'password.required' => 'Obrigatório.',
        'is_admin.required' => 'Obrigatório.',
        'email.required' => 'Obrigatório.',
        'email.email' => 'E-mail inválido.',
        'password.confirmed' => 'A confirmação da senha não confere.',
    ];

    public function render()
    {
        $linkedCompanies = collect();

        if ($this->mode === 'profile' && !empty($this->user_id)) {
            $linkedCompanies = DB::table('user_company')
                ->join('companies', 'companies.id', '=', 'user_company.company_id')
                ->where('user_company.user_id', $this->user_id)
                ->orderBy('companies.corporate_name')
                ->get(['companies.corporate_name', 'companies.fantasy_name']);
        }

        $agentTokens = collect();
        if (Auth::user()?->is_admin === 'S'
            && in_array($this->mode, ['create', 'manage'], true)
            && !empty($this->user_id)) {
            $agentTokens = User::find($this->user_id)?->tokens()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'last_used_at', 'created_at']) ?? collect();
        }

        // A carga acompanha a mesma condição da tela: quem não vê a grade não
        // consulta as empresas. Este componente é montado em toda página do
        // painel, e a consulta é crua (fura o global scope `linked_user`).
        $podeEditarEmpresas = Auth::user()?->isAdmin()
            && in_array($this->mode, ['create', 'manage', 'self_locked', 'profile'], true);

        return view('livewire.panel.user.modal-data-to-user', [
            'emailComMesmoNome' => $this->emailComMesmoNome(),
            'companies' => $podeEditarEmpresas
                ? DB::table('companies')->select('id', 'corporate_name', 'fantasy_name')->get()
                : collect(),
            'linkedCompanies' => $linkedCompanies,
            'agentTokens' => $agentTokens,
        ]);
    }

    /**
     * E-mail de outro usuário que já usa este nome — ou null. Avisa, não
     * bloqueia: nome repetido é legítimo, quem identifica é o e-mail.
     */
    protected function emailComMesmoNome(): ?string
    {
        $nome = trim((string) $this->name);

        if ($nome === '' || ! in_array($this->mode, ['create', 'manage'], true)) {
            return null;
        }

        return User::where('name', $nome)
            ->when($this->user_id, fn ($q) => $q->whereKeyNot($this->user_id))
            ->value('email');
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName, $this->rulesUser());
    }

    public function eventAction($action = null, $user_id = null, $focus = null, $mode = null)
    {
        // Dispatch por objeto chega como parâmetro NOMEADO; o 'store' chega
        // posicional. O 1º argumento como array é o caso legado.
        if (is_array($action)) {
            $user_id = $action['user_id'] ?? $user_id;
            $focus = $action['focus'] ?? $focus;
            $mode = $action['mode'] ?? $mode;
            $action = $action['action'] ?? null;
        } elseif (is_object($action)) {
            $user_id = $action->user_id ?? $user_id;
            $focus = $action->focus ?? $focus;
            $mode = $action->mode ?? $mode;
            $action = $action->action ?? null;
        }

        $requestedMode = $mode; // do cliente: só 'profile' é significativo

        $this->action = $action;

        $this->resetUser();

        $this->passwordOnly = ($focus === 'password');

        if (!empty($user_id)) {
            $this->user_id = $user_id;
        }

        // Autorização no servidor: todo componente Livewire é endereçável por
        // /livewire/update, então a guarda da página-índice não basta.
        $this->authorizeUserAction();

        if (!empty($this->user_id)) {
            $this->edit();
        }

        $isSelf = !empty($this->user_id) && (int) $this->user_id === (int) Auth::id();

        // editar a si mesmo: não pode alterar o próprio nível de administrador
        $this->lockAdmin = $isSelf;

        // 'profile' só quando a própria conta é aberta pela navbar; pela lista
        // /panel/users vira 'self_locked' (somente leitura).
        if ($this->passwordOnly) {
            $this->mode = 'password';
        } elseif ($this->action === 'store') {
            $this->mode = 'create';
        } elseif ($isSelf) {
            $this->mode = ($requestedMode === 'profile') ? 'profile' : 'self_locked';
        } else {
            $this->mode = 'manage';
        }

        // Gestão de outro usuário abre travada; os demais modos vêm liberados.
        $this->editEnabled = $this->mode !== 'manage';

        $manageLocked = $this->mode === 'manage' && ! $this->editEnabled;

        // Configura o modal no cliente (foco + campos travados), já com o
        // select2 inicializado.
        $this->dispatch(
            'configureUserModal',
            focus: $focus,
            lockInputs: in_array($this->mode, ['password', 'self_locked'], true) || $manageLocked,
            lockAdmin: $this->lockAdmin
        );

        if (empty($user_id)) {
            $this->dispatch(
                'syncUserModalFields',
                is_admin: $this->is_admin,
                related_companies: []
            );
        }
    }

    /** Destrava a gestão para edição (reabilita campos/select2 e re-marca chips). */
    public function enableEdit(): void
    {
        $this->authorizeUserAction();

        if ($this->mode !== 'manage') {
            return;
        }

        $this->editEnabled = true;

        $this->dispatch(
            'configureUserModal',
            focus: null,
            lockInputs: false,
            lockAdmin: $this->lockAdmin
        );

        $this->dispatch(
            'syncUserModalFields',
            is_admin: $this->is_admin,
            related_companies: array_map('strval', $this->related_companies)
        );
    }

    public function submit()
    {
        $this->authorizeUserAction();
        $this->updateOrCreate();
    }

    protected function updateOrCreate()
    {
        $isSelf = !empty($this->user_id) && (int) $this->user_id === (int) Auth::id();

        // Modo "Alterar senha": só a senha muda (própria conta), com confirmação.
        if ($this->passwordOnly && $this->action == 'edit' && !empty($this->user_id)) {
            abort_unless($isSelf, 403, 'Unauthorized action.');

            $this->validate(['password' => 'required|min:8|confirmed']);

            User::whereKey($this->user_id)->update(['password' => bcrypt($this->password)]);

            $this->dispatch('$refresh')->to(Index::class);
            $this->dispatch('eventCloseModal', modalId: "#modal-data-to-user");
            $this->dispatch('eventCuteToast', msg: "Senha atualizada com sucesso.", code: 200);

            return;
        }

        // Perfil: só nome/e-mail. Nunca toca em is_admin nem nas empresas.
        if ($isSelf) {
            $this->validate([
                'name' => 'required',
                'email' => 'required|email',
            ]);

            try {
                $selfUser = User::findOrFail($this->user_id);
                $selfUser->update([
                    'name' => $this->name,
                    'email' => $this->email,
                ]);

                // Só admin mexe nas empresas do próprio perfil — senão o
                // usuário se auto-vincularia a qualquer empresa.
                if ($this->currentUser()?->isAdmin()) {
                    // admin no próprio perfil -> vinculado a TODAS as empresas
                    $selfUser->companies()->sync($this->companiesToSync(true));
                }

                $this->dispatch('$refresh')->to(Index::class);
                // atualiza a saudação "Olá, <nome>" no header sem reload
                $this->dispatch('eventProfileUpdated', name: $this->name);
                $this->dispatch('eventCloseModal', modalId: "#modal-data-to-user");
                $this->dispatch('eventCuteToast', msg: "Atualizado com sucesso.", code: 200);
            } catch (\Exception $e) {
                report($e);

                if ($e->getCode() === "23000" || $e->getCode() === 23000) {
                    $this->dispatch('eventCuteToast', msg: "Verifique os dados, pois alguns já estão cadastrados.", code: 23000);
                } else {
                    $this->dispatch('eventCuteToast', msg: "Não foi possível salvar.", code: 500);
                }
            }

            return;
        }

        // Gestão completa (criar / editar outro) — exclusiva de administrador.
        abort_unless($this->currentUser()?->isAdmin(), 403, 'Unauthorized action.');

        $this->validate($this->rulesUser());

        $data = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->action == 'edit') {
            if (!empty($this->password)) {
                $data['password'] = bcrypt($this->password);
            }
        } else {
            $data['password'] = bcrypt($this->password);
        }

        try {
            $user = !empty($this->user_id)
                ? User::findOrFail($this->user_id)
                : new User();

            $user->fill($data); // name/e-mail/senha (mass-assignable)

            // is_admin fica fora do $fillable: setado na instância.
            $user->is_admin = $this->is_admin;

            $user->save();

            // Admin -> vinculado a TODAS as empresas; usuário comum -> só as marcadas.
            $user->companies()->sync($this->companiesToSync($this->is_admin === 'S'));

            if ($this->action == 'store') {
                // 1ª chave da instalação: mostra o token uma vez, sem fechar o
                // modal (é o valor que vai para o Key= do .ini do cliente).
                $this->issueAgentToken($user);
                $this->userJustCreated = true;
                $this->user_id = $user->id;
                $this->action = 'edit';

                $this->dispatch('$refresh')->to(Index::class);
                $this->dispatch('eventCuteToast', msg: "Cadastrado com sucesso.", code: 200);

                return;
            }

            $this->dispatch('$refresh')->to(Index::class);
            $this->dispatch('eventCloseModal', modalId: "#modal-data-to-user");
            $this->dispatch('eventCuteToast', msg: "Atualizado com sucesso.", code: 200);
        } catch (\Exception $e) {
            report($e);

            switch ($e->getCode()) {
                case "23000":
                case 23000:
                    $this->dispatch('eventCuteToast', msg: "Verifique os dados, pois alguns já estão cadastrados.", code: 23000);
                    break;

                default:
                    $this->dispatch('eventCuteToast', msg: "Não foi possível salvar.", code: 500);
                    break;
            }
        }
    }

    /**
     * Cria um token com a ability agent:upload e prepara a exibição única.
     * Devolve false se o nome já existe PARA ESTE usuário: o nome identifica a
     * instalação, e repetido não dá para saber qual chave revogar. Entre
     * usuários diferentes o mesmo nome é normal.
     */
    private function issueAgentToken(User $user): bool
    {
        $name = trim((string) $this->tokenInstallationName) ?: 'Instalação principal';

        if ($user->tokens()->where('name', $name)->exists()) {
            $this->addError('tokenInstallationName', "Este usuário já tem uma chave chamada \"{$name}\". Use outro nome.");

            return false;
        }

        $new = $user->createToken($name, ['agent:upload']);

        $this->generatedToken = $new->plainTextToken;
        $this->generatedTokenName = $name;
        $this->showTokenPanel = true;
        $this->tokenInstallationName = 'Instalação principal';

        return true;
    }

    /**
     * Gerar/revogar chave exige "Habilitar edição". O bloco das chaves fica
     * FORA do <fieldset disabled>, então o @disabled do HTML não o alcança.
     *
     * ⚠️ A checagem tem de ser no servidor: `editEnabled` é propriedade pública
     * e o cliente a define à vontade; só enableEdit() reautoriza como admin.
     */
    protected function exigeEdicaoHabilitada(): void
    {
        abort_if($this->mode === 'manage' && ! $this->editEnabled, 403, 'Habilite a edicao primeiro.');
    }

    /** Gera uma nova chave para o usuário-alvo (modo manage). Admin-only. */
    public function generateAgentToken(): void
    {
        abort_unless($this->currentUser()?->isAdmin(), 403, 'Unauthorized action.');
        abort_if(empty($this->user_id), 403, 'Unauthorized action.');
        $this->exigeEdicaoHabilitada();

        $this->userJustCreated = false; // geração no manage não é um cadastro novo
        $this->issueAgentToken(User::findOrFail($this->user_id));
    }

    /** Revoga (apaga) uma chave do usuário-alvo. Admin-only; escopo pelo dono. */
    public function revokeAgentToken($tokenId): void
    {
        abort_unless($this->currentUser()?->isAdmin(), 403, 'Unauthorized action.');
        abort_if(empty($this->user_id), 403, 'Unauthorized action.');
        $this->exigeEdicaoHabilitada();

        // whereKey dentro da relação: só apaga token do próprio alvo.
        User::findOrFail($this->user_id)->tokens()->whereKey($tokenId)->delete();

        $this->dispatch('eventCuteToast', msg: 'Chave revogada.', code: 200);
    }

    /** Fecha o painel do token e o modal (reseta o estado transitório). */
    public function finishTokenPanel(): void
    {
        $this->reset(['generatedToken', 'generatedTokenName', 'showTokenPanel']);
        $this->resetUser();
        $this->dispatch('$refresh')->to(Index::class);
        $this->dispatch('eventCloseModal', modalId: "#modal-data-to-user");
    }

    protected function edit()
    {
        $user = User::find($this->user_id);

        if ($user) {
            $this->name = $user->name;
            $this->email = $user->email;
            $this->is_admin = $user->is_admin;
            // Admin enxerga todas as empresas: abre com todas marcadas.
            $this->related_companies = $user->is_admin === 'S'
                ? \App\Models\Company::pluck('id')->all()
                : $user->companies()->pluck('id')->toArray();

            $this->dispatch(
                'syncUserModalFields',
                is_admin: $this->is_admin,
                related_companies: array_map('strval', $this->related_companies)
            );
        }
    }

    protected function resetUser()
    {
        $this->reset([
            'user_id', 'name', 'email', 'password', 'password_confirmation', 'is_admin', 'related_companies',
            'generatedToken', 'generatedTokenName', 'showTokenPanel', 'tokenInstallationName', 'userJustCreated',
        ]);
        $this->related_companies = [];
    }

    /** Normaliza $related_companies para o sync(): array de ids, sem nulos/vazios. */
    protected function normalizeRelatedCompanies(): array
    {
        $related = $this->related_companies ?? [];

        if ($related instanceof \Illuminate\Support\Collection) {
            $related = $related->toArray();
        }
        if (! is_array($related)) {
            $related = [$related];
        }

        return array_values(array_filter($related, fn ($v) => $v !== null && $v !== ''));
    }

    /** Vínculo a gravar: admin recebe todas as empresas; comum, só as marcadas. */
    protected function companiesToSync(bool $isAdmin): array
    {
        return $isAdmin
            ? \App\Models\Company::pluck('id')->all()
            : $this->normalizeRelatedCompanies();
    }

    protected function rulesUser()
    {
        $rules = [
            'name' => 'required',
            'email' => 'required|email',
        ];

        if ($this->currentUser()->isAdmin()) {
            $rules['is_admin'] = 'required|in:S,N';
        }

        if ($this->action == 'store') {
            // min:8, a mesma régua do /install e do reset de senha.
            $rules['password'] = 'required|min:8';
        }

        return $rules;
    }

    private function currentUser()
    {
        return Auth::user();
    }

    /**
     * Autorização do modal: admin gerencia qualquer usuário; não-admin só a
     * PRÓPRIA conta (e a gestão completa ainda é barrada no updateOrCreate).
     * Qualquer outro caso é 403.
     */
    private function authorizeUserAction(): void
    {
        $current = auth('web')->user();

        if ($current?->isAdmin()) {
            return;
        }

        $isSelf = !empty($this->user_id) && (int) $this->user_id === (int) ($current?->id);

        abort_unless($current && $this->action === 'edit' && $isSelf, 403, 'Unauthorized action.');
    }
}
