<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Casts\E164Phone;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// role і is_active не масово присвоювані: їх змінюють лише явно (команда, дія адміна)
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Як DEFAULT у базі, щоб щойно створена модель не мала null до refresh(). */
    protected $attributes = [
        'is_active' => true,
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
            'phone' => E164Phone::class,
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Вхід шукає `where email = ?` (чутливо до регістру), тож у базі лише нижній регістр.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)),
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isDriver(): bool
    {
        return $this->role === UserRole::Driver;
    }

    /**
     * Вхід в адмін-панель: лише активний адмін.
     * Сигнатура сумісна з Filament\Models\Contracts\FilamentUser (Filament ще не встановлено).
     */
    public function canAccessPanel(?Panel $panel = null): bool
    {
        return $this->is_active && $this->isAdmin();
    }

    /**
     * Вхід в API водія (видача токена): лише активний водій.
     */
    public function canUseDriverApi(): bool
    {
        return $this->is_active && $this->isDriver();
    }

    /**
     * @return HasMany<DeliveryRoute, $this>
     */
    public function driverRoutes(): HasMany
    {
        return $this->hasMany(DeliveryRoute::class, 'driver_id');
    }
}
