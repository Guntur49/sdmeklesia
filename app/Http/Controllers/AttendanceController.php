<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\Dispensation;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function login(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->isAdmin() ? 'admin' : 'staff');
        }

        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('username'))->with('error', 'Username atau password tidak sesuai.');
        }

        $request->session()->regenerate();

        return redirect()->route(Auth::user()->isAdmin() ? 'admin' : 'staff');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function staff(): View
    {
        $user = Auth::user();
        $today = Carbon::today();
        $todaySchedule = $this->scheduleFor($user, strtolower($today->locale('id')->dayName));
        if ($todaySchedule) {
            $user->work_start = $todaySchedule['start'];
            $user->work_end = $todaySchedule['end'];
        }
        $attendance = Attendance::where('user_id', $user->id)->whereDate('attendance_date', $today)->first();
        $dispensation = Dispensation::where('user_id', $user->id)->whereDate('dispensation_date', $today)->first();
        $leave = LeaveRequest::where('user_id', $user->id)->whereDate('request_date', $today)->first();
        $location = $this->location();

        return view('staff.index', compact('user', 'attendance', 'dispensation', 'leave', 'location'));
    }

    public function attendance(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validate([
            'action' => ['required', 'in:check_in,check_out'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'face_verified' => ['required', 'accepted'],
            'blink_verified' => ['required', 'accepted'],
        ]);
        $now = Carbon::now();
        $today = $now->toDateString();
        $weekday = strtolower($now->locale('id')->dayName);
        $workDays = $user->work_days ?? [];

        if (! in_array($weekday, $workDays, true)) {
            return back()->with('error', 'Hari ini bukan jadwal kerja Anda.');
        }
        if (Dispensation::where('user_id', $user->id)->whereDate('dispensation_date', $today)->exists()) {
            return back()->with('error', 'Anda mendapat dispensasi untuk hari ini.');
        }

        $location = $this->location();
        $distance = $location ? $this->distanceInMeters((float) $data['latitude'], (float) $data['longitude'], $location['latitude'], $location['longitude']) : null;
        if (! $location) {
            return back()->with('error', 'Lokasi absensi belum diatur admin.');
        }
        if ($distance > 100) {
            $accuracy = isset($data['accuracy']) ? ' Akurasi GPS sekitar '.round((float) $data['accuracy']).' meter.' : '';

            return back()->with('error', 'Jarak GPS Anda sekitar '.round($distance).' meter dari titik kantor, sedangkan batasnya 100 meter.'.$accuracy.' Pastikan GPS aktif dan coba lagi di area terbuka.');
        }

        $record = Attendance::firstOrCreate(['user_id' => $user->id, 'attendance_date' => $today]);
        if ($data['action'] === 'check_in') {
            if ($record->check_in) {
                return back()->with('error', 'Anda sudah melakukan absen masuk hari ini.');
            }
            $schedule = $this->scheduleFor($user, $weekday);
            if (! $schedule) {
                return back()->with('error', 'Jam kerja hari ini belum diatur admin.');
            }
            $start = Carbon::parse($today.' '.$schedule['start']);
            $gracePeriodEndsAt = $start->copy()->addMinutes(5);
            $late = $now->greaterThan($gracePeriodEndsAt)
                ? (int) ceil($gracePeriodEndsAt->diffInSeconds($now) / 60)
                : 0;
            $record->update([
                'check_in' => $now,
                'check_in_status' => $late > 0 ? 'Terlambat' : 'Tepat waktu',
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'face_verified' => true,
                'late_minutes' => $late,
            ]);

            return back()->with('success', 'Absensi masuk sukses. Selamat bekerja!');
        }

        if (! $record->check_in) {
            return back()->with('error', 'Anda belum melakukan absen masuk hari ini.');
        }
        $schedule = $this->scheduleFor($user, $weekday);
        if (! $schedule) {
            return back()->with('error', 'Jam kerja hari ini belum diatur admin.');
        }
        $end = Carbon::parse($today.' '.$schedule['end']);
        if ($now->lessThan($end)) {
            return back()->with('error', 'Absen pulang baru tersedia setelah jam pulang kerja.');
        }
        if ($record->check_out) {
            return back()->with('error', 'Anda sudah melakukan absen pulang hari ini.');
        }
        $overtime = $now->greaterThan($end->copy()->addMinutes(30)) ? $end->diffInMinutes($now) : 0;
        $record->update(['check_out' => $now, 'overtime_minutes' => $overtime]);

        return back()->with('success', 'Absensi pulang sukses. Hati-hati di jalan!');
    }

    public function leave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'request_date' => ['required', 'date'],
            'type' => ['required', 'in:izin,sakit'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        LeaveRequest::updateOrCreate(
            ['user_id' => Auth::id(), 'request_date' => $data['request_date']],
            ['type' => $data['type'], 'reason' => $data['reason'], 'status' => 'approved'],
        );

        return back()->with('success', 'Pengajuan '.ucfirst($data['type']).' berhasil dikirim.');
    }

    public function admin(): View
    {
        $staff = User::where('role', 'staff')->orderBy('name')->get();
        $location = $this->location();

        return view('admin.index', compact('staff', 'location'));
    }

    public function staffDetails(Request $request): View
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $search = trim($data['search'] ?? '');
        $staff = User::where('role', 'staff')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->get();

        return view('admin.staff', compact('staff', 'search'));
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $data = $this->validateStaffData($request);
        $schedule = array_values($data['work_schedule'])[0];
        User::create([
            'name' => $data['name'], 'username' => $data['username'],
            'email' => $data['username'].'@eklesia.local', 'password' => $data['password'],
            'role' => 'staff', 'employment_status' => $data['employment_status'],
            'base_salary' => $data['base_salary'],
            'daily_attendance_allowance' => $data['daily_attendance_allowance'],
            'daily_meal_allowance' => $data['daily_meal_allowance'],
            'daily_transport_allowance' => $data['daily_transport_allowance'],
            'bpjs_subsidy' => $data['bpjs_subsidy'],
            'work_days' => $data['work_days'],
            'work_start' => $schedule['start'], 'work_end' => $schedule['end'],
            'work_schedule' => $data['work_schedule'],
            'face_reference' => json_encode($this->storeFacePhotos($request)),
        ]);

        return back()->with('success', 'Akun staff berhasil dibuat.');
    }

    public function updateStaff(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->role === 'staff', 404);

        $data = $this->validateStaffData($request, $staff);
        $schedule = array_values($data['work_schedule'])[0];

        $attributes = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['username'].'@eklesia.local',
            'employment_status' => $data['employment_status'],
            'base_salary' => $data['base_salary'],
            'daily_attendance_allowance' => $data['daily_attendance_allowance'],
            'daily_meal_allowance' => $data['daily_meal_allowance'],
            'daily_transport_allowance' => $data['daily_transport_allowance'],
            'bpjs_subsidy' => $data['bpjs_subsidy'],
            'work_days' => $data['work_days'],
            'work_start' => $schedule['start'],
            'work_end' => $schedule['end'],
            'work_schedule' => $data['work_schedule'],
        ];
        if ($data['password'] ?? null) {
            $attributes['password'] = $data['password'];
        }
        if ($request->hasFile('face_photos')) {
            $this->deleteFacePhotos($staff);
            $attributes['face_reference'] = json_encode($this->storeFacePhotos($request));
        }

        $staff->update($attributes);

        return back()->with('success', 'Data staff berhasil diperbarui.');
    }

    public function destroyStaff(User $staff): RedirectResponse
    {
        abort_unless($staff->role === 'staff', 404);

        $this->deleteFacePhotos($staff);
        $staff->delete();

        return back()->with('success', 'Akun staff berhasil dihapus.');
    }

    public function saveLocation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'location_url' => ['required', 'url', 'max:2048'],
        ]);
        $coordinates = $this->coordinatesFromMapsUrl($data['location_url']);
        if (! $coordinates) {
            return back()->withInput()->with('error', 'Link Google Maps tidak berisi koordinat lokasi yang valid.');
        }
        AppSetting::updateOrCreate(['key' => 'attendance_location'], ['value' => json_encode([
            'url' => $data['location_url'],
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
        ])]);

        return back()->with('success', 'Lokasi absensi berhasil diperbarui.');
    }

    public function storeDispensation(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'dispensation_date' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:500']]);
        Dispensation::updateOrCreate(['user_id' => $data['user_id'], 'dispensation_date' => $data['dispensation_date']], ['reason' => $data['reason']]);

        return back()->with('success', 'Dispensasi berhasil disimpan.');
    }

    public function report(Request $request): View
    {
        $staff = User::where('role', 'staff')->orderBy('name')->get();
        $showAll = $request->input('user_id') === 'all';
        $selected = $showAll ? 'all' : ($request->integer('user_id') ?: $staff->first()?->id);
        $month = $request->input('month', now()->format('Y-m'));
        $reports = $showAll ? $this->buildAllReports($staff, $month) : [];
        $report = $showAll ? null : $this->buildReport($selected, $month);

        return view('admin.report', compact('staff', 'report', 'reports', 'selected', 'month', 'showAll'));
    }

    public function reportPdf(Request $request): Response
    {
        $staff = User::where('role', 'staff')->orderBy('name')->get();
        $showAll = $request->input('user_id') === 'all';
        $selected = $showAll ? 'all' : ($request->integer('user_id') ?: $staff->first()?->id);
        $month = $request->input('month', now()->format('Y-m'));
        $reports = $showAll ? $this->buildAllReports($staff, $month) : [];
        $report = $showAll ? null : $this->buildReport($selected, $month);

        return response()->view('admin.report-print', compact('report', 'reports', 'showAll'));
    }

    public function reportDownload(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'string', 'regex:/^(all|\d+)$/'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $staff = User::where('role', 'staff')->orderBy('name')->get();
        $month = $data['month'] ?? now()->format('Y-m');
        $selected = $data['user_id'] ?? (string) ($staff->first()?->id ?? '');
        $showAll = $selected === 'all';

        if ($showAll) {
            $reports = $this->buildAllReports($staff, $month);
            $filename = 'laporan-absensi-semua-anggota-'.$month.'.xlsx';
        } elseif ($selected === '') {
            $reports = [];
            $filename = 'laporan-absensi-'.$month.'.xlsx';
        } else {
            $user = $staff->firstWhere('id', (int) $selected);
            if (! $user instanceof User) {
                abort(404);
            }

            $reports = [$this->buildReport($user->id, $month)];
            $filename = 'laporan-absensi-'.$user->username.'-'.$month.'.xlsx';
        }

        $spreadsheet = $this->createReportSpreadsheet($reports, $showAll);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function location(): ?array
    {
        $value = AppSetting::where('key', 'attendance_location')->value('value');

        return $value ? json_decode($value, true) : null;
    }

    private function coordinatesFromMapsUrl(string $url): ?array
    {
        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $url, $matches)) {
            return ['latitude' => (float) $matches[1], 'longitude' => (float) $matches[2]];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get($url);
            if (! $response->successful()) {
                return null;
            }
            $resolvedUrl = $response->handlerStats()['url'] ?? '';
        } catch (\Throwable) {
            return null;
        }

        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $resolvedUrl, $matches)) {
            return ['latitude' => (float) $matches[1], 'longitude' => (float) $matches[2]];
        }

        preg_match_all('/-?\d{1,3}\.\d{5,}/', $response->body(), $matches);
        $values = array_values(array_unique($matches[0]));
        foreach ($values as $index => $value) {
            $nextValue = $values[$index + 1] ?? null;
            if ($nextValue === null) {
                break;
            }
            $first = (float) $value;
            $second = (float) $nextValue;
            if (abs($first) <= 90 && abs($second) <= 180) {
                return ['latitude' => $first, 'longitude' => $second];
            }
            if (abs($second) <= 90 && abs($first) <= 180) {
                return ['latitude' => $second, 'longitude' => $first];
            }
        }

        return null;
    }

    private function storeFacePhotos(Request $request): array
    {
        $paths = [];
        foreach ($request->file('face_photos', []) as $photo) {
            $storedPath = $photo->store('face-references', 'public');
            $paths[] = basename($storedPath);
        }

        return $paths;
    }

    private function validateStaffData(Request $request, ?User $staff = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'alpha_dash', $staff ? Rule::unique('users', 'username')->ignore($staff->id) : 'unique:users,username'],
            'password' => [$staff ? 'nullable' : 'required', 'min:6'],
            'employment_status' => ['required', 'in:tetap,kontrak'],
            'base_salary' => ['required', 'integer', 'min:0', 'max:999999999999999'],
            'daily_attendance_allowance' => ['required', 'integer', 'min:0', 'max:999999999999999'],
            'daily_meal_allowance' => ['required', 'integer', 'min:0', 'max:999999999999999'],
            'daily_transport_allowance' => ['required', 'integer', 'min:0', 'max:999999999999999'],
            'bpjs_subsidy' => ['required', 'integer', 'min:0', 'max:999999999999999'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'work_schedule' => ['required', 'array'],
            'work_schedule.*.start' => ['nullable', 'date_format:H:i'],
            'work_schedule.*.end' => ['nullable', 'date_format:H:i'],
            'face_photos' => ['nullable', 'array', 'max:5'],
            'face_photos.*' => ['image', 'max:4096'],
        ]);
        $schedule = [];
        foreach ($data['work_days'] as $day) {
            $start = $data['work_schedule'][$day]['start'] ?? null;
            $end = $data['work_schedule'][$day]['end'] ?? null;
            if (! $start || ! $end) {
                throw ValidationException::withMessages(['work_schedule' => 'Lengkapi jam masuk dan pulang untuk setiap hari kerja yang dipilih.']);
            }
            if ($end <= $start) {
                throw ValidationException::withMessages(['work_schedule' => 'Jam pulang harus lebih besar dari jam masuk pada setiap hari kerja.']);
            }
            $schedule[$day] = ['start' => $start, 'end' => $end];
        }
        $data['work_schedule'] = $schedule;

        return $data;
    }

    private function scheduleFor(User $user, string $weekday): ?array
    {
        return $user->work_schedule[$weekday] ?? ($user->work_start && $user->work_end ? [
            'start' => substr($user->work_start, 0, 5),
            'end' => substr($user->work_end, 0, 5),
        ] : null);
    }

    private function deleteFacePhotos(User $staff): void
    {
        $photos = json_decode($staff->face_reference ?: '[]', true) ?: [];
        Storage::disk('public')->delete(array_map(fn (string $photo): string => 'face-references/'.$photo, $photos));
    }

    private function distanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function buildReport(?int $userId, string $month): array
    {
        [$periodStart, $periodEnd, $periodLabel] = $this->reportPeriod($month);

        if (! $userId || ! ($user = User::find($userId))) {
            return ['user' => null, 'month' => $periodLabel, 'period_label' => $periodLabel, 'days' => [], 'present' => 0, 'absent' => 0, 'leave' => 0, 'late' => 0, 'overtime' => 0];
        }
        $attendances = Attendance::where('user_id', $userId)->whereBetween('attendance_date', [$periodStart, $periodEnd])->get()->keyBy(fn ($item) => $item->attendance_date->format('Y-m-d'));
        $leaves = LeaveRequest::where('user_id', $userId)->whereBetween('request_date', [$periodStart, $periodEnd])->get()->keyBy(fn ($item) => Carbon::parse($item->request_date)->format('Y-m-d'));
        $dispensations = Dispensation::where('user_id', $userId)->whereBetween('dispensation_date', [$periodStart, $periodEnd])->get()->keyBy(fn ($item) => Carbon::parse($item->dispensation_date)->format('Y-m-d'));

        return $this->formatUserReport($user, $periodStart, $periodEnd, $periodLabel, $attendances, $leaves, $dispensations);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function reportPeriod(string $month): array
    {
        $periodEnd = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->addDays(19)->endOfDay();
        $periodStart = $periodEnd->copy()->subMonthNoOverflow()->startOfMonth()->addDays(20)->startOfDay();
        $periodLabel = $periodStart->format('d/m/Y').' - '.$periodEnd->format('d/m/Y');

        return [$periodStart, $periodEnd, $periodLabel];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAllReports(EloquentCollection $staff, string $month): array
    {
        [$periodStart, $periodEnd, $periodLabel] = $this->reportPeriod($month);
        $userIds = $staff->modelKeys();

        $attendances = Attendance::whereIn('user_id', $userIds)
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('user_id');
        $leaves = LeaveRequest::whereIn('user_id', $userIds)
            ->whereBetween('request_date', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('user_id');
        $dispensations = Dispensation::whereIn('user_id', $userIds)
            ->whereBetween('dispensation_date', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('user_id');

        return $staff->map(function (User $user) use ($attendances, $leaves, $dispensations, $periodStart, $periodEnd, $periodLabel): array {
            $userAttendances = ($attendances->get($user->id) ?? collect())
                ->keyBy(fn ($item) => $item->attendance_date->format('Y-m-d'));
            $userLeaves = ($leaves->get($user->id) ?? collect())
                ->keyBy(fn ($item) => Carbon::parse($item->request_date)->format('Y-m-d'));
            $userDispensations = ($dispensations->get($user->id) ?? collect())
                ->keyBy(fn ($item) => $item->dispensation_date->format('Y-m-d'));

            return $this->formatUserReport($user, $periodStart, $periodEnd, $periodLabel, $userAttendances, $userLeaves, $userDispensations);
        })->all();
    }

    private function formatUserReport(
        User $user,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $periodLabel,
        Collection $attendances,
        Collection $leaves,
        Collection $dispensations,
    ): array {
        $days = [];
        for ($date = $periodStart->copy(); $date <= $periodEnd; $date->addDay()) {
            $key = $date->format('Y-m-d');
            $attendance = $attendances->get($key);
            $leave = $leaves->get($key);
            $days[] = [
                'date' => $date->copy(),
                'attendance' => $attendance,
                'leave' => $leave,
                'dispensation' => $dispensations->get($key),
                'workday' => in_array(strtolower($date->locale('id')->dayName), $user->work_days ?? [], true),
                'future' => $date->isFuture(),
            ];
        }

        $present = collect($days)
            ->filter(fn (array $day) => $day['attendance']?->check_in || $day['dispensation'])
            ->count();
        $absent = collect($days)
            ->filter(fn (array $day) => $day['workday'] && ! $day['future'] && ! $day['attendance']?->check_in && ! $day['leave'] && ! $day['dispensation'])
            ->count();

        return ['user' => $user, 'month' => $periodLabel, 'period_label' => $periodLabel, 'days' => $days, 'present' => $present, 'absent' => $absent, 'leave' => $leaves->count(), 'late' => $attendances->sum('late_minutes'), 'overtime' => $attendances->sum('overtime_minutes')];
    }

    /**
     * @param  array<int, array<string, mixed>>  $reports
     */
    private function createReportSpreadsheet(array $reports, bool $showAll): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Ringkasan');
        $summary->mergeCells('A1:F1');
        $summary->setCellValue('A1', 'Laporan Absensi EKLESIA GROUP');
        $summary->mergeCells('A2:F2');
        $reportSubject = $showAll ? 'Semua anggota' : ($reports[0]['user']?->name ?? 'Tidak ada anggota staff');
        $summary->setCellValue('A2', $reportSubject.' · Periode '.($reports[0]['period_label'] ?? 'Tidak ada anggota staff'));

        $totals = [
            'present' => collect($reports)->sum('present'),
            'absent' => collect($reports)->sum('absent'),
            'leave' => collect($reports)->sum('leave'),
            'late' => collect($reports)->sum('late'),
            'overtime' => collect($reports)->sum('overtime'),
        ];
        $summaryCards = [
            ['label' => 'Hadir', 'value' => $totals['present'], 'label_range' => 'A4:B4', 'value_range' => 'A5:B6'],
            ['label' => 'Tidak hadir', 'value' => $totals['absent'], 'label_range' => 'C4:D4', 'value_range' => 'C5:D6'],
            ['label' => 'Izin / sakit', 'value' => $totals['leave'], 'label_range' => 'E4:F4', 'value_range' => 'E5:F6'],
            ['label' => 'Total telat', 'value' => $totals['late'].' menit', 'label_range' => 'A8:C8', 'value_range' => 'A9:C10'],
            ['label' => 'Total lembur', 'value' => $totals['overtime'].' menit', 'label_range' => 'D8:F8', 'value_range' => 'D9:F10'],
        ];

        foreach ($summaryCards as $card) {
            $summary->mergeCells($card['label_range']);
            $summary->mergeCells($card['value_range']);
            $summary->setCellValue(explode(':', $card['label_range'])[0], $card['label']);
            $summary->setCellValue(explode(':', $card['value_range'])[0], $card['value']);
            $summary->getStyle($card['label_range'])->applyFromArray([
                'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C95A1D']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $summary->getStyle($card['value_range'])->applyFromArray([
                'font' => ['color' => ['rgb' => '54250F'], 'bold' => true, 'size' => 18],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF1E3']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        $tableHeaderRow = 12;
        $summary->fromArray(['Anggota', 'Hadir', 'Tidak hadir', 'Izin / sakit', 'Telat (menit)', 'Lembur (menit)'], null, 'A'.$tableHeaderRow);

        foreach ($reports as $index => $report) {
            $row = $index + $tableHeaderRow + 1;
            $summary->setCellValueExplicit('A'.$row, $report['user']->name, DataType::TYPE_STRING);
            $summary->fromArray([
                $report['present'],
                $report['absent'],
                $report['leave'],
                $report['late'],
                $report['overtime'],
            ], null, 'B'.$row);
        }

        $totalRow = count($reports) + $tableHeaderRow + 1;
        $summary->setCellValue('A'.$totalRow, 'TOTAL');
        $summary->fromArray([
            $totals['present'],
            $totals['absent'],
            $totals['leave'],
            $totals['late'],
            $totals['overtime'],
        ], null, 'B'.$totalRow);

        $summary->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('54250F');
        $summary->getStyle('A2')->getFont()->setSize(12)->getColor()->setRGB('A84316');
        $summary->getStyle('A'.$tableHeaderRow.':F'.$tableHeaderRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $summary->getStyle('A'.$tableHeaderRow.':F'.$tableHeaderRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C95A1D');
        $summary->getStyle('A'.$totalRow.':F'.$totalRow)->getFont()->setBold(true);
        $summary->getRowDimension(1)->setRowHeight(30);
        $summary->getRowDimension(2)->setRowHeight(24);
        $summary->getRowDimension(5)->setRowHeight(24);
        $summary->getRowDimension(6)->setRowHeight(20);
        $summary->getRowDimension(9)->setRowHeight(24);
        $summary->getRowDimension(10)->setRowHeight(20);
        $summary->getRowDimension($tableHeaderRow)->setRowHeight(22);

        foreach (range('A', 'F') as $column) {
            $summary->getColumnDimension($column)->setWidth(18);
        }
        $summary->getColumnDimension('A')->setWidth(24);

        $details = $spreadsheet->createSheet();
        $details->setTitle('Rincian Harian');
        $details->fromArray(['Anggota', 'Tanggal', 'Status', 'Masuk', 'Pulang', 'Telat (menit)', 'Lembur (menit)'], null, 'A1');
        $row = 2;

        foreach ($reports as $report) {
            foreach ($report['days'] as $day) {
                $details->setCellValueExplicit('A'.$row, $report['user']->name, DataType::TYPE_STRING);
                $details->setCellValue('B'.$row, $day['date']->format('d/m/Y'));
                $details->setCellValueExplicit('C'.$row, $this->reportDayStatus($day), DataType::TYPE_STRING);
                $details->setCellValue('D'.$row, $day['attendance']?->check_in?->format('H:i') ?? '-');
                $details->setCellValue('E'.$row, $day['attendance']?->check_out?->format('H:i') ?? '-');
                $details->setCellValue('F'.$row, $day['attendance']?->late_minutes ?? 0);
                $details->setCellValue('G'.$row, $day['attendance']?->overtime_minutes ?? 0);
                $row++;
            }
        }

        $details->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $details->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C95A1D');
        foreach (range('A', 'G') as $column) {
            $details->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function reportDayStatus(array $day): string
    {
        if ($day['dispensation']) {
            return 'Hadir (Dispen)';
        }
        if ($day['attendance']?->check_in) {
            return 'Hadir';
        }
        if ($day['leave']) {
            return ucfirst($day['leave']->type);
        }
        if ($day['future']) {
            return 'Belum berlangsung';
        }

        return $day['workday'] ? 'Tidak hadir' : 'Libur';
    }
}
