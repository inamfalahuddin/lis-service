<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TLabRegister extends Model
{
    use HasFactory;

    protected $connection = 'mysql2';
    protected $table = 't_lab_register';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    // Laravel otomatis mengelola created_at dan updated_at, tapi di tabel kamu kolomnya sedikit berbeda, 
    // jadi kita matikan timestamps otomatis dan atur manual jika perlu
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'rujukan_id',
        'pelayanan_id',
        'jenis_pasien',
        'kode_transaksi',
        'tanggal_registrasi',
        'tanggal_pengambilan_sample',
        'pasien_id',
        'nama',
        'jenis_kelamin',
        'alamat',
        'no_identitas',
        'telepon',
        'tanggal_lahir',
        'dokter_id',
        'dokter_perujuk_id',
        'dokter_perujuk',
        'rujukan_dari',
        'layanan_id',
        'cara_bayar_id',
        'kontraktor_id',
        'asuransi_id',
        'no_jaminan',
        'ruang_id',
        'kelas_id',
        'bed_id',
        'catatan',
        'diagnosa',
        'cito',
        'diagnosa_id',
        'status',
        'status_antrian',
        'status_antrian_at',
        'batal_alasan',
        'batal_at',
        'batal_by',
        'created_at',
        'created_by',
        'update_at',
        'update_by',
        'deleted_flag',
        'lis',
    ];

    // Jika ingin menyesuaikan format tanggal otomatis (cast)
    protected $casts = [
        'tanggal_registrasi' => 'datetime',
        'tanggal_pengambilan_sample' => 'datetime',
        'tanggal_lahir' => 'date',
        'status_antrian_at' => 'datetime',
        'batal_at' => 'datetime',
        'created_at' => 'datetime',
        'update_at' => 'datetime',
        'cito' => 'boolean',
        'status' => 'integer',
        'deleted_flag' => 'boolean',
        'lis' => 'boolean',
    ];

    // Jika mau definisikan rule validasi di model (optional)
    public function getValidationRules()
    {
        return [
            'uid' => 'required|string|size:36|unique:t_lab_register,uid,' . $this->id,
            'kode_transaksi' => 'nullable|string|max:32|unique:t_lab_register,kode_transaksi,' . $this->id,
            'jenis_pasien' => 'nullable|in:rs,non_rs',
            'jenis_kelamin' => 'required|integer|in:0,1', // sesuai kolom kamu
            'cara_bayar_id' => 'required|integer',
            'status' => 'required|integer|in:0,1,2,3',
            // rules lain sesuai kebutuhan...
        ];
    }
}
