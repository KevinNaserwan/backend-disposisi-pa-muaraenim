<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Allowed roles in the system. Used to validate role input so
     * arbitrary/privileged roles cannot be injected.
     *
     * @var list<string>
     */
    public const ROLES = [
        'admin',
        'admin_arsip',
        'kasubbag_umum',
        'kasubbag_kepegawaian',
        'kasubbag_ptip',
        'sekretaris',
        'plh_sekretaris',
        'panitera',
        'panmud_hukum',
        'panmud_permohonan',
        'panmud_gugatan',
        'wakil_ketua',
        'ketua',
        'plh_ketua',
        'pegawai',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sentDispositions()
    {
        return $this->hasMany(Disposition::class, 'from_user_id');
    }

    public function receivedDispositions()
    {
        return $this->hasMany(Disposition::class, 'to_user_id');
    }
}
