<?php

namespace App\Livewire\Panel\Company;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Index extends Component
{
    use WithPagination;

    public $user;

    /** Busca por razão social, nome fantasia ou CNPJ/CPF. */
    public $search = '';

    protected $listeners = ['$refresh'];

    public function updatedSearch()
    {
        $this->resetPage('companies');
    }

    public function mount()
    {
        $this->user = auth('web')->user();

        if (!$this->user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function render()
    {
        $term = trim((string) $this->search);
        $digits = preg_replace('/\D/', '', $term);

        return view('livewire.panel.company.index', [
            'companies' => Company::withCount('users')
                ->withoutGlobalScope('linked_user')
                ->when($term !== '', function ($query) use ($term, $digits) {
                    $query->where(function ($q) use ($term, $digits) {
                        $q->where('corporate_name', 'like', "%{$term}%")
                            ->orWhere('fantasy_name', 'like', "%{$term}%");

                        if ($digits !== '') {
                            $q->orWhere('cnpj_cpf', 'like', "%{$digits}%");
                        }
                    });
                })
                ->orderBy('corporate_name')
                ->paginate(
                    10,
                    ['id', 'cnpj_cpf', 'corporate_name', 'fantasy_name'],
                    pageName: 'companies'
                )
        ]);
    }

    public function paginationView()
    {
        return 'components.layouts.pagination';
    }

    public function deleteCompany($id)
    {
        // Mesma razão do deleteUser: o mount() não roda nas chamadas
        // seguintes, então a autorização fica no método.
        abort_unless(auth('web')->user()?->isAdmin(), 403, 'Unauthorized action.');

        try {
            DB::transaction(function () use ($id) {
                // findOrFail: id inexistente viraria "member function on null".
                $company = Company::findOrFail($id);

                $company->users()->detach();
                $company->delete();
            });

            $this->dispatch('$refresh')->self();

            $this->dispatch('eventCuteToast', msg: "Excluído com sucesso", code: 200);
        } catch (\Exception $e) {

            $errorDetails = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ];

            // Detalhe fica no log do servidor; não vaza para o cliente.
            Log::error('Error on delete company', $errorDetails);

            switch ($e->getCode()) {
                case "23000":
                    $this->dispatch(
                        'eventCuteToast',
                        msg: "Não foi possível deletar, pois existem dados relacionados.",
                        code: 23000
                    );
                    break;

                default:
                    $this->dispatch(
                        'eventCuteToast',
                        msg: "Não foi possível deletar.",
                        code: 500
                    );
                    break;
            }
        }
    }
}
