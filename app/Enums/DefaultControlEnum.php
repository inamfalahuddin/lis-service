<?php

namespace App\Enums;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DefaultControlEnum
{
    public const KELAS_ID = 'DEFAULT:KELAS_ID';
    public const KELAS_KODE = 'DEFAULT:KELAS_KODE';
    public const KELAS_NAMA = 'DEFAULT:KELAS_NAMA';

    /**
     * Mendapatkan nilai default berdasarkan key
     *
     * @param string $key
     * @return mixed
     */
    public static function getValue(string $key)
    {
        switch ($key) {
            case self::KELAS_ID:
                return self::getKelasId();
            case self::KELAS_KODE:
                return self::getKelasKode();
            case self::KELAS_NAMA:
                return self::getKelasNama();
            default:
                return null;
        }
    }

    /**
     * Mendapatkan ID kelas default
     *
     * @return int|null
     */
    public static function getKelasId(): ?int
    {
        $result = self::executeDefaultKelasQuery();
        return $result ? (int) $result->id : null;
    }

    /**
     * Mendapatkan kode kelas default
     *
     * @return string|null
     */
    public static function getKelasKode(): ?string
    {
        $result = self::executeDefaultKelasQuery();
        return $result ? $result->kode : null;
    }

    /**
     * Mendapatkan nama kelas default
     *
     * @return string|null
     */
    public static function getKelasNama(): ?string
    {
        $result = self::executeDefaultKelasQuery();
        return $result ? $result->nama : null;
    }

    /**
     * Eksekusi query untuk mendapatkan data kelas default
     *
     * @return object|null
     */
    private static function executeDefaultKelasQuery(): ?object
    {
        try {
            return DB::connection('mysql2')
                ->table('m_kelas as k')
                ->select('k.id', 'k.kode', 'k.nama')
                ->join('sys_options as so', function ($join) {
                    $join->on('k.id', '=', 'so.option_value')
                        ->where('so.option_name', 'default_kelas_rawat_jalan');
                })
                ->first();
        } catch (\Exception $e) {
            Log::channel('default_control_enum')->error('Error fetching default kelas data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mendapatkan semua data kelas default dalam bentuk array
     *
     * @return array
     */
    public static function getAllKelasData(): array
    {
        $result = self::executeDefaultKelasQuery();

        if (!$result) {
            return [
                'id' => null,
                'kode' => null,
                'nama' => null
            ];
        }

        return [
            'id' => (int) $result->id,
            'kode' => $result->kode,
            'nama' => $result->nama
        ];
    }

    /**
     * Validasi apakah key tersedia
     *
     * @param string $key
     * @return bool
     */
    public static function isValidKey(string $key): bool
    {
        return in_array($key, [
            self::KELAS_ID,
            self::KELAS_KODE,
            self::KELAS_NAMA,
        ]);
    }

    /**
     * Daftar semua available keys
     *
     * @return array
     */
    public static function getAvailableKeys(): array
    {
        return [
            self::KELAS_ID,
            self::KELAS_KODE,
            self::KELAS_NAMA,
        ];
    }
}
