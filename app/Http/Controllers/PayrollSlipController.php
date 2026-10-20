<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Dispensation;
use App\Models\PayrollSlip;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollSlipController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'staff')],
            'edit' => ['nullable', 'integer', 'min:1'],
        ]);
        $staff = User::query()->where('role', 'staff')->orderBy('name')->get();
        $month = $filters['month'] ?? now()->format('Y-m');
        $selectedUserId = isset($filters['user_id']) ? (int) $filters['user_id'] : null;
        $editingSlip = null;

        if (isset($filters['edit'])) {
            $editingSlip = PayrollSlip::query()
                ->with('user')
                ->whereHas('user', fn ($query) => $query->where('role', 'staff'))
                ->findOrFail((int) $filters['edit']);
            $month = $editingSlip->month;
            $selectedUserId = $editingSlip->user_id;
        }

        $slips = PayrollSlip::query()
            ->with('user')
            ->where('month', $month)
            ->when($selectedUserId, fn ($query) => $query->where('user_id', $selectedUserId))
            ->get()
            ->sortBy(fn (PayrollSlip $slip): string => $slip->user->name)
            ->values();

        $paidDaysByStaff = $this->paidDaysByStaff($staff->modelKeys(), $month);
        $payrollUserId = $editingSlip?->user_id ?? $selectedUserId ?? $staff->first()?->id;
        $payrollStaff = $staff->firstWhere('id', $payrollUserId);
        $initialPaidDays = $payrollUserId === null ? 0 : ($paidDaysByStaff[$payrollUserId] ?? 0);
        $automaticAmounts = $payrollStaff
            ? $this->automaticAmounts($payrollStaff, $initialPaidDays)
            : array_fill_keys(PayrollSlip::AUTOMATIC_EARNING_FIELDS, 0);

        return view('admin.payroll-slips', compact(
            'staff',
            'month',
            'selectedUserId',
            'editingSlip',
            'slips',
            'paidDaysByStaff',
            'payrollUserId',
            'initialPaidDays',
            'automaticAmounts',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $editableAmountFields = array_diff(
            array_keys(PayrollSlip::EARNING_FIELDS + PayrollSlip::DEDUCTION_FIELDS),
            PayrollSlip::AUTOMATIC_EARNING_FIELDS,
        );
        $amountRules = array_fill_keys($editableAmountFields, [
            'required',
            'integer',
            'min:0',
            'max:999999999999999',
        ]);
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'staff')],
            ...$amountRules,
        ]);

        $userId = (int) $data['user_id'];
        $staff = User::query()->where('role', 'staff')->findOrFail($userId);
        $paidDays = $this->paidDaysByStaff([$userId], $data['month'])[$userId] ?? 0;
        $amounts = $this->automaticAmounts($staff, $paidDays);

        foreach ($editableAmountFields as $field) {
            $amounts[$field] = (int) $data[$field];
        }

        $slip = PayrollSlip::updateOrCreate(
            ['user_id' => $userId, 'month' => $data['month']],
            $amounts,
        );

        return redirect()
            ->route('admin.payroll-slips.index', [
                'month' => $slip->month,
                'user_id' => $slip->user_id,
                'edit' => $slip->id,
            ])
            ->with('success', $slip->wasRecentlyCreated ? 'Slip gaji berhasil disimpan.' : 'Slip gaji berhasil diperbarui.');
    }

    /**
     * @param  array<int, int>  $staffIds
     * @return array<int, int>
     */
    private function paidDaysByStaff(array $staffIds, string $month): array
    {
        if ($staffIds === []) {
            return [];
        }

        $date = Carbon::createFromFormat('!Y-m', $month);
        $dateRange = [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()];

        $attendanceDatesByStaff = Attendance::query()
            ->whereIn('user_id', $staffIds)
            ->whereBetween('attendance_date', $dateRange)
            ->whereNotNull('check_in')
            ->distinct()
            ->get(['user_id', 'attendance_date'])
            ->groupBy('user_id')
            ->map(fn (Collection $records): array => $records
                ->pluck('attendance_date')
                ->map(fn (Carbon $attendanceDate): string => $attendanceDate->toDateString())
                ->all());

        $dispensationDatesByStaff = Dispensation::query()
            ->whereIn('user_id', $staffIds)
            ->whereBetween('dispensation_date', $dateRange)
            ->distinct()
            ->get(['user_id', 'dispensation_date'])
            ->groupBy('user_id')
            ->map(fn (Collection $records): array => $records
                ->pluck('dispensation_date')
                ->map(fn (Carbon $dispensationDate): string => $dispensationDate->toDateString())
                ->all());

        $paidDaysByStaff = [];

        foreach ($staffIds as $staffId) {
            $dates = [
                ...$attendanceDatesByStaff->get($staffId, []),
                ...$dispensationDatesByStaff->get($staffId, []),
            ];
            $paidDaysByStaff[$staffId] = count(array_unique($dates));
        }

        return $paidDaysByStaff;
    }

    /**
     * @return array<string, int>
     */
    private function automaticAmounts(User $staff, int $paidDays): array
    {
        return [
            'base_salary' => (int) $staff->base_salary,
            'attendance' => (int) $staff->daily_attendance_allowance * $paidDays,
            'umkm' => (int) $staff->daily_meal_allowance * $paidDays,
            'transport' => (int) $staff->daily_transport_allowance * $paidDays,
            'bpjs' => (int) $staff->bpjs_subsidy,
        ];
    }

    public function download(PayrollSlip $payrollSlip): StreamedResponse
    {
        abort_unless($payrollSlip->user()->where('role', 'staff')->exists(), 404);

        $payrollSlip->load('user');
        $spreadsheet = $this->createSpreadsheet($payrollSlip);
        $filename = 'slip-gaji-'.$payrollSlip->user->username.'-'.$payrollSlip->month.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function createSpreadsheet(PayrollSlip $slip): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $periodEnd = Carbon::createFromFormat('!Y-m', $slip->month)->day(20);
        $periodStart = $periodEnd->copy()->subMonth()->day(21);
        $period = $periodStart->locale('id')->translatedFormat('d F Y')
            .' - '.$periodEnd->locale('id')->translatedFormat('d F Y');
        $sheet->setTitle('Slip Gaji');
        $sheet->setShowGridlines(false);
        $logoPath = storage_path('app/public/logo eklesia.png');
        $logo = new Drawing;
        $logo->setName('Logo Eklesia');
        $logo->setDescription('Logo Eklesia');
        $logo->setPath($logoPath);
        $logo->setCoordinates('A1');
        $logo->setHeight(78);
        $logo->setOffsetX(10);
        $logo->setOffsetY(4);
        $logo->setWorksheet($sheet);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->getRowDimension(3)->setRowHeight(28);
        $sheet->getRowDimension(5)->setRowHeight(30);
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'EKLESIA GROUP');
        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A2', 'Jl. Supersemar No.09, Ngronggo - Kota Kediri');
        $sheet->mergeCells('A3:D3');
        $sheet->setCellValue('A3', 'SLIP GAJI · '.Carbon::createFromFormat('Y-m', $slip->month)->locale('id')->translatedFormat('F Y'));
        $sheet->setCellValue('A4', 'NAMA');
        $sheet->mergeCells('B4:D4');
        $sheet->setCellValueExplicit('B4', $slip->user->name, DataType::TYPE_STRING);
        $sheet->setCellValue('A5', 'PERIODE');
        $sheet->mergeCells('B5:D5');
        $sheet->setCellValue('B5', $period);
        $sheet->setCellValue('A6', 'STATUS KARYAWAN');
        $sheet->mergeCells('B6:D6');
        $sheet->setCellValue('B6', ucfirst($slip->user->employment_status));
        $sheet->getStyle('A4:D6')->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('A4:A6')->getAlignment()->setIndent(1);
        $sheet->getStyle('B4:D6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->mergeCells('A7:B7');
        $sheet->mergeCells('C7:D7');
        $sheet->setCellValue('A7', 'RINCIAN');
        $sheet->setCellValue('C7', 'JUMLAH');
        $row = 8;

        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->mergeCells('C'.$row.':D'.$row);
        $sheet->setCellValue('A'.$row, 'PENERIMAAN');
        $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true);
        $row++;

        foreach (PayrollSlip::EARNING_FIELDS as $field => $label) {
            $sheet->mergeCells('A'.$row.':B'.$row);
            $sheet->mergeCells('C'.$row.':D'.$row);
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('C'.$row, $slip->getAttribute($field));
            $row++;
        }

        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->mergeCells('C'.$row.':D'.$row);
        $sheet->setCellValue('A'.$row, 'TOTAL PENERIMAAN');
        $sheet->setCellValue('C'.$row, $slip->earningsTotal());
        $totalEarningsRow = $row++;

        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->mergeCells('C'.$row.':D'.$row);
        $sheet->setCellValue('A'.$row, 'POTONGAN');
        $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true);
        $row++;

        foreach (PayrollSlip::DEDUCTION_FIELDS as $field => $label) {
            if ($field === 'deductions') {
                continue;
            }

            $sheet->mergeCells('A'.$row.':B'.$row);
            $sheet->mergeCells('C'.$row.':D'.$row);
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('C'.$row, $slip->getAttribute($field));
            $row++;
        }

        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->mergeCells('C'.$row.':D'.$row);
        $sheet->setCellValue('A'.$row, 'TOTAL POTONGAN');
        $sheet->setCellValue('C'.$row, $slip->deductionsTotal());
        $totalDeductionsRow = $row++;
        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->mergeCells('C'.$row.':D'.$row);
        $sheet->setCellValue('A'.$row, 'GAJI DITERIMA');
        $sheet->setCellValue('C'.$row, $slip->takeHomePay());

        $signatureDateRow = $row + 1;
        $signatureHeadingRow = $signatureDateRow + 1;
        $signatureNameRow = $signatureHeadingRow + 5;
        $sheet->mergeCells('A'.$signatureDateRow.':D'.$signatureDateRow);
        $sheet->setCellValue(
            'A'.$signatureDateRow,
            'Kediri, '.$slip->created_at->locale('id')->translatedFormat('d F Y'),
        );
        $sheet->getStyle('A'.$signatureDateRow.':D'.$signatureDateRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->mergeCells('A'.$signatureHeadingRow.':B'.$signatureHeadingRow);
        $sheet->setCellValue('A'.$signatureHeadingRow, 'Mengetahui');
        $sheet->mergeCells('A'.($signatureHeadingRow + 1).':B'.($signatureHeadingRow + 1));
        $sheet->setCellValue('A'.($signatureHeadingRow + 1), 'A.n Direktur EKF Group');
        $sheet->mergeCells('A'.($signatureHeadingRow + 2).':B'.($signatureNameRow - 1));
        $sheet->setCellValue('C'.($signatureHeadingRow + 1), 'Penerima');
        $sheet->mergeCells('C'.($signatureHeadingRow + 1).':D'.($signatureHeadingRow + 1));
        $sheet->mergeCells('C'.($signatureHeadingRow + 2).':D'.($signatureNameRow - 1));
        $sheet->mergeCells('A'.$signatureNameRow.':B'.$signatureNameRow);
        $sheet->mergeCells('C'.$signatureNameRow.':D'.$signatureNameRow);
        $sheet->setCellValue('A'.$signatureNameRow, 'Fitri Wahyu Kartika, SE');
        $sheet->setCellValue('C'.$signatureNameRow, $slip->user->name);
        $sheet->mergeCells('A'.($signatureNameRow + 1).':B'.($signatureNameRow + 1));
        $sheet->setCellValue('A'.($signatureNameRow + 1), 'Personalia');
        $sheet->getStyle('A'.$signatureHeadingRow.':D'.($signatureNameRow + 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('A'.$signatureNameRow.':D'.($signatureNameRow + 1))->getFont()->setBold(true);
        $sheet->getStyle('A'.($signatureHeadingRow + 2).':D'.($signatureNameRow - 1))
            ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        for ($signatureRow = $signatureHeadingRow + 2; $signatureRow < $signatureNameRow; $signatureRow++) {
            $sheet->getRowDimension($signatureRow)->setRowHeight(20);
        }

        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => '54250F']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A2:D2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '896B5A']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A3:D3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'A84316']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A7:D7')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C95A1D']],
        ]);
        $sheet->getStyle('A7:B7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('C7:D7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C8:D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A'.$totalEarningsRow.':D'.$totalEarningsRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$totalDeductionsRow.':D'.$totalDeductionsRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':D'.$row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '54250F']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF1E3']],
        ]);
        $sheet->getStyle('C8:C'.$row)->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle('A7:D'.$row)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A5)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(1)
            ->setPrintArea('A1:D'.($signatureNameRow + 1));

        return $spreadsheet;
    }
}
