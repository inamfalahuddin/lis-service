<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MPasien extends Model
{
    use HasFactory;

    protected $connection = 'mysql2';
    // Nama tabel sesuai database
    protected $table = 'm_pasien';

    // Primary key sesuai tabel kamu (id, bukan _id)
    protected $primaryKey = 'id';

    // Jika primary key bukan auto increment integer, set false
    public $incrementing = true;

    // Jika primary key integer, tipe data key adalah int
    protected $keyType = 'int';

    // Jika kamu ingin timestamps otomatis, pastikan kolomnya ada di tabel
    public $timestamps = false; // karena tabel kamu pakai created_at tapi nullable, sesuaikan jika mau

    // Kolom yang bisa diisi massal (sesuaikan kebutuhan)
    protected $fillable = [
        'uid',
        'no_rm',
        'title_id',
        'nama',
        'jenis_kelamin',
        'tanda_tangan',
        'kewarganegaraan',
        'alamat',
        'provinsi_id',
        'kabupaten_id',
        'kecamatan_id',
        'kelurahan_id',
        'kodepos',
        'jenis_identitas',
        'no_identitas',
        'jenis_telepon_1',
        'no_telepon_1',
        'jenis_telepon_2',
        'no_telepon_2',
        'tempat_lahir',
        'tanggal_lahir',
        'golongan_darah',
        'agama',
        'suku',
        'status_kawin',
        'pendidikan_id',
        'pekerjaan_id',
        'deposit',
        'saldo',
        'foto',
        'status',
        'created_at',
        'created_by',
        'update_at',
        'update_by',
        'deleted',
        'delete_at',
        'delete_by'
    ];

    // Jika ingin sembunyikan atribut tertentu di output API, tambahkan ke sini
    protected $hidden = [
        // contoh: 'tanda_tangan', 'foto'
    ];

    /**
     * Validation rules, kamu bisa isi sesuai kebutuhan
     */
    public function getValidationRules()
    {
        return [
            'uid' => 'required|string|max:64|unique:m_pasien,uid,' . $this->id,
            'no_rm' => 'required|string|max:20|unique:m_pasien,no_rm,' . $this->id,
            'nama' => 'required|string|max:60',
            'jenis_kelamin' => 'required|integer|in:1,2',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string|max:255',
            'no_identitas' => 'nullable|string|max:30',
            'no_telepon_1' => 'nullable|string|max:25',
            // Tambah rules lain sesuai kebutuhan
        ];
    }
}
