<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * ⚠️ `is_admin` fica FORA daqui de propósito (escalonamento de privilégio):
     * quem grava é o código que já autorizou, setando na instância.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Excluir o usuário revoga os tokens do agente dele: token órfão
        // continuaria autenticando no CheckSystem.
        static::deleting(function (User $user) {
            $user->tokens()->delete();
        });
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'user_company');
    }

    public function isAdmin()
    {
        return $this->is_admin === "S";
    }
}
