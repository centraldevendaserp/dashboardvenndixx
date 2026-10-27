<?php

namespace App\Livewire\Panel\User;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;

class Index extends Component
{
    use WithPagination;

    public $user;

    /** Busca por nome, e-mail ou CNPJ/CPF de empresa vinculada. */
    public $search = '';

    protected $listeners = ['$refresh'];

    public function updatedSearch()
    {
        $this->resetPage('users');
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

        return view('livewire.panel.user.index', [
            'users' => User::withCount('companies')
                ->when($term !== '', function ($query) use ($term, $digits) {
                    $query->where(function ($q) use ($term, $digits) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");

                        if ($digits !== '') {
                            $q->orWhereHas('companies', function ($c) use ($digits) {
                                $c->where('cnpj_cpf', 'like', "%{$digits}%");
                            });
                        }
                    });
                })
                ->orderByRaw("CASE WHEN is_admin = 'S' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->paginate(
                    10,
                    ['id', 'name', 'email', 'is_admin'],
                    pageName: 'users'
                )
        ]);
    }

    public function paginationView()
    {
        return 'components.layouts.pagination';
    }

    public function deleteUser($id)
    {
        // ⚠️ Autoriza AQUI, não só no mount(): o mount() roda uma vez e as
        // chamadas seguintes chegam por /livewire/update, hidratando o
        // snapshot. Sem isto, admin rebaixado com a tela aberta segue apagando.
        abort_unless(auth('web')->user()?->isAdmin(), 403, 'Unauthorized action.');

        try {
            $user = User::find($id);

            if (is_null($user)) {
                $this->dispatch(
                    'eventCuteToast',
                    msg: "Usuario nao encontrado.",
                    code: 404
                );
                return;
            }

            if ($user->id === auth('web')->id()) {
                $this->dispatch(
                    'eventCuteToast',
                    msg: "Voce nao pode excluir a propria conta.",
                    code: 400
                );
                return;
            }

            if ($user->isAdmin() && User::where('is_admin', 'S')->count() <= 1) {
                $this->dispatch(
                    'eventCuteToast',
                    msg: "Nao e possivel excluir o ultimo administrador.",
                    code: 400
                );
                return;
            }

            $user->delete();

            $this->dispatch('$refresh')->self();
            $this->dispatch(
                'eventCuteToast',
                msg: "Excluído com sucesso",
                code: 200
            );
        } catch (\Exception $e) {
            // Detalhe da exceção fica no servidor; o cliente vê mensagem amigável.
            report($e);

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
