<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform:grant-admin {email : Correo de una cuenta ya registrada} {--revoke : Quitar el acceso en lugar de darlo}')]
#[Description('Da o quita el acceso de super admin al panel /plataforma')]
class GrantPlatformAdmin extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No existe una cuenta con ese correo. Regístrala primero en la web.');

            return self::FAILURE;
        }

        $user->is_platform_admin = ! $this->option('revoke');
        $user->save();

        $this->info($user->is_platform_admin
            ? "{$user->email} ahora es super admin. Debe activar la verificación en dos pasos para entrar a /plataforma."
            : "{$user->email} ya no es super admin.");

        return self::SUCCESS;
    }
}
