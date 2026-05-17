<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Letter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BugfixTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?string $phone = null): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'phone_number' => $phone,
        ]);
    }

    /** #1 agenda_number no longer collides on rapid creation */
    public function test_rapid_letter_creation_has_unique_agenda_numbers(): void
    {
        $admin = $this->makeUser('admin');

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $res = $this->actingAs($admin)->postJson('/api/letters', [
                'letter_number' => "N$i",
                'origin' => 'MA',
                'letter_date' => '2026-05-01',
                'type' => 'ordinary',
                'classification' => 'ordinary',
                'subject' => "S$i",
            ]);
            $res->assertCreated();
            $numbers[] = $res->json('agenda_number');
        }

        $this->assertCount(5, array_unique($numbers), 'agenda numbers must be unique');
    }

    /** #3 arbitrary role is rejected */
    public function test_user_role_must_be_whitelisted(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Hax', 'email' => 'hax@x.com', 'password' => 'secret1',
            'role' => 'superadmin',
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Ok', 'email' => 'ok@x.com', 'password' => 'secret1',
            'role' => 'panitera',
        ])->assertCreated();
    }

    /** #2 file validation on update rejects bad types */
    public function test_letter_update_rejects_invalid_file(): void
    {
        $admin = $this->makeUser('admin');
        $letter = Letter::create([
            'agenda_number' => 'AGN-X', 'letter_number' => '1', 'origin' => 'MA',
            'letter_date' => '2026-05-01', 'type' => 'ordinary',
            'classification' => 'ordinary', 'subject' => 'X', 'status' => 'processing',
            'category' => 'incoming',
        ]);

        $bad = \Illuminate\Http\UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload');

        $this->actingAs($admin)
            ->putJson("/api/letters/{$letter->id}", ['file' => $bad])
            ->assertStatus(422);
    }

    /** #5 only admin or letter holder may disposition */
    public function test_disposition_requires_holding_the_letter(): void
    {
        $admin = $this->makeUser('admin');
        $outsider = $this->makeUser('pegawai');
        $target = $this->makeUser('panitera');

        $letter = Letter::create([
            'agenda_number' => 'AGN-Y', 'letter_number' => '2', 'origin' => 'MA',
            'letter_date' => '2026-05-01', 'type' => 'ordinary',
            'classification' => 'ordinary', 'subject' => 'Y', 'status' => 'processing',
            'category' => 'incoming',
        ]);

        // Outsider never received the letter -> forbidden
        $this->actingAs($outsider)->postJson('/api/dispositions', [
            'letter_id' => $letter->id, 'to_user_ids' => [$target->id], 'type' => 'forward',
        ])->assertStatus(403);

        // Admin can always disposition
        $this->actingAs($admin)->postJson('/api/dispositions', [
            'letter_id' => $letter->id, 'to_user_ids' => [$target->id], 'type' => 'disposition',
        ])->assertCreated();
    }

    /** #6 cannot disposition to yourself */
    public function test_cannot_disposition_to_self(): void
    {
        $admin = $this->makeUser('admin');
        $letter = Letter::create([
            'agenda_number' => 'AGN-Z', 'letter_number' => '3', 'origin' => 'MA',
            'letter_date' => '2026-05-01', 'type' => 'ordinary',
            'classification' => 'ordinary', 'subject' => 'Z', 'status' => 'processing',
            'category' => 'incoming',
        ]);

        $this->actingAs($admin)->postJson('/api/dispositions', [
            'letter_id' => $letter->id, 'to_user_ids' => [$admin->id], 'type' => 'forward',
        ])->assertStatus(422);
    }

    /** #7 accept sets read_at even when skipping read */
    public function test_accept_marks_read(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser('panitera');
        $letter = Letter::create([
            'agenda_number' => 'AGN-A', 'letter_number' => '4', 'origin' => 'MA',
            'letter_date' => '2026-05-01', 'type' => 'ordinary',
            'classification' => 'ordinary', 'subject' => 'A', 'status' => 'processing',
            'category' => 'incoming',
        ]);
        $disp = $letter->dispositions()->create([
            'from_user_id' => $admin->id, 'to_user_id' => $target->id,
            'type' => 'disposition', 'status' => 'pending',
        ]);

        $res = $this->actingAs($target)->putJson("/api/dispositions/{$disp->id}/accept");
        $res->assertOk()->assertJsonPath('status', 'accepted');
        $this->assertNotNull($disp->fresh()->read_at);
    }

    /** #8 non-admin user list does not leak email / phone */
    public function test_user_list_does_not_leak_pii_to_non_admin(): void
    {
        $this->makeUser('admin');
        $sek = $this->makeUser('sekretaris');

        $res = $this->actingAs($sek)->getJson('/api/users');
        $res->assertOk();
        $first = $res->json('0');
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayNotHasKey('email', $first);
        $this->assertArrayNotHasKey('phone_number', $first);
    }

    /** #11 WA notification is queued, not sent inline */
    public function test_disposition_queues_whatsapp_job(): void
    {
        Queue::fake();

        $admin = $this->makeUser('admin');
        $target = $this->makeUser('panitera', '081234567890');
        $letter = Letter::create([
            'agenda_number' => 'AGN-Q', 'letter_number' => '5', 'origin' => 'MA',
            'letter_date' => '2026-05-01', 'type' => 'important',
            'classification' => 'secret', 'subject' => 'Q', 'status' => 'processing',
            'category' => 'incoming',
        ]);

        $this->actingAs($admin)->postJson('/api/dispositions', [
            'letter_id' => $letter->id, 'to_user_ids' => [$target->id], 'type' => 'disposition',
        ])->assertCreated();

        Queue::assertPushed(SendWhatsAppNotification::class);
    }
}
