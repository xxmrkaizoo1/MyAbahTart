<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A sign-in from the admin mobile app. Only a hash of the token is stored.
 */
#[Fillable(['user_id', 'name', 'token', 'last_used_at'])]
#[Hidden(['token'])]
class ApiToken extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a token for the user and return the plain text value, which is shown only once.
     */
    public static function issue(User $user, string $name): string
    {
        $plainToken = Str::random(60);

        static::create(['user_id' => $user->id, 'name' => $name, 'token' => hash('sha256', $plainToken)]);

        return $plainToken;
    }

    public static function findByPlainToken(?string $plainToken): ?self
    {
        if ($plainToken === null || $plainToken === '') {
            return null;
        }

        return static::query()->with('user')->where('token', hash('sha256', $plainToken))->first();
    }
}
