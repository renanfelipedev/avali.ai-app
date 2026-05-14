<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case TEACHER = 'teacher';
    case STUDENT = 'student';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrador',
            self::TEACHER => 'Professor',
            self::STUDENT => 'Estudante',
        };
    }
}
