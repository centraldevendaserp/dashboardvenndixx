<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Senha conhecida só em ambiente local; fora dele, aleatória, para não
        // criar credencial fixa.
        $isLocal = app()->environment('local');
        $password = $isLocal ? 'password' : Str::password(16);

        DB::table('users')->insert([
            'name' => 'Master',
            'email' => "master@mail.com",
            'is_admin' => "S",
            'password' => bcrypt($password),
        ]);

        if (! $isLocal) {
            $this->command?->warn("UserSeeder: master@mail.com criado com senha aleatória: {$password}");
        }
    }
}
