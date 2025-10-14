<?php

namespace App\Enums;

enum OrderControlEnum: string
{
    case PASIEN_BARU_MASUK     = 'NI';
    case PASIEN_PINDAH_RUANGAN = 'UI';
    case PASIEN_PULANG         = 'PI';

    public static function fromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }
}
