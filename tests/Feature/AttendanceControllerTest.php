<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\Dispensation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_within_five_minute_grace_period_is_on_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 08:05:00'));
        $user = $this->createStaffWithAttendanceLocation();

        $response = $this->actingAs($user)
            ->from('/staff')
            ->post('/staff/attendance', $this->validCheckInData());

        $response->assertRedirect('/staff');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_date' => '2026-10-13 00:00:00',
            'check_in_status' => 'Tepat waktu',
            'late_minutes' => 0,
        ]);
    }

    public function test_check_in_more_than_five_minutes_late_records_late_time_after_grace_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 08:05:01'));
        $user = $this->createStaffWithAttendanceLocation();

        $response = $this->actingAs($user)
            ->from('/staff')
            ->post('/staff/attendance', $this->validCheckInData());

        $response->assertRedirect('/staff');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_date' => '2026-10-13 00:00:00',
            'check_in_status' => 'Terlambat',
            'late_minutes' => 1,
        ]);
    }

    public function test_staff_with_dispensation_cannot_check_in(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 08:00:00'));
        $user = $this->createStaffWithAttendanceLocation();
        Dispensation::create([
            'user_id' => $user->id,
            'dispensation_date' => '2026-10-13',
            'reason' => 'Dinas luar kota',
        ]);

        $response = $this->actingAs($user)
            ->from('/staff')
            ->post('/staff/attendance', $this->validCheckInData());

        $response->assertRedirect('/staff');
        $response->assertSessionHas('error', 'Anda mendapat dispensasi untuk hari ini.');
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'attendance_date' => '2026-10-13 00:00:00',
        ]);
    }

    public function test_staff_with_dispensation_cannot_check_out(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 17:00:00'));
        $user = $this->createStaffWithAttendanceLocation();
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-10-13',
            'check_in' => '2026-10-13 08:00:00',
            'check_in_status' => 'Tepat waktu',
        ]);
        Dispensation::create([
            'user_id' => $user->id,
            'dispensation_date' => '2026-10-13',
            'reason' => 'Dinas luar kota',
        ]);

        $response = $this->actingAs($user)
            ->from('/staff')
            ->post('/staff/attendance', [
                ...$this->validCheckInData(),
                'action' => 'check_out',
            ]);

        $response->assertRedirect('/staff');
        $response->assertSessionHas('error', 'Anda mendapat dispensasi untuk hari ini.');
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'check_out' => null,
        ]);
    }

    public function test_staff_dashboard_hides_attendance_camera_when_dispensated_today(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 08:00:00'));
        $user = $this->createStaffWithAttendanceLocation();
        Dispensation::create([
            'user_id' => $user->id,
            'dispensation_date' => '2026-10-13',
            'reason' => 'Dinas luar kota',
        ]);

        $response = $this->actingAs($user)->get('/staff');

        $response->assertSeeText('Anda dijadwalkan dispensasi hari ini sehingga tidak dapat melakukan absensi.');
        $response->assertSeeText('Dinas luar kota');
        $response->assertDontSee('id="camera"');
        $response->assertDontSee('id="attendanceForm"');
    }

    public function test_admin_can_create_staff_with_employment_and_compensation_details(): void
    {
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'admin']);
        $data = $this->validStaffData();

        $this->actingAs($admin)
            ->get(route('admin.staff'))
            ->assertSee('name="employment_status"', false)
            ->assertSee('name="base_salary"', false)
            ->assertSee('name="daily_attendance_allowance"', false)
            ->assertSee('name="daily_meal_allowance"', false)
            ->assertSee('name="daily_transport_allowance"', false)
            ->assertSee('name="bpjs_subsidy"', false)
            ->assertSee('function formatCurrencyInput(input)', false)
            ->assertSee("prefix.textContent = 'Rp'", false);

        $response = $this->actingAs($admin)
            ->from(route('admin.staff'))
            ->post(route('admin.staff.store'), $data);

        $response->assertRedirect(route('admin.staff'));
        $this->assertDatabaseHas('users', [
            'username' => 'staff-baru',
            'employment_status' => 'kontrak',
            'base_salary' => 5000000,
            'daily_attendance_allowance' => 25000,
            'daily_meal_allowance' => 30000,
            'daily_transport_allowance' => 15000,
            'bpjs_subsidy' => 200000,
        ]);
    }

    public function test_admin_can_update_staff_employment_and_compensation_details(): void
    {
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'admin']);
        $staff = User::factory()->create([
            'username' => 'staff',
            'role' => 'staff',
            'employment_status' => 'tetap',
        ]);
        $data = [
            ...$this->validStaffData(),
            'name' => $staff->name,
            'username' => $staff->username,
            'password' => '',
            'employment_status' => 'kontrak',
            'base_salary' => 6000000,
            'daily_attendance_allowance' => 35000,
            'daily_meal_allowance' => 40000,
            'daily_transport_allowance' => 25000,
            'bpjs_subsidy' => 300000,
        ];

        $response = $this->actingAs($admin)
            ->from(route('admin.staff'))
            ->patch(route('admin.staff.update', $staff), $data);

        $response->assertRedirect(route('admin.staff'));
        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'employment_status' => 'kontrak',
            'base_salary' => 6000000,
            'daily_attendance_allowance' => 35000,
            'daily_meal_allowance' => 40000,
            'daily_transport_allowance' => 25000,
            'bpjs_subsidy' => 300000,
        ]);
    }

    public function test_admin_cannot_save_unsupported_employment_status_or_negative_compensation(): void
    {
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'admin']);
        $data = [
            ...$this->validStaffData(),
            'employment_status' => 'harian',
            'bpjs_subsidy' => -1,
        ];

        $response = $this->actingAs($admin)
            ->from(route('admin.staff'))
            ->post(route('admin.staff.store'), $data);

        $response->assertSessionHasErrors(['employment_status', 'bpjs_subsidy']);
        $this->assertDatabaseMissing('users', ['username' => 'staff-baru']);
    }

    /**
     * @return array<string, string>
     */
    private function validCheckInData(): array
    {
        return [
            'action' => 'check_in',
            'latitude' => '0',
            'longitude' => '0',
            'face_verified' => '1',
            'blink_verified' => '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validStaffData(): array
    {
        return [
            'name' => 'Staff Baru',
            'username' => 'staff-baru',
            'password' => 'password',
            'employment_status' => 'kontrak',
            'base_salary' => 5000000,
            'daily_attendance_allowance' => 25000,
            'daily_meal_allowance' => 30000,
            'daily_transport_allowance' => 15000,
            'bpjs_subsidy' => 200000,
            'work_days' => ['senin'],
            'work_schedule' => [
                'senin' => ['start' => '08:00', 'end' => '17:00'],
            ],
        ];
    }

    private function createStaffWithAttendanceLocation(): User
    {
        $user = User::factory()->create([
            'username' => 'staff',
            'role' => 'staff',
            'work_days' => ['selasa'],
            'work_start' => '08:00',
            'work_end' => '17:00',
        ]);

        AppSetting::create([
            'key' => 'attendance_location',
            'value' => json_encode(['latitude' => 0, 'longitude' => 0]),
        ]);

        return $user;
    }
}
