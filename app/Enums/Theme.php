<?php

namespace App\Enums;

use Illuminate\Http\Request;

enum Theme: string
{
    case Matcha = 'matcha';
    case Raspberry = 'raspberry';
    case Blueberry = 'blueberry';
    case Caramel = 'caramel';
    case Lavender = 'lavender';
    case Midnight = 'midnight';
    case Mono = 'mono';
    case Aurora = 'aurora';

    /**
     * The cookie that remembers the visitor's chosen theme.
     */
    public const COOKIE = 'theme';

    public static function default(): self
    {
        return self::Matcha;
    }

    public static function current(Request $request): self
    {
        return self::tryFrom((string) $request->cookie(self::COOKIE)) ?? self::default();
    }

    public function label(): string
    {
        return match ($this) {
            self::Matcha => 'Matcha & Apricot',
            self::Raspberry => 'Raspberry & Butter',
            self::Blueberry => 'Blueberry & Coral',
            self::Caramel => 'Caramel Classic',
            self::Lavender => 'Lavender & Lime',
            self::Midnight => 'Midnight Neon',
            self::Mono => 'Mono Volt',
            self::Aurora => 'Aurora Glow',
        };
    }

    /**
     * A one-line description of the theme's personality.
     */
    public function description(): string
    {
        return match ($this) {
            self::Matcha => 'Fresh greens, modern and rounded',
            self::Raspberry => 'Bold pink, bright and playful',
            self::Blueberry => 'Cool blues with an editorial serif',
            self::Caramel => 'Warm and elegant with sharper edges',
            self::Lavender => 'Soft purples, extra round and bubbly',
            self::Midnight => 'Dark mode with electric violet and cyan',
            self::Mono => 'Minimal black and white with a lime spark',
            self::Aurora => 'Airy teal and pink glow, softly futuristic',
        };
    }

    /**
     * Primary, accent and dark colours shown in the theme picker.
     *
     * @return array{string, string, string}
     */
    public function swatches(): array
    {
        return match ($this) {
            self::Matcha => ['#4f8a3c', '#ffa65c', '#1c2a1e'],
            self::Raspberry => ['#cc2257', '#ffd54f', '#211425'],
            self::Blueberry => ['#3451d1', '#ff8266', '#151b33'],
            self::Caramel => ['#9a5422', '#f3c96e', '#261b14'],
            self::Lavender => ['#7a3bd9', '#cdec5f', '#1e1733'],
            self::Midnight => ['#7352f5', '#3dd9f0', '#0c0b14'],
            self::Mono => ['#111111', '#d4ff3a', '#0a0a0a'],
            self::Aurora => ['#0f9f9a', '#ff6fae', '#0b2530'],
        };
    }

    /**
     * Whether the theme uses light text on dark surfaces.
     */
    public function isDark(): bool
    {
        return $this === self::Midnight;
    }

    /**
     * The browser UI colour for the page background.
     */
    public function metaColor(): string
    {
        return match ($this) {
            self::Matcha => '#f7f5ec',
            self::Raspberry => '#fff8f1',
            self::Blueberry => '#f6f8fc',
            self::Caramel => '#fbf6ee',
            self::Lavender => '#faf7ff',
            self::Midnight => '#0c0b14',
            self::Mono => '#f4f4f1',
            self::Aurora => '#f3fbfa',
        };
    }
}
