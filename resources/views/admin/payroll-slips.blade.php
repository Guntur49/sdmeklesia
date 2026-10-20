@extends('layouts.app')

@section('content')
<div class="topbar">
    <div>
        <div class="eyebrow">Manajemen penggajian</div>
        <h1>Gaji staff</h1>
        <p class="muted">Buat dan unduh slip gaji bulanan setiap staff.</p>
    </div>
    <span class="pill">{{ $slips->count() }} slip · {{ $month }}</span>
</div>

@if($errors->any())
    <div class="alert error">
        <strong>Periksa kembali data slip gaji.</strong>
        <ul style="margin:8px 0 0;padding-left:20px">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<section class="card">
    <div class="eyebrow">Filter slip gaji</div>
    <h2>Pilih bulan dan staff</h2>
    <form method="get" action="{{ route('admin.payroll-slips.index') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <label style="min-width:190px">Bulan
            <input type="month" name="month" value="{{ $month }}" required>
        </label>
        <label style="min-width:220px">Staff
            <select name="user_id">
                <option value="">Semua staff</option>
                @foreach($staff as $person)
                    <option value="{{ $person->id }}" @selected($selectedUserId === $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn ghost" type="submit">Tampilkan</button>
        @if($editingSlip)
            <a class="btn ghost" href="{{ route('admin.payroll-slips.index', ['month' => $month, 'user_id' => $selectedUserId]) }}">Slip baru</a>
        @endif
    </form>
</section>

<section class="card" style="margin-top:18px">
    <div class="eyebrow">{{ $editingSlip ? 'Perbarui data' : 'Input baru' }}</div>
    <h2>{{ $editingSlip ? 'Ubah slip gaji' : 'Tambahkan slip gaji' }}</h2>
    @if($staff->isEmpty())
        <p class="muted">Belum ada staff yang terdaftar.</p>
    @else
        <form class="form-grid" method="post" action="{{ route('admin.payroll-slips.store') }}">
            @csrf
            <div class="form-row">
                <label>Bulan gaji
                    <input type="month" name="month" data-payroll-month value="{{ old('month', $editingSlip?->month ?? $month) }}" required>
                </label>
                <label>Nama staff
                    <select name="user_id" data-payroll-staff required>
                        @foreach($staff as $person)
                            <option
                                value="{{ $person->id }}"
                                data-paid-days="{{ $paidDaysByStaff[$person->id] ?? 0 }}"
                                data-base-salary="{{ $person->base_salary }}"
                                data-daily-attendance-allowance="{{ $person->daily_attendance_allowance }}"
                                data-daily-meal-allowance="{{ $person->daily_meal_allowance }}"
                                data-daily-transport-allowance="{{ $person->daily_transport_allowance }}"
                                data-bpjs-subsidy="{{ $person->bpjs_subsidy }}"
                                @selected((int) old('user_id', $payrollUserId) === $person->id)
                            >{{ $person->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <p class="muted" style="margin:0">
                Jumlah hari dibayar: <strong data-paid-days-display>{{ $initialPaidDays }}</strong> hari
                (check-in dan dispensasi; tanggal yang sama dihitung sekali).
            </p>

            <div class="grid grid-2">
                <section class="card">
                    <div class="eyebrow">Penerimaan</div>
                    <div class="form-grid">
                        @foreach(\App\Models\PayrollSlip::EARNING_FIELDS as $field => $label)
                            @php($isAutomatic = in_array($field, \App\Models\PayrollSlip::AUTOMATIC_EARNING_FIELDS, true))
                            <label>{{ $label }}
                                <input
                                    type="number"
                                    name="{{ $field }}"
                                    value="{{ $isAutomatic ? $automaticAmounts[$field] : old($field, $editingSlip?->getAttribute($field) ?? 0) }}"
                                    min="0"
                                    max="999999999999999"
                                    step="1"
                                    @readonly($isAutomatic)
                                    required
                                >
                            </label>
                        @endforeach
                    </div>
                </section>
                <section class="card">
                    <div class="eyebrow">Potongan</div>
                    <div class="form-grid">
                        @foreach(\App\Models\PayrollSlip::DEDUCTION_FIELDS as $field => $label)
                            <label>{{ $label }}
                                <input type="number" name="{{ $field }}" value="{{ old($field, $editingSlip?->getAttribute($field) ?? 0) }}" min="0" max="999999999999999" step="1" required>
                            </label>
                        @endforeach
                    </div>
                </section>
            </div>
            <button class="btn" type="submit">{{ $editingSlip ? 'Simpan perubahan' : 'Simpan slip gaji' }}</button>
        </form>
    @endif
</section>

<section class="card" style="margin-top:18px">
    <div class="topbar" style="margin-bottom:12px">
        <div>
            <div class="eyebrow">Arsip penggajian</div>
            <h2>Slip bulan {{ $month }}</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Nama staff</th><th>Total penerimaan</th><th>Total potongan</th><th>Gaji diterima</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse($slips as $slip)
                    <tr>
                        <td>{{ $slip->user->name }}</td>
                        <td>Rp {{ number_format($slip->earningsTotal(), 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($slip->deductionsTotal(), 0, ',', '.') }}</td>
                        <td><strong>Rp {{ number_format($slip->takeHomePay(), 0, ',', '.') }}</strong></td>
                        <td>
                            <div style="display:flex;gap:8px">
                                <a class="btn ghost" href="{{ route('admin.payroll-slips.index', ['month' => $month, 'user_id' => $slip->user_id, 'edit' => $slip->id]) }}">Ubah</a>
                                <a class="btn" href="{{ route('admin.payroll-slips.download', $slip) }}">Unduh Excel</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Belum ada slip gaji untuk bulan dan staff yang dipilih.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<script>
    const payrollStaffSelect = document.querySelector('[data-payroll-staff]');
    const payrollMonthInput = document.querySelector('[data-payroll-month]');

    if (payrollStaffSelect && payrollMonthInput) {
        const automaticFieldCalculators = {
            base_salary: (staff) => Number(staff.dataset.baseSalary),
            attendance: (staff) => Number(staff.dataset.dailyAttendanceAllowance) * Number(staff.dataset.paidDays),
            umkm: (staff) => Number(staff.dataset.dailyMealAllowance) * Number(staff.dataset.paidDays),
            transport: (staff) => Number(staff.dataset.dailyTransportAllowance) * Number(staff.dataset.paidDays),
            bpjs: (staff) => Number(staff.dataset.bpjsSubsidy),
        };

        const refreshAutomaticAmounts = () => {
            const staff = payrollStaffSelect.selectedOptions[0];
            const paidDays = Number(staff.dataset.paidDays);

            document.querySelector('[data-paid-days-display]').textContent = paidDays;

            for (const [field, calculate] of Object.entries(automaticFieldCalculators)) {
                const input = document.querySelector(`input[name="${field}"]`);
                input.value = calculate(staff);
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        };

        payrollStaffSelect.addEventListener('change', refreshAutomaticAmounts);
        payrollMonthInput.addEventListener('change', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('month', payrollMonthInput.value);
            url.searchParams.set('user_id', payrollStaffSelect.value);
            url.searchParams.delete('edit');
            window.location.assign(url);
        });

        refreshAutomaticAmounts();
    }
</script>

@endsection
