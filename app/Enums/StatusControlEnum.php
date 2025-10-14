<?php

namespace App\Enums;

enum StatusControlEnum: string
{
    case PASIEN_BARU_MASUK     = 'NI';
    case PASIEN_PINDAH_RUANGAN = 'UI';
    case PASIEN_PULANG         = 'PI';

    case STATUS_PASIEN_RAWAT_INAP  = 'IP';
    case STATUS_PASIEN_RAWAT_JALAN = 'OP';

    case MEDLEGAL_YA = 'Y';
    case MEDLEGAL_TIDAK = 'N';

    /**
     * Ambil daftar nilai valid enum (misal untuk validasi).
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Ambil daftar nama-nama enum.
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Ambil mapping nama => value
     */
    public static function nameValueMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->name => $case->value])
            ->toArray();
    }
}
