<?php

namespace App\Livewire\General;

use Livewire\Component;

class MenuSidebar extends Component
{
    public $user;

    public function mount()
    {
        $this->user = auth('web')->user();
    }

    public function render()
    {
        return view('livewire.general.menu-sidebar');
    }

    public function logout()
    {
        auth()->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('auth.login');
    }
}
