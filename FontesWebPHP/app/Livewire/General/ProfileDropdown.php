<?php

namespace App\Livewire\General;

use Livewire\Component;

class ProfileDropdown extends Component
{
    public function render()
    {
        return view('livewire.general.profile-dropdown');
    }

    public function logout()
    {
        auth()->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('auth.login');
    }
}
