<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Dispensation;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_attendance_report_for_all_staff(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'admin',
        ]);
        $raka = $this->createStaff('Raka', 'raka');
        $sinta = $this->createStaff('Sinta', 'sinta');
        Attendance::create([
            'user_id' => $raka->id,
            'attendance_date' => '2026-10-13',
            'check_in' => '2026-10-13 08:00:00',
            'check_in_status' => 'Tepat waktu',
        ]);
        Attendance::create([
            'user_id' => $sinta->id,
            'attendance_date' => '2026-10-13',
            'check_in' => '2026-10-13 08:12:00',
            'check_in_status' => 'Terlambat',
            'late_minutes' => 7,
        ]);

        $response = $this->actingAs($admin)->get('/admin/report?user_id=all&month=2026-10');

        $response->assertSeeText('Laporan semua anggota');
        $response->assertSeeText('Raka');
        $response->assertSeeText('Sinta');
        $response->assertSeeText('08:00');
        $response->assertSeeText('08:12');
        $response->assertSeeText('7 menit');
        $response->assertSeeText('Unduh Excel');
    }

    public function test_admin_can_print_all_staff_reports_and_select_an_individual_report(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'admin',
        ]);
        $raka = $this->createStaff('Raka', 'raka');
        $sinta = $this->createStaff('Sinta', 'sinta');

        $allReportsResponse = $this->actingAs($admin)->get('/admin/report/pdf?user_id=all&month=2026-10');
        $individualReportResponse = $this->actingAs($admin)->get('/admin/report?user_id='.$raka->id.'&month=2026-10');

        $allReportsResponse->assertSeeText('Raka');
        $allReportsResponse->assertSeeText('Sinta');
        $allReportsResponse->assertSeeText('Total tidak hadir');
        $allReportsResponse->assertSeeText('Total telat');
        $individualReportResponse->assertSeeText('Raka');
        $individualReportResponse->assertDontSeeText('Laporan semua anggota');
    }

    public function test_dispensation_counts_as_attendance_and_is_labeled_in_individual_report(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'admin',
        ]);
        $staff = $this->createStaff('Raka', 'raka');
        Dispensation::create([
            'user_id' => $staff->id,
            'dispensation_date' => '2026-10-13',
            'reason' => 'Dinas luar kota',
        ]);

        $response = $this->actingAs($admin)->get('/admin/report?user_id='.$staff->id.'&month=2026-10');

        $response->assertSeeText('Hadir');
        $response->assertSeeText('Hadir (Dispen)');
    }

    public function test_dispensation_counts_as_attendance_and_is_labeled_in_all_staff_report(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'admin',
        ]);
        $staff = $this->createStaff('Raka', 'raka');
        Dispensation::create([
            'user_id' => $staff->id,
            'dispensation_date' => '2026-10-13',
            'reason' => 'Dinas luar kota',
        ]);

        $response = $this->actingAs($admin)->get('/admin/report?user_id=all&month=2026-10');

        $response->assertSeeText('Hadir 1');
        $response->assertSeeText('Hadir (Dispen)');
    }

    public function test_individual_report_shows_absences_and_accumulated_late_minutes_for_the_period(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'admin',
        ]);
        $staff = $this->createStaff('Raka', 'raka');
        $staff->update(['work_days' => ['senin', 'selasa']]);

        Attendance::create([
            'user_id' => $staff->id,
            'attendance_date' => '2026-09-22',
            'check_in' => '2026-09-22 08:09:00',
            'check_in_status' => 'Terlambat',
            'late_minutes' => 4,
        ]);
        Attendance::create([
            'user_id' => $staff->id,
            'attendance_date' => '2026-10-13',
            'check_in' => '2026-10-13 08:16:00',
            'check_in_status' => 'Terlambat',
            'late_minutes' => 11,
        ]);
        Dispensation::create([
            'user_id' => $staff->id,
            'dispensation_date' => '2026-09-29',
            'reason' => null,
        ]);
        LeaveRequest::create([
            'user_id' => $staff->id,
            'request_date' => '2026-10-06',
            'type' => 'izin',
            'reason' => 'Keperluan keluarga',
        ]);

        $response = $this->actingAs($admin)->get('/admin/report?user_id='.$staff->id.'&month=2026-10');

        $response->assertSeeTextInOrder(['Tidak hadir', '4', 'hari kerja', 'Total telat', '15', 'menit dalam periode ini']);
        $response->assertSeeText('Belum berlangsung');
        $response->assertSeeText('Hadir (Dispen)');
    }

    public function test_admin_can_download_an_individual_xlsx_report_with_summary_and_daily_details(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'admin',
        ]);
        $staff = $this->createStaff('Raka', 'raka');
        Attendance::create([
            'user_id' => $staff->id,
            'attendance_date' => '2026-09-22',
            'check_in' => '2026-09-22 08:09:00',
            'check_in_status' => 'Terlambat',
            'late_minutes' => 4,
        ]);
        Attendance::create([
            'user_id' => $staff->id,
            'attendance_date' => '2026-10-13',
            'check_in' => '2026-10-13 08:16:00',
            'check_in_status' => 'Terlambat',
            'late_minutes' => 11,
        ]);
        Dispensation::create([
            'user_id' => $staff->id,
            'dispensation_date' => '2026-09-29',
            'reason' => null,
        ]);
        LeaveRequest::create([
            'user_id' => $staff->id,
            'request_date' => '2026-10-06',
            'type' => 'izin',
            'reason' => 'Keperluan keluarga',
        ]);

        $response = $this->actingAs($admin)->get('/admin/report/download?user_id='.$staff->id.'&month=2026-10');
        $workbook = $this->readDownloadedWorkbook($response, 'laporan-absensi-raka-2026-10.xlsx');

        $summary = $workbook->getSheetByName('Ringkasan');
        $this->assertSame('Raka · Periode 21/09/2026 - 20/10/2026', $summary->getCell('A2')->getValue());
        $this->assertSame('Hadir', $summary->getCell('A4')->getValue());
        $this->assertSame('Tidak hadir', $summary->getCell('C4')->getValue());
        $this->assertSame('Izin / sakit', $summary->getCell('E4')->getValue());
        $this->assertSame('Total telat', $summary->getCell('A8')->getValue());
        $this->assertSame('Total lembur', $summary->getCell('D8')->getValue());
        $this->assertSame(3, $summary->getCell('A5')->getValue());
        $this->assertSame(1, $summary->getCell('C5')->getValue());
        $this->assertSame(1, $summary->getCell('E5')->getValue());
        $this->assertSame('15 menit', $summary->getCell('A9')->getValue());
        $this->assertSame('0 menit', $summary->getCell('D9')->getValue());
        $this->assertSame('Raka', $summary->getCell('A13')->getValue());
        $this->assertSame(3, $summary->getCell('B13')->getValue());
        $this->assertSame(1, $summary->getCell('C13')->getValue());
        $this->assertSame(15, $summary->getCell('E13')->getValue());
        $this->assertSame('TOTAL', $summary->getCell('A14')->getValue());
        $this->assertSame(15, $summary->getCell('E14')->getValue());

        $dailyStatuses = [];
        for ($row = 2; $row <= $workbook->getSheetByName('Rincian Harian')->getHighestRow(); $row++) {
            $dailyStatuses[] = $workbook->getSheetByName('Rincian Harian')->getCell('C'.$row)->getValue();
        }
        $this->assertContains('Hadir (Dispen)', $dailyStatuses);
        $this->assertContains('Tidak hadir', $dailyStatuses);
        $workbook->disconnectWorksheets();
    }

    public function test_admin_can_download_xlsx_reports_for_all_staff_with_combined_totals(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $admin = User::factory()->create([
            'username' => 'admin',
            'role' => 'admin',
        ]);
        $raka = $this->createStaff('Raka', 'raka');
        $sinta = $this->createStaff('Sinta', 'sinta');
        Attendance::create([
            'user_id' => $raka->id,
            'attendance_date' => '2026-10-13',
            'check_in' => '2026-10-13 08:09:00',
            'check_in_status' => 'Terlambat',
            'late_minutes' => 4,
        ]);
        Attendance::create([
            'user_id' => $sinta->id,
            'attendance_date' => '2026-10-13',
            'check_in' => '2026-10-13 08:12:00',
            'check_in_status' => 'Terlambat',
            'late_minutes' => 7,
        ]);

        $response = $this->actingAs($admin)->get('/admin/report/download?user_id=all&month=2026-10');
        $workbook = $this->readDownloadedWorkbook($response, 'laporan-absensi-semua-anggota-2026-10.xlsx');

        $summary = $workbook->getSheetByName('Ringkasan');
        $this->assertSame('Semua anggota · Periode 21/09/2026 - 20/10/2026', $summary->getCell('A2')->getValue());
        $this->assertSame(2, $summary->getCell('A5')->getValue());
        $this->assertSame(8, $summary->getCell('C5')->getValue());
        $this->assertSame('11 menit', $summary->getCell('A9')->getValue());
        $this->assertSame('Raka', $summary->getCell('A13')->getValue());
        $this->assertSame('Sinta', $summary->getCell('A14')->getValue());
        $this->assertSame('TOTAL', $summary->getCell('A15')->getValue());
        $this->assertSame(8, $summary->getCell('C15')->getValue());
        $this->assertSame(11, $summary->getCell('E15')->getValue());
        $workbook->disconnectWorksheets();
    }

    private function readDownloadedWorkbook(TestResponse $response, string $filename): Spreadsheet
    {
        $response->assertDownload($filename);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $contents = $response->streamedContent();
        $this->assertSame('PK', substr($contents, 0, 2));

        $temporaryPath = tempnam(sys_get_temp_dir(), 'attendance-report-');

        try {
            file_put_contents($temporaryPath, $contents);

            return IOFactory::load($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function createStaff(string $name, string $username): User
    {
        return User::factory()->create([
            'name' => $name,
            'username' => $username,
            'role' => 'staff',
            'work_days' => ['selasa'],
            'work_start' => '08:00',
            'work_end' => '17:00',
        ]);
    }
}
