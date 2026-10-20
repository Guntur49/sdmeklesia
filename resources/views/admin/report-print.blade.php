<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Absensi</title>
    <style>
        body{font-family:Arial,sans-serif;color:#54250f;padding:30px}
        h1{margin-bottom:4px}
        h2{margin:24px 0 8px}
        .summary{display:flex;gap:35px;margin:25px 0}
        .summary b{font-size:24px;display:block}
        table{width:100%;border-collapse:collapse;margin-bottom:24px}
        th,td{padding:9px;border:1px solid #f2d8c2;text-align:left;font-size:12px}
        th{background:#fff1e3;color:#a84316}
        @media print{body{padding:0}.member{break-inside:avoid}}
    </style>
</head>
<body>
    <h1>Laporan Absensi EKLESIA GROUP</h1>
    @if($showAll)
        <p>Semua anggota · Periode {{ $reports[0]['period_label'] ?? 'tidak ada anggota staff' }}</p>
        <div class="summary">
            <div>Total hadir<b>{{ collect($reports)->sum('present') }}</b></div>
            <div>Total tidak hadir<b>{{ collect($reports)->sum('absent') }}</b></div>
            <div>Izin / sakit<b>{{ collect($reports)->sum('leave') }}</b></div>
            <div>Total telat<b>{{ collect($reports)->sum('late') }} menit</b></div>
            <div>Total lembur<b>{{ collect($reports)->sum('overtime') }} menit</b></div>
        </div>
        @foreach($reports as $memberReport)
            <section class="member">
                <h2>{{ $memberReport['user']->name }}</h2>
                <p>Hadir {{ $memberReport['present'] }} hari · Tidak hadir {{ $memberReport['absent'] }} hari · Izin / sakit {{ $memberReport['leave'] }} · Telat {{ $memberReport['late'] }} menit · Lembur {{ $memberReport['overtime'] }} menit</p>
                <table>
                    <thead><tr><th>Tanggal</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Telat</th><th>Lembur</th></tr></thead>
                    <tbody>
                        @foreach($memberReport['days'] as $day)
                            <tr>
                                <td>{{ $day['date']->format('d/m/Y') }}</td>
                                <td>{{ $day['dispensation'] ? 'Hadir (Dispen)' : ($day['attendance']?->check_in ? 'Hadir' : ($day['leave'] ? ucfirst($day['leave']->type) : ($day['future'] ? 'Belum berlangsung' : ($day['workday'] ? 'Tidak hadir' : 'Libur')))) }}</td>
                                <td>{{ $day['attendance']?->check_in?->format('H:i') ?? '-' }}</td>
                                <td>{{ $day['attendance']?->check_out?->format('H:i') ?? '-' }}</td>
                                <td>{{ $day['attendance']?->late_minutes ?? 0 }} menit</td>
                                <td>{{ $day['attendance']?->overtime_minutes ?? 0 }} menit</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    @else
        <p>{{ $report['user']?->name }} · Periode {{ $report['month'] }}</p>
        <div class="summary">
            <div>Hadir<b>{{ $report['present'] }}</b></div>
            <div>Tidak hadir<b>{{ $report['absent'] }}</b></div>
            <div>Izin / sakit<b>{{ $report['leave'] }}</b></div>
            <div>Total telat<b>{{ $report['late'] }} menit</b></div>
            <div>Total lembur<b>{{ $report['overtime'] }} menit</b></div>
        </div>
        <table>
            <thead><tr><th>Tanggal</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Telat</th><th>Lembur</th></tr></thead>
            <tbody>
                @foreach($report['days'] as $day)
                    <tr>
                        <td>{{ $day['date']->format('d/m/Y') }}</td>
                        <td>{{ $day['dispensation'] ? 'Hadir (Dispen)' : ($day['attendance']?->check_in ? 'Hadir' : ($day['leave'] ? ucfirst($day['leave']->type) : ($day['future'] ? 'Belum berlangsung' : ($day['workday'] ? 'Tidak hadir' : 'Libur')))) }}</td>
                        <td>{{ $day['attendance']?->check_in?->format('H:i') ?? '-' }}</td>
                        <td>{{ $day['attendance']?->check_out?->format('H:i') ?? '-' }}</td>
                        <td>{{ $day['attendance']?->late_minutes ?? 0 }} menit</td>
                        <td>{{ $day['attendance']?->overtime_minutes ?? 0 }} menit</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
