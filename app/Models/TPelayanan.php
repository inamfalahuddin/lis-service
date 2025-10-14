<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TPelayanan extends Model
{
    use HasFactory;

    protected $connection = 'mysql2';
    protected $table = 't_pelayanan';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false; // Karena kolom created_at dan update_at kamu custom nama dan nullable

    protected $fillable = [
        'uid',
        'tanggal',
        'no_register',
        'no_antrian',
        'jam_praktek_id',
        'pasien_id',
        'pegawai_id',
        'rujukan_dari',
        'nama_perujuk',
        'rumah_sakit_id',
        'rumah_sakit',
        'cara_bayar_id',
        'perusahaan_id',
        'no_jaminan',
        'no_kartu',
        'penjamin_id',
        'penjamin_perusahaan_id',
        'penjamin_no_jaminan',
        'jenis_perawatan',
        'penjamin_bagian',
        'penjamin_no_polis',
        'penjamin_plan',
        'penjamin_selisih',
        'penjamin_acc',
        'penjamin_no_telp',
        'status_pasien_id',
        'cara_masuk',
        'asal_layanan_id',
        'layanan_id',
        'dokter_id',
        'dokter_perujuk_id',
        'ruang_id',
        'kelas_id',
        'bed_id',
        'titip',
        'titip_kelas_id',
        'asal_pelayanan_id',
        'parent_pelayanan_id',
        'catatan',
        'pj_yang_bersangkutan',
        'pj_hubungan',
        'pj_nama',
        'pj_alamat',
        'pj_telepon',
        'pj_no_identitas',
        'jumlah_tagihan',
        'jumlah_inacbgs',
        'close',
        'close_at',
        'close_by',
        'created_at',
        'created_by',
        'update_at',
        'update_by',
        'deleted',
        'delete_at',
        'delete_by',
        'batal',
        'batal_at',
        'batal_by',
        'batal_alasan',
        'pindah_ranap',
        'jumlah_hari_rawat',
        'checkout',
        'checkout_at',
        'checkout_by',
        'data_checkout',
        'pindah_vk',
        'vk_id',
        'status_antrian',
        'status_antrian_at',
        'type_registration',
        'ordering',
        'blacklist',
        'blacklist_at',
        'blacklist_by',
        'white_flag',
        'white_at',
        'white_by',
        'white_alasan',
        'gabung_flag',
        'gabung_dest_pelayanan_id',
        'gabung_at',
        'gabung_by',
        'paket',
        'flag_open',
        'flag_open_at',
        'flag_open_by',
        'tutup_asuhan',
        'tutup_asuhan_at',
        'tutup_asuhan_by',
        'bayar_by',
        'bayar_at',
        'bayar',
        'dokter_pendamping_id',
        'no_rujukan',
        'tanggal_rencana_kontrol',
        'rujuk_layanan',
        'rujuk_dokter',
        'rujuk_rs',
        'farmasi_unit_id',
        'catatan_kasir',
        'id_encounter',
        'id_condition',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'close_at' => 'datetime',
        'created_at' => 'datetime',
        'update_at' => 'datetime',
        'delete_at' => 'datetime',
        'batal_at' => 'datetime',
        'checkout_at' => 'datetime',
        'status_antrian_at' => 'datetime',
        'blacklist_at' => 'datetime',
        'white_at' => 'datetime',
        'gabung_at' => 'datetime',
        'flag_open_at' => 'datetime',
        'tutup_asuhan_at' => 'datetime',
        'bayar_at' => 'datetime',
        'tanggal_rencana_kontrol' => 'date',

        'rujuk_rs' => 'integer',

        'rujukan_dari' => 'integer',
        'cara_masuk' => 'integer',
        'titip' => 'boolean',
        'pj_yang_bersangkutan' => 'boolean',
        'close' => 'boolean',
        'deleted' => 'boolean',
        'batal' => 'boolean',
        'pindah_ranap' => 'boolean',
        'checkout' => 'boolean',
        'pindah_vk' => 'boolean',
        'blacklist' => 'boolean',
        'white_flag' => 'boolean',
        'gabung_flag' => 'boolean',
        'paket' => 'boolean',
        'flag_open' => 'boolean',
        'tutup_asuhan' => 'boolean',
        'bayar' => 'boolean',
    ];

    // Contoh relasi (optional), sesuaikan namespace dan model sesuai aplikasi kamu
    public function pasien()
    {
        return $this->belongsTo(MPasien::class, 'pasien_id');
    }

    // public function dokter()
    // {
    //     return $this->belongsTo(Pegawai::class, 'dokter_id');
    // }

    // public function pegawai()
    // {
    //     return $this->belongsTo(Pegawai::class, 'pegawai_id');
    // }

    // public function vk()
    // {
    //     return $this->belongsTo(VK::class, 'vk_id');
    // }

    // Tambahkan relasi lain sesuai kebutuhan...

    // Contoh validasi rules
    public function getValidationRules()
    {
        return [
            'uid' => 'required|string|unique:t_pelayanan,uid,' . $this->id,
            'no_register' => 'required|string|max:15',
            'no_antrian' => 'required|string|max:20',
            'pasien_id' => 'nullable|integer|exists:m_pasien,id',
            'dokter_id' => 'nullable|integer|exists:hrd_karyawan,id',
            'cara_bayar_id' => 'nullable|integer|exists:m_cara_bayar,id',
            'status_pasien_id' => 'nullable|integer|exists:m_statuspasien,id',
            'deleted' => 'boolean',
            'batal' => 'boolean',
            'close' => 'boolean',
            // rules lain bisa ditambahkan sesuai kebutuhan...
        ];
    }
}
