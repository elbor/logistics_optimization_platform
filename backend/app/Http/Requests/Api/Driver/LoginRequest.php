<?php

namespace App\Http\Requests\Api\Driver;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoginRequest extends FormRequest
{
    /** Невдалих спроб на пару email + IP, після чого вхід блокується на DECAY_SECONDS. */
    public const MAX_ATTEMPTS = 5;

    /** Невдалих спроб з однієї IP на будь-які email (перебір акаунтів). */
    public const MAX_ATTEMPTS_PER_IP = 30;

    public const DECAY_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Email у базі в нижньому регістрі (мутатор User::email()).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * Перевіряє пароль і право на API водія. Будь-яка відмова — однакова відповідь
     * (невідомий email, хибний пароль, адмін, неактивний), щоб не розкривати,
     * чи існує акаунт і чи правильний пароль.
     *
     * @throws HttpException 401
     * @throws ThrottleRequestsException
     */
    public function authenticate(): User
    {
        $emailKey = $this->throttleKey();
        $limits = [
            $emailKey => self::MAX_ATTEMPTS,
            self::ipThrottleKey($this->ip()) => self::MAX_ATTEMPTS_PER_IP,
        ];

        foreach ($limits as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($key);

                throw new ThrottleRequestsException(
                    __('auth.throttle', ['seconds' => $seconds]),
                    headers: ['Retry-After' => $seconds],
                );
            }
        }

        // validate() не створює сесію і вирівнює час відповіді (Timebox)
        $guard = Auth::guard('web');
        $valid = $guard->validate($this->only('email', 'password'));
        /** @var User|null $user */
        $user = $guard->getLastAttempted();

        if (! $valid || ! $user->canUseDriverApi()) {
            foreach (array_keys($limits) as $key) {
                RateLimiter::hit($key, self::DECAY_SECONDS);
            }

            throw new HttpException(401, __('auth.failed'));
        }

        // Лічильник IP не скидається: інакше вхід у власний акаунт обнуляв би перебір чужих
        RateLimiter::clear($emailKey);

        return $user;
    }

    private function throttleKey(): string
    {
        return 'driver-login|'.$this->input('email').'|'.$this->ip();
    }

    public static function ipThrottleKey(?string $ip): string
    {
        return 'driver-login-ip|'.$ip;
    }
}
