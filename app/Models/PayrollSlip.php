<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSlip extends Model
{
    public const AUTOMATIC_EARNING_FIELDS = [
        'base_salary',
        'attendance',
        'umkm',
        'transport',
        'bpjs',
    ];

    public const EARNING_FIELDS = [
        'base_salary' => 'Gaji pokok',
        'attendance' => 'Kehadiran',
        'umkm' => 'Uang makan',
        'transport' => 'Uang transport',
        'bpjs' => 'BPJS',
        'other_honor' => 'Honor lain-lain',
        'position_allowance' => 'Tunjangan jabatan',
        'overtime' => 'Tambahan jam',
        'holiday' => 'Tanggal merah',
        'thr' => 'THR',
        'bonus' => 'Bonus',
    ];

    public const DEDUCTION_FIELDS = [
        'deductions' => 'Potongan',
        'lateness' => 'Keterlambatan',
        'bpjs_deduction' => 'Potongan BPJS',
        'cooperative_deduction' => 'Angsuran koperasi',
        'sanction' => 'Sanksi',
        'honor_received' => 'Honor yang sudah diterima',
    ];

    protected $fillable = [
        'user_id',
        'month',
        'base_salary',
        'attendance',
        'umkm',
        'transport',
        'bpjs',
        'other_honor',
        'position_allowance',
        'overtime',
        'holiday',
        'thr',
        'bonus',
        'deductions',
        'lateness',
        'bpjs_deduction',
        'cooperative_deduction',
        'sanction',
        'honor_received',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function earningsTotal(): int
    {
        return array_sum(array_map(
            fn (string $field): int => (int) $this->getAttribute($field),
            array_keys(self::EARNING_FIELDS),
        ));
    }

    public function deductionsTotal(): int
    {
        return array_sum(array_map(
            fn (string $field): int => (int) $this->getAttribute($field),
            array_keys(self::DEDUCTION_FIELDS),
        ));
    }

    public function takeHomePay(): int
    {
        return $this->earningsTotal() - $this->deductionsTotal();
    }

    protected function casts(): array
    {
        return array_fill_keys([
            ...array_keys(self::EARNING_FIELDS),
            ...array_keys(self::DEDUCTION_FIELDS),
        ], 'integer');
    }
}
