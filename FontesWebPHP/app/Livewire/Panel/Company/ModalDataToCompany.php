<?php

namespace App\Livewire\Panel\Company;

use Livewire\Component;
use Livewire\Attributes\Locked;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Helpers\Mask;

class ModalDataToCompany extends Component
{
    #[Locked]
    public $company_id;
    public $cnpj_cpf;
    public $corporate_name;
    public $fantasy_name;
    public $email;
    public $phone_number;
    public $public_place;
    public $home_number;
    public $complement;
    public $district;
    public $zip_code;
    public $county;
    public $uf;

    public $related_users = [];

    public $action;

    // Editar abre somente-leitura até "Habilitar edição"; criar já abre
    // liberado. É guarda de UX — o submit continua protegido por ensureAdmin().
    public bool $editEnabled = false;

    protected $listeners = ['eventAction'];

    protected $messages = [
        'corporate_name.required' => 'Obrigatório.',
        'cnpj_cpf.required' => 'Obrigatório.',
    ];

    public function mount()
    {
        // Empresa é área 100% admin: guarda já no boot.
        $this->ensureAdmin();
    }

    public function render()
    {
        return view('livewire.panel.company.modal-data-to-company', [
            'users' => DB::table('users')->select('id', 'name')->get(),
        ]);
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName, $this->rulesCompany());
    }

    public function eventAction($action, $company_id = null)
    {
        // /livewire/update é endereçável direto: reforça a autorização aqui.
        $this->ensureAdmin();

        $this->action = $action;

        $this->resetCompany();

        // Editar abre travado; criar já abre liberado.
        $this->editEnabled = ($action !== 'edit');

        if ($company_id) {
            $this->company_id = $company_id;
            $this->edit();
        } else {
            $this->dispatch('syncCompanyModalFields', related_users: []);
        }
    }

    /** Destrava os campos para edição (e re-marca os chips após o morph). */
    public function enableEdit(): void
    {
        $this->ensureAdmin();

        $this->editEnabled = true;

        $this->dispatch(
            'syncCompanyModalFields',
            related_users: array_map('strval', $this->related_users)
        );
    }

    public function submit()
    {
        $this->ensureAdmin();

        $this->updateOrCreate();
    }

    protected function updateOrCreate()
    {
        $this->validate($this->rulesCompany());

        $data = [
            'corporate_name' => $this->corporate_name,
            'fantasy_name' => $this->fantasy_name,
            'cnpj_cpf' => preg_replace("/\D/", "", $this->cnpj_cpf),
            'email' => $this->email,
            'phone_number' => preg_replace("/\D/", "", $this->phone_number),
            'public_place' => $this->public_place,
            'home_number' => $this->home_number,
            'complement' => $this->complement,
            'district' => $this->district,
            'zip_code' => $this->zip_code,
            'county' => $this->county,
            'uf' => $this->uf,
        ];

        try {
            $company = Company::updateOrCreate(['id' => $this->company_id], $data);
            $company->users()->sync($this->related_users);

            if ($this->action == 'store') {
                $this->resetCompany();
            }

            $this->dispatch('$refresh')->to(Index::class);

            $this->dispatch('eventCloseModal', modalId: "#modal-data-to-company");

            $this->dispatch(
                'eventCuteToast',
                msg: $this->action == 'edit' ? "Atualizado com sucesso." : "Cadastrado com sucesso.",
                code: 200
            );
        } catch (\Exception $e) {
            // Detalhe vai para o log; o cliente não vê stack nem erro.
            report($e);

            switch ($e->getCode()) {
                case "23000":
                    $this->dispatch(
                        'eventCuteToast',
                        msg: "Verifique os dados, pois alguns já estão cadastrados.",
                        code: 23000
                    );
                    break;

                default:
                    $this->dispatch(
                        'eventCuteToast',
                        msg: "Não foi possível salvar.",
                        code: 500
                    );
                    break;
            }
        }
    }

    protected function edit()
    {
        $company = Company::find($this->company_id);

        if ($company) {
            $this->corporate_name = $company['corporate_name'];
            $this->fantasy_name = $company['fantasy_name'];
            $this->cnpj_cpf = Mask::run($company['cnpj_cpf'], strlen($company['cnpj_cpf']) > 11 ? '##.###.###/####-##' : '###.###.###-##');
            $this->email = $company['email'];
            $this->phone_number =  Mask::run($company['phone_number'], strlen($company['phone_number']) > 10 ? '(##) #####-####' : '(##) ####-####');
            $this->public_place = $company['public_place'];
            $this->home_number = $company['home_number'];
            $this->complement = $company['complement'];
            $this->district = $company['district'];
            $this->zip_code = $company['zip_code'];
            $this->county = $company['county'];
            $this->uf = $company['uf'];
            $this->related_users = $company->users()->pluck('id')->toArray();

            $this->dispatch(
                'syncCompanyModalFields',
                related_users: array_map('strval', $this->related_users)
            );
        }
    }

    protected function resetCompany()
    {
        $this->reset([
            'company_id',
            'corporate_name',
            'fantasy_name',
            'cnpj_cpf',
            'email',
            'phone_number',
            'public_place',
            'home_number',
            'complement',
            'district',
            'zip_code',
            'county',
            'uf',
            'related_users'
        ]);
    }

    protected function rulesCompany()
    {
        $rules = [
            'corporate_name' => 'required',
            'cnpj_cpf' => 'required',
        ];

        return $rules;
    }

    /** Preenche o endereço pelo CEP (BrasilAPI, com fallback ViaCEP). */
    public function buscarCep()
    {
        $this->ensureAdmin();

        $cep = preg_replace('/\D/', '', (string) $this->zip_code);

        if (strlen($cep) !== 8) {
            $this->dispatch('eventCuteToast', msg: "Informe um CEP válido (8 dígitos).", code: 400);
            return;
        }

        try {
            $endereco = $this->consultarCepBrasilApi($cep) ?? $this->consultarCepViaCep($cep);

            if ($endereco === null) {
                $this->dispatch('eventCuteToast', msg: "CEP não encontrado.", code: 404);
                return;
            }

            // Só sobrescreve com valores não-vazios retornados pela API.
            $this->setIfNotEmpty('public_place', $endereco['public_place'] ?? null);
            $this->setIfNotEmpty('district', $endereco['district'] ?? null);
            $this->setIfNotEmpty('county', $endereco['county'] ?? null);
            $this->setIfNotEmpty('uf', $endereco['uf'] ?? null);

            $this->dispatch('eventCuteToast', msg: "Endereço preenchido pelo CEP.", code: 200);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('eventCuteToast', msg: "Não foi possível consultar o CEP agora. Tente novamente.", code: 500);
        }
    }

    /** Preenche os dados cadastrais pelo CNPJ (BrasilAPI). */
    public function buscarCnpj()
    {
        $this->ensureAdmin();

        $cnpj = preg_replace('/\D/', '', (string) $this->cnpj_cpf);

        if (strlen($cnpj) !== 14) {
            $this->dispatch('eventCuteToast', msg: "Informe um CNPJ válido (14 dígitos).", code: 400);
            return;
        }

        try {
            $resp = Http::timeout(10)->acceptJson()->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

            if (!$resp->successful()) {
                $this->dispatch('eventCuteToast', msg: "CNPJ não encontrado.", code: 404);
                return;
            }

            $data = $resp->json();

            if (!is_array($data)) {
                $this->dispatch('eventCuteToast', msg: "CNPJ não encontrado.", code: 404);
                return;
            }

            // Só sobrescreve com valores não-vazios retornados pela API.
            $this->setIfNotEmpty('corporate_name', $data['razao_social'] ?? null);
            $this->setIfNotEmpty('fantasy_name', $data['nome_fantasia'] ?? null);
            $this->setIfNotEmpty('zip_code', $data['cep'] ?? null);
            $this->setIfNotEmpty('public_place', $data['logradouro'] ?? null);
            $this->setIfNotEmpty('home_number', $data['numero'] ?? null);
            $this->setIfNotEmpty('complement', $data['complemento'] ?? null);
            $this->setIfNotEmpty('district', $data['bairro'] ?? null);
            $this->setIfNotEmpty('county', $data['municipio'] ?? null);
            $this->setIfNotEmpty('uf', $data['uf'] ?? null);
            $this->setIfNotEmpty('phone_number', $data['ddd_telefone_1'] ?? null);
            $this->setIfNotEmpty('email', $data['email'] ?? null);

            $this->dispatch('eventCuteToast', msg: "Dados preenchidos pelo CNPJ.", code: 200);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('eventCuteToast', msg: "Não foi possível consultar o CNPJ agora. Tente novamente.", code: 500);
        }
    }

    /** Consulta o CEP na BrasilAPI (v2); null em falha. */
    protected function consultarCepBrasilApi(string $cep): ?array
    {
        $resp = Http::timeout(8)->acceptJson()->get("https://brasilapi.com.br/api/cep/v2/{$cep}");

        if (!$resp->successful()) {
            return null;
        }

        $data = $resp->json();

        if (!is_array($data)) {
            return null;
        }

        return [
            'public_place' => $data['street'] ?? null,
            'district' => $data['neighborhood'] ?? null,
            'county' => $data['city'] ?? null,
            'uf' => $data['state'] ?? null,
        ];
    }

    /** Fallback de CEP no ViaCEP ("erro":true = não encontrado). */
    protected function consultarCepViaCep(string $cep): ?array
    {
        $resp = Http::timeout(8)->acceptJson()->get("https://viacep.com.br/ws/{$cep}/json/");

        if (!$resp->successful()) {
            return null;
        }

        $data = $resp->json();

        if (!is_array($data) || ($data['erro'] ?? false)) {
            return null;
        }

        return [
            'public_place' => $data['logradouro'] ?? null,
            'district' => $data['bairro'] ?? null,
            'county' => $data['localidade'] ?? null,
            'uf' => $data['uf'] ?? null,
        ];
    }

    /** Atribui à propriedade só quando o valor não é vazio. */
    protected function setIfNotEmpty(string $property, $value): void
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value !== null && $value !== '') {
            $this->{$property} = $value;
        }
    }

    /** Empresa é área 100% admin: bloqueia acesso direto por /livewire/update. */
    protected function ensureAdmin(): void
    {
        if (!auth('web')->user()?->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
    }
}
