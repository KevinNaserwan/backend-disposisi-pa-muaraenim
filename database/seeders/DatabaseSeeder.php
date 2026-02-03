<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // 1. Admin
        User::create([
            'name' => 'Admin System',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // 2. Kasubbag Umum & Keuangan
        User::create([
            'name' => 'Kasubbag Umum & Keuangan',
            'email' => 'kasubbag_umum@example.com',
            'password' => bcrypt('password'),
            'role' => 'kasubbag_umum',
            'phone_number' => '6281234567891',
        ]);

        // 3. Kasubbag Kepegawaian
        User::create([
            'name' => 'Kasubbag Kepegawaian',
            'email' => 'kasubbag_kepegawaian@example.com',
            'password' => bcrypt('password'),
            'role' => 'kasubbag_kepegawaian',
            'phone_number' => '6281234567892',
        ]);

        // 4. Kasubbag PTIP
        User::create([
            'name' => 'Kasubbag PTIP',
            'email' => 'kasubbag_ptip@example.com',
            'password' => bcrypt('password'),
            'role' => 'kasubbag_ptip',
            'phone_number' => '6281234567893',
        ]);

        // 5. Sekretaris
        User::create([
            'name' => 'Sekretaris',
            'email' => 'sekretaris@example.com',
            'password' => bcrypt('password'),
            'role' => 'sekretaris',
            'phone_number' => '6281234567894',
        ]);

        // 6. Panitera
        User::create([
            'name' => 'Panitera',
            'email' => 'panitera@example.com',
            'password' => bcrypt('password'),
            'role' => 'panitera',
            'phone_number' => '6281234567895',
        ]);

        // 7. Wakil Ketua
        User::create([
            'name' => 'Wakil Ketua',
            'email' => 'wakil_ketua@example.com',
            'password' => bcrypt('password'),
            'role' => 'wakil_ketua',
            'phone_number' => '6281234567896',
        ]);

        // 8. Ketua
        User::create([
            'name' => 'Ketua',
            'email' => 'ketua@example.com',
            'password' => bcrypt('password'),
            'role' => 'ketua',
            'phone_number' => '6281234567897',
        ]);

        // 9. Pegawai (Contoh)
        User::create([
            'name' => 'Pegawai Staff',
            'email' => 'pegawai@example.com',
            'password' => bcrypt('password'),
            'role' => 'pegawai',
            'phone_number' => '6281234567898',
        ]);

        // 10. Admin Arsip
        User::create([
            'name' => 'Admin Arsip',
            'email' => 'admin_arsip@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin_arsip',
            'phone_number' => '6281234567899',
        ]);

        // 11. Panmud Hukum
        User::create([
            'name' => 'Panmud Hukum',
            'email' => 'panmud_hukum@example.com',
            'password' => bcrypt('password'),
            'role' => 'panmud_hukum',
            'phone_number' => '6281234567800',
        ]);

        // 12. Panmud Permohonan
        User::create([
            'name' => 'Panmud Permohonan',
            'email' => 'panmud_permohonan@example.com',
            'password' => bcrypt('password'),
            'role' => 'panmud_permohonan',
            'phone_number' => '6281234567801',
        ]);

        // 13. Panmud Gugatan
        User::create([
            'name' => 'Panmud Gugatan',
            'email' => 'panmud_gugatan@example.com',
            'password' => bcrypt('password'),
            'role' => 'panmud_gugatan',
            'phone_number' => '6281234567802',
        ]);

        // 14. Plh. Sekretaris
        User::create([
            'name' => 'Plh. Sekretaris',
            'email' => 'plh_sekretaris@example.com',
            'password' => bcrypt('password'),
            'role' => 'plh_sekretaris',
            'phone_number' => '6281234567803',
        ]);

        // 15. Plh. Ketua
        User::create([
            'name' => 'Plh. Ketua',
            'email' => 'plh_ketua@example.com',
            'password' => bcrypt('password'),
            'role' => 'plh_ketua',
            'phone_number' => '6281234567804',
        ]);
    }
}
