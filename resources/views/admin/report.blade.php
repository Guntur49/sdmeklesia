@extends('layouts.app')

@push('styles')
<style>
    .report-page {
        --report-orange: #c95a1d;
        --report-bright-orange: #f97316;
        --report-gold: #f59e0b;
        --report-soft: #fff1e3;
        --report-line: #f2d8c2;
    }

    .report-page .report-hero {
        padding: 28px 32px;
        border-radius: 16px;
        color: #fff;
        background: linear-gradient(145deg, #c95a1d, #f59e0b);
        box-shadow: 0 14px 32px rgba(201, 90, 29, .2);
    }

    .report-page .report-hero .eyebrow,
    .report-page .report-hero .muted {
        color: #fff1de;
    }

    .report-page .report-hero h1 {
        margin-top: 6px;
    }

    .report-page .report-hero .btn {
        flex-shrink: 0;
        background: #fff;
        color: var(--report-orange);
        box-shadow: 0 8px 20px rgba(98, 43, 15, .16);
    }

    .report-page .eyebrow {
        color: var(--report-orange);
    }

    .report-page .btn {
        background: linear-gradient(135deg, var(--report-bright-orange), #ea580c);
        box-shadow: 0 8px 18px rgba(249, 115, 22, .2);
    }

    .report-page .btn.ghost {
        background: var(--report-soft);
        color: var(--report-orange);
    }

    .report-page .card {
        border-color: var(--report-line);
    }

    .report-page .stat {
        border-top: 3px solid var(--report-bright-orange);
    }

    .report-page .pill {
        background: var(--report-soft);
        color: var(--report-orange);
    }

    .report-page .table th {
        color: var(--report-orange);
        background: #fff8f1;
    }

    .report-page .day.present {
        background: #fff1e3;
        border-color: #f4c59f;
    }

    .report-page .day.absent {
        background: #fde8e3;
        border-color: #f5c7bc;
    }

    .report-page .day.leave {
        background: #fff5df;
        border-color: #f4dda1;
    }

    @media (max-width: 850px) {
        .report-page .report-hero {
            padding: 24px 20px;
            flex-direction: column;
        }
    }
</style>
@endpush

@section('content')
<div class="report-page">
<div class="report-hero topbar">
    <div>
        <div class="eyebrow">Laporan absensi</div>
        <h1>{{ $showAll ? 'Laporan semua anggota' : ($report['user']?->name ?? 'Pilih staff') }}</h1>
        <p class="muted">Periode {{ $showAll ? ($reports[0]['period_label'] ?? 'belum ada anggota') : $report['period_label'] }} · Rekap kehadiran dan detail waktu kerja.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn" href="{{ route('admin.report.download', ['user_id' => $selected, 'month' => $month]) }}">Unduh Excel</a>
        <a class="btn ghost" href="{{ route('admin.report.pdf', ['user_id' => $selected, 'month' => $month]) }}">Lihat ringkasan</a>
    </div>
</div>

<section class="card">
    <form style="display:flex;gap:10px;flex-wrap:wrap;align-items:end" method="get">
        <label style="min-width:220px">Anggota
            <select name="user_id">
                <option value="all" @selected($showAll)>Semua anggota</option>
                @foreach($staff as $person)
                    <option value="{{ $person->id }}" @selected(! $showAll && $person->id === $selected)>{{ $person->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Bulan akhir periode<input type="month" name="month" value="{{ $month }}"></label>
        <button class="btn" type="submit">Tampilkan laporan</button>
    </form>
    <p class="muted" style="margin:12px 0 0">Periode laporan berjalan dari tanggal 21 sampai tanggal 20 bulan berikutnya.</p>
</section>

@if($showAll)
    @if($reports)
        <div class="grid grid-3" style="margin:18px 0">
            <div class="card stat"><span class="muted">Total hadir semua anggota</span><b>{{ collect($reports)->sum('present') }}</b></div>
            <div class="card stat"><span class="muted">Total izin / sakit</span><b>{{ collect($reports)->sum('leave') }}</b></div>
            <div class="card stat"><span class="muted">Total tidak hadir</span><b>{{ collect($reports)->sum('absent') }}</b><span class="muted">hari anggota</span></div>
            <div class="card stat"><span class="muted">Total telat</span><b>{{ collect($reports)->sum('late') }}</b><span class="muted">menit dalam periode ini</span></div>
            <div class="card stat"><span class="muted">Total lembur</span><b>{{ collect($reports)->sum('overtime') }}</b><span class="muted">menit</span></div>
        </div>
        <div class="grid">
            @foreach($reports as $memberReport)
                <section class="card">
                    <div class="topbar" style="margin-bottom:15px">
                        <div>
                            <div class="eyebrow">Laporan anggota</div>
                            <h2>{{ $memberReport['user']->name }}</h2>
                        </div>
                        <span class="pill">Hadir {{ $memberReport['present'] }} · Tidak hadir {{ $memberReport['absent'] }} · Telat {{ $memberReport['late'] }} menit</span>
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Tanggal</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Telat</th><th>Lembur</th></tr></thead>
                            <tbody>
                                @foreach($memberReport['days'] as $day)
                                    <tr>
                                        <td>{{ $day['date']->format('d/m/Y') }}</td>
                                        <td>{{ $day['dispensation'] ? 'Hadir (Dispen)' : ($day['attendance']?->check_in ? 'Hadir' : ($day['leave'] ? ucfirst($day['leave']->type) : ($day['future'] ? 'Belum berlangsung' : ($day['workday'] ? 'Tidak hadir' : 'Libur')))) }}</td>
                                        <td>{{ $day['attendance']?->check_in?->format('H:i') ?? '-' }}</td>
                                        <td>{{ $day['attendance']?->check_out?->format('H:i') ?? '-' }}</td>
                                        <td>{{ $day['attendance']?->late_minutes ? $day['attendance']->late_minutes.' menit' : '-' }}</td>
                                        <td>{{ $day['attendance']?->overtime_minutes ? $day['attendance']->overtime_minutes.' menit' : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    @else
        <section class="card" style="margin-top:18px"><p class="muted">Belum ada anggota staff untuk ditampilkan.</p></section>
    @endif
@elseif($report['user'])
    <div class="grid grid-3" style="margin:18px 0">
        <div class="card stat"><span class="muted">Hadir</span><b>{{ $report['present'] }}</b><span class="muted">hari dalam periode ini</span></div>
        <div class="card stat"><span class="muted">Izin / sakit</span><b>{{ $report['leave'] }}</b><span class="muted">pengajuan tercatat</span></div>
        <div class="card stat"><span class="muted">Tidak hadir</span><b>{{ $report['absent'] }}</b><span class="muted">hari kerja</span></div>
        <div class="card stat"><span class="muted">Total telat</span><b>{{ $report['late'] }}</b><span class="muted">menit dalam periode ini</span></div>
        <div class="card stat"><span class="muted">Total lembur</span><b>{{ $report['overtime'] }}</b><span class="muted">menit di atas 30 menit</span></div>
    </div>
    <section class="card">
        <div class="topbar" style="margin-bottom:15px"><div><div class="eyebrow">Kalender {{ $month }}</div><h2>Rekap harian</h2></div><span class="muted">Hijau hadir · merah tidak hadir</span></div>
        <div class="calendar">
            @foreach($report['days'] as $day)
                <div class="day {{ $day['attendance']?->check_in || $day['dispensation'] ? 'present' : ($day['leave'] ? 'leave' : ($day['workday'] && ! $day['future'] ? 'absent' : '')) }}">
                    <b>{{ $day['date']->format('d') }}</b><small>{{ $day['date']->locale('id')->shortDayName }}</small>
                    @if($day['dispensation'])
                        <small>Dispen</small>
                    @elseif($day['attendance']?->check_in)
                        <small>Masuk {{ $day['attendance']->check_in->format('H:i') }}</small>
                        @if($day['attendance']->late_minutes)<small style="color:#b84d3b">Telat {{ $day['attendance']->late_minutes }} m</small>@endif
                    @elseif($day['leave'])
                        <small>{{ ucfirst($day['leave']->type) }}</small>
                    @elseif($day['future'] && $day['workday'])
                        <small>Belum berlangsung</small>
                    @elseif($day['workday'])
                        <small>Tidak hadir</small>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
    <section class="card" style="margin-top:18px">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Telat</th><th>Lembur</th></tr></thead>
                <tbody>
                    @foreach($report['days'] as $day)
                        <tr>
                            <td>{{ $day['date']->format('d/m/Y') }}</td>
                            <td>{{ $day['dispensation'] ? 'Hadir (Dispen)' : ($day['attendance']?->check_in ? 'Hadir' : ($day['leave'] ? ucfirst($day['leave']->type) : ($day['future'] ? 'Belum berlangsung' : ($day['workday'] ? 'Tidak hadir' : 'Libur')))) }}</td>
                            <td>{{ $day['attendance']?->check_in?->format('H:i') ?? '-' }}</td>
                            <td>{{ $day['attendance']?->check_out?->format('H:i') ?? '-' }}</td>
                            <td>{{ $day['attendance']?->late_minutes ? $day['attendance']->late_minutes.' menit' : '-' }}</td>
                            <td>{{ $day['attendance']?->overtime_minutes ? $day['attendance']->overtime_minutes.' menit' : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
</div>
@endsection
