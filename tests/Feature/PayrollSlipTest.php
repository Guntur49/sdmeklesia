<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Dispensation;
use App\Models\PayrollSlip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Tests\TestCase;

class PayrollSlipTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_monthly_slip_for_a_staff_member(): void
    {
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'admin']);
        $staff = $this->createStaff('Raka', 'raka', [
            'base_salary' => 1000000,
            'daily_attendance_allowance' => 10000,
            'daily_meal_allowance' => 5000,
            'daily_transport_allowance' => 3000,
            'bpjs_subsidy' => 25000,
        ]);
        $anotherStaff = $this->createStaff('Sinta', 'sinta');
        Attendance::create([
            'user_id' => $anotherStaff->id,
            'attendance_date' => '2026-10-05',
            'check_in' => '2026-10-05 08:00:00',
        ]);
        Dispensation::create([
            'user_id' => $anotherStaff->id,
            'dispensation_date' => '2026-10-06',
            'reason' => 'Hari dispensasi staff lain',
        ]);
        Attendance::create([
            'user_id' => $staff->id,
            'attendance_date' => '2026-10-01',
            'check_in' => '2026-10-01 08:00:00',
        ]);
        Attendance::create([
            'user_id' => $staff->id,
            'attendance_date' => '2026-10-02',
            'check_in' => '2026-10-02 08:00:00',
        ]);
        Attendance::create([
            'user_id' => $staff->id,
            'attendance_date' => '2026-10-03',
            'check_in' => null,
        ]);
        Dispensation::create([
            'user_id' => $staff->id,
            'dispensation_date' => '2026-10-02',
            'reason' => 'Dispensasi pada hari check-in',
        ]);
        Dispensation::create([
            'user_id' => $staff->id,
            'dispensation_date' => '2026-10-04',
            'reason' => 'Dispensasi tanpa check-in',
        ]);
        Dispensation::create([
            'user_id' => $staff->id,
            'dispensation_date' => '2026-11-01',
            'reason' => 'Di luar bulan slip',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.payroll-slips.store'), [
            'month' => '2026-10',
            'user_id' => $staff->id,
            ...$this->validAmounts(),
        ]);

        $response->assertRedirect(route('admin.payroll-slips.index', [
            'month' => '2026-10',
            'user_id' => $staff->id,
            'edit' => 1,
        ]));
        $response->assertSessionHas('success', 'Slip gaji berhasil disimpan.');
        $this->assertDatabaseHas('payroll_slips', [
            'user_id' => $staff->id,
            'month' => '2026-10',
            'base_salary' => 1000000,
            'attendance' => 30000,
            'umkm' => 15000,
            'transport' => 9000,
            'bpjs' => 25000,
            'thr' => 500000,
            'bonus' => 200000,
            'bpjs_deduction' => 20000,
            'sanction' => 5000,
            'honor_received' => 50000,
            'lateness' => 10000,
        ]);

        $slip = PayrollSlip::query()->firstOrFail();
        $this->assertSame(1914000, $slip->earningsTotal());
        $this->assertSame(110000, $slip->deductionsTotal());
        $this->assertSame(1804000, $slip->takeHomePay());

        $this->actingAs($admin)->get(route('admin.payroll-slips.index', [
            'month' => '2026-10',
            'user_id' => $staff->id,
        ]))
            ->assertSeeText('Jumlah hari dibayar: 3 hari')
            ->assertSeeText('Uang makan')
            ->assertSeeText('THR')
            ->assertSeeText('Bonus')
            ->assertSeeText('Potongan BPJS')
            ->assertSeeText('Angsuran koperasi')
            ->assertSeeText('Sanksi')
            ->assertSeeText('Honor yang sudah diterima')
            ->assertSee('value="30000"', false);
    }

    public function test_saving_an_existing_staff_and_month_updates_the_same_slip(): void
    {
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'admin']);
        $staff = $this->createStaff('Raka', 'raka', ['base_salary' => 1000000]);
        $payload = ['month' => '2026-10', 'user_id' => $staff->id, ...$this->validAmounts()];

        $this->actingAs($admin)->post(route('admin.payroll-slips.store'), $payload);
        $staff->update(['base_salary' => 1200000]);
        $response = $this->actingAs($admin)->post(route('admin.payroll-slips.store'), [
            ...$payload,
            'base_salary' => 1,
        ]);

        $response->assertSessionHas('success', 'Slip gaji berhasil diperbarui.');
        $this->assertDatabaseCount('payroll_slips', 1);
        $this->assertDatabaseHas('payroll_slips', [
            'user_id' => $staff->id,
            'month' => '2026-10',
            'base_salary' => 1200000,
        ]);
    }

    public function test_admin_can_filter_the_payroll_list_by_month_and_staff(): void
    {
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'admin']);
        $raka = $this->createStaff('Raka', 'raka');
        $sinta = $this->createStaff('Sinta', 'sinta');
        $rakaSlip = PayrollSlip::query()->create([
            'user_id' => $raka->id,
            'month' => '2026-10',
            ...$this->validAmounts(),
        ]);
        $sintaSlip = PayrollSlip::query()->create([
            'user_id' => $sinta->id,
            'month' => '2026-10',
            ...$this->validAmounts(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payroll-slips.index', [
            'month' => '2026-10',
            'user_id' => $raka->id,
        ]));

        $response->assertSeeText('Gaji staff');
        $response->assertSeeText('Raka');
        $response->assertSee('href="'.route('admin.payroll-slips.download', $rakaSlip).'"', false);
        $response->assertDontSee('href="'.route('admin.payroll-slips.download', $sintaSlip).'"', false);
    }

    public function test_staff_cannot_access_payroll_slips_and_invalid_staff_selection_is_rejected(): void
    {
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'admin']);
        $staff = $this->createStaff('Raka', 'raka', ['employment_status' => 'kontrak']);

        $this->get(route('admin.payroll-slips.index'))->assertRedirect(route('login'));
        $this->actingAs($staff)->get(route('admin.payroll-slips.index'))->assertForbidden();
        $response = $this->actingAs($admin)->from(route('admin.payroll-slips.index'))->post(route('admin.payroll-slips.store'), [
            'month' => '2026-13',
            'user_id' => $admin->id,
            ...$this->validAmounts(),
        ]);

        $response->assertSessionHasErrors(['month', 'user_id']);
        $this->assertDatabaseCount('payroll_slips', 0);
    }

    public function test_admin_can_download_an_individual_slip_as_an_xlsx_file(): void
    {
        $this->travelTo('2026-10-20 12:00:00');

        $admin = User::factory()->create(['username' => 'admin', 'role' => 'admin']);
        $staff = $this->createStaff('Raka', 'raka', ['employment_status' => 'kontrak']);
        $slip = PayrollSlip::query()->create([
            'user_id' => $staff->id,
            'month' => '2026-10',
            ...$this->validAmounts(),
        ]);
        $slip->forceFill(['created_at' => '2026-10-01 09:30:00'])->save();

        $response = $this->actingAs($admin)->get(route('admin.payroll-slips.download', $slip));
        $workbook = $this->readDownloadedWorkbook($response, 'slip-gaji-raka-2026-10.xlsx');

        $sheet = $workbook->getSheetByName('Slip Gaji');
        $this->assertSame('EKLESIA GROUP', $sheet->getCell('A1')->getValue());
        $this->assertSame('Jl. Supersemar No.09, Ngronggo - Kota Kediri', $sheet->getCell('A2')->getValue());
        $this->assertSame('SLIP GAJI · Oktober 2026', $sheet->getCell('A3')->getValue());
        $this->assertSame(1, $sheet->getStyle('A4')->getAlignment()->getIndent());
        $this->assertSame(1, $sheet->getStyle('A5')->getAlignment()->getIndent());
        $this->assertSame(1, $sheet->getStyle('A6')->getAlignment()->getIndent());
        $this->assertSame('Raka', $sheet->getCell('B4')->getValue());
        $this->assertSame('PERIODE', $sheet->getCell('A5')->getValue());
        $this->assertSame('21 September 2026 - 20 Oktober 2026', $sheet->getCell('B5')->getValue());
        $this->assertSame('STATUS KARYAWAN', $sheet->getCell('A6')->getValue());
        $this->assertSame('Kontrak', $sheet->getCell('B6')->getValue());
        $this->assertSame('RINCIAN', $sheet->getCell('A7')->getValue());
        $this->assertSame('JUMLAH', $sheet->getCell('C7')->getValue());
        $this->assertSame(Alignment::HORIZONTAL_CENTER, $sheet->getStyle('C7')->getAlignment()->getHorizontal());
        $this->assertSame('PENERIMAAN', $sheet->getCell('A8')->getValue());
        $this->assertNull($sheet->getCell('C8')->getValue());
        $this->assertSame('Gaji pokok', $sheet->getCell('A9')->getValue());
        $this->assertSame(1000000, $sheet->getCell('C9')->getValue());
        $this->assertSame('"Rp" #,##0', $sheet->getStyle('C9')->getNumberFormat()->getFormatCode());
        $this->assertSame(Alignment::HORIZONTAL_RIGHT, $sheet->getStyle('C9')->getAlignment()->getHorizontal());
        $this->assertSame('Tanggal merah', $sheet->getCell('A17')->getValue());
        $this->assertSame('THR', $sheet->getCell('A18')->getValue());
        $this->assertSame(500000, $sheet->getCell('C18')->getValue());
        $this->assertSame('Bonus', $sheet->getCell('A19')->getValue());
        $this->assertSame(200000, $sheet->getCell('C19')->getValue());
        $this->assertSame('TOTAL PENERIMAAN', $sheet->getCell('A20')->getValue());
        $this->assertSame(2010000, $sheet->getCell('C20')->getValue());
        $this->assertSame('POTONGAN', $sheet->getCell('A21')->getValue());
        $this->assertNull($sheet->getCell('C21')->getValue());
        $this->assertSame('Keterlambatan', $sheet->getCell('A22')->getValue());
        $this->assertSame('Potongan BPJS', $sheet->getCell('A23')->getValue());
        $this->assertSame(20000, $sheet->getCell('C23')->getValue());
        $this->assertSame('Angsuran koperasi', $sheet->getCell('A24')->getValue());
        $this->assertSame('Sanksi', $sheet->getCell('A25')->getValue());
        $this->assertSame('Honor yang sudah diterima', $sheet->getCell('A26')->getValue());
        $this->assertSame('TOTAL POTONGAN', $sheet->getCell('A27')->getValue());
        $this->assertSame(110000, $sheet->getCell('C27')->getValue());
        $this->assertSame('GAJI DITERIMA', $sheet->getCell('A28')->getValue());
        $this->assertSame(1900000, $sheet->getCell('C28')->getValue());
        $this->assertSame('Kediri, 01 Oktober 2026', $sheet->getCell('A29')->getValue());
        $this->assertSame(Alignment::HORIZONTAL_RIGHT, $sheet->getStyle('A29')->getAlignment()->getHorizontal());
        $this->assertSame('Mengetahui', $sheet->getCell('A30')->getValue());
        $this->assertSame('A.n Direktur EKF Group', $sheet->getCell('A31')->getValue());
        $this->assertSame('Penerima', $sheet->getCell('C31')->getValue());
        $this->assertSame('Fitri Wahyu Kartika, SE', $sheet->getCell('A35')->getValue());
        $this->assertSame('Personalia', $sheet->getCell('A36')->getValue());
        $this->assertSame('Raka', $sheet->getCell('C35')->getValue());
        $this->assertSame(36, $sheet->getHighestRow());
        $this->assertSame('D', $sheet->getHighestColumn());
        $this->assertSame(Border::BORDER_THIN, $sheet->getStyle('A7')->getBorders()->getTop()->getBorderStyle());
        $this->assertSame(Border::BORDER_THIN, $sheet->getStyle('C12')->getBorders()->getTop()->getBorderStyle());
        $this->assertSame(Border::BORDER_THIN, $sheet->getStyle('D28')->getBorders()->getBottom()->getBorderStyle());
        $this->assertSame(Border::BORDER_NONE, $sheet->getStyle('A30')->getBorders()->getTop()->getBorderStyle());
        $this->assertSame(Border::BORDER_NONE, $sheet->getStyle('A30')->getBorders()->getBottom()->getBorderStyle());
        $this->assertSame(Border::BORDER_NONE, $sheet->getStyle('C30')->getBorders()->getTop()->getBorderStyle());
        $this->assertSame(Border::BORDER_NONE, $sheet->getStyle('C30')->getBorders()->getBottom()->getBorderStyle());
        $this->assertContains('A8:B8', $sheet->getMergeCells());
        $this->assertContains('C8:D8', $sheet->getMergeCells());
        $this->assertContains('A9:B9', $sheet->getMergeCells());
        $this->assertContains('C9:D9', $sheet->getMergeCells());
        $this->assertContains('A1:D1', $sheet->getMergeCells());
        $this->assertContains('A2:D2', $sheet->getMergeCells());
        $this->assertContains('A3:D3', $sheet->getMergeCells());
        $this->assertContains('A29:D29', $sheet->getMergeCells());
        $this->assertContains('A30:B30', $sheet->getMergeCells());
        $this->assertContains('C31:D31', $sheet->getMergeCells());
        $this->assertContains('C32:D34', $sheet->getMergeCells());
        $this->assertSame(22.0, $sheet->getColumnDimension('A')->getWidth());
        $this->assertSame(PageSetup::PAPERSIZE_A5, $sheet->getPageSetup()->getPaperSize());
        $this->assertSame(PageSetup::ORIENTATION_PORTRAIT, $sheet->getPageSetup()->getOrientation());
        $this->assertSame(1, $sheet->getPageSetup()->getFitToWidth());
        $this->assertSame(1, $sheet->getPageSetup()->getFitToHeight());
        $this->assertSame('A1:D36', $sheet->getPageSetup()->getPrintArea());
        $this->assertCount(1, $sheet->getDrawingCollection());
        $this->assertSame('A1', $sheet->getDrawingCollection()->offsetGet(0)->getCoordinates());
        $this->assertSame(78, $sheet->getDrawingCollection()->offsetGet(0)->getHeight());
        $this->assertSame(30.0, $sheet->getRowDimension(1)->getRowHeight());
        $this->assertSame(18.0, $sheet->getRowDimension(2)->getRowHeight());
        $this->assertFalse($sheet->getShowGridlines());
        $workbook->disconnectWorksheets();
    }

    /**
     * @return array<string, int>
     */
    private function validAmounts(): array
    {
        return [
            'base_salary' => 1000000,
            'attendance' => 100000,
            'umkm' => 0,
            'transport' => 50000,
            'bpjs' => 25000,
            'other_honor' => 85000,
            'position_allowance' => 0,
            'overtime' => 50000,
            'holiday' => 0,
            'thr' => 500000,
            'bonus' => 200000,
            'deductions' => 10000,
            'lateness' => 10000,
            'bpjs_deduction' => 20000,
            'cooperative_deduction' => 15000,
            'sanction' => 5000,
            'honor_received' => 50000,
        ];
    }

    private function readDownloadedWorkbook(TestResponse $response, string $filename): Spreadsheet
    {
        $response->assertDownload($filename);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $contents = $response->streamedContent();
        $this->assertSame('PK', substr($contents, 0, 2));

        $temporaryPath = tempnam(sys_get_temp_dir(), 'payroll-slip-');

        try {
            file_put_contents($temporaryPath, $contents);

            return IOFactory::load($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function createStaff(string $name, string $username, array $attributes = []): User
    {
        return User::factory()->create([
            'name' => $name,
            'username' => $username,
            'role' => 'staff',
            ...$attributes,
        ]);
    }
}
