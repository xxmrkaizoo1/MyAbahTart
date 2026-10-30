@props(['art' => 'pineapple'])

@php
    $palettes = [
        'pineapple' => ['#fff4cf', '#ffe08a', '#f4b63c', '#c97a0e'],
        'egg' => ['#fff6e0', '#ffe2a8', '#f8d057', '#e3a325'],
        'cheese' => ['#fff7e3', '#fbe2b0', '#f9e08a', '#e8b848'],
        'berry' => ['#ffeef3', '#ffc9d8', '#fff5e2', '#efd9b4'],
        'mango' => ['#fff0d9', '#ffcf94', '#fbb73c', '#e5891a'],
        'matcha' => ['#eef8e8', '#cfe8c0', '#a3c46f', '#6e9140'],
        'nutty' => ['#fbefe2', '#f0d2b0', '#b5733a', '#7a431a'],
        'chocolate' => ['#f7e9ef', '#e6c7d4', '#6b3c27', '#3e2016'],
        'savory' => ['#f8f1de', '#e9dbb6', '#efbf68', '#c98a3e'],
        'durian' => ['#fbf6d6', '#f0e39c', '#f6e38c', '#e2c552'],
        'lavender' => ['#f4eefa', '#dccdee', '#c9b3df', '#9a7dbb'],
        'lemon' => ['#fffbe0', '#fff09a', '#fbe27a', '#e8c13a'],
        'caramel' => ['#fff1e4', '#f9d3b0', '#d58a3a', '#8f4f17'],
        'pistachio' => ['#fdeef2', '#f6cdd8', '#bcd68c', '#7fa24e'],
    ];

    [$bgLight, $bgDark, $fill, $fillDark] = $palettes[$art] ?? $palettes['pineapple'];
    $uid = 'tart'.\Illuminate\Support\Str::random(8);

    $crimps = collect(range(0, 27))->map(fn (int $i) => [
        round(100 + 69 * cos(2 * M_PI * $i / 28), 2),
        round(106 + 29 * sin(2 * M_PI * $i / 28), 2),
    ]);
@endphp

<svg {{ $attributes->merge(['class' => 'size-full']) }} viewBox="0 0 200 200" preserveAspectRatio="xMidYMid slice" role="img" aria-hidden="true">
    <defs>
        <radialGradient id="{{ $uid }}-bg" cx="35%" cy="25%" r="90%">
            <stop offset="0" stop-color="{{ $bgLight }}" />
            <stop offset="1" stop-color="{{ $bgDark }}" />
        </radialGradient>
        <radialGradient id="{{ $uid }}-fill" cx="42%" cy="38%" r="70%">
            <stop offset="0" stop-color="#ffffff" stop-opacity=".35" />
            <stop offset=".35" stop-color="{{ $fill }}" />
            <stop offset="1" stop-color="{{ $fillDark }}" />
        </radialGradient>
        <linearGradient id="{{ $uid }}-crust" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#f4c97f" />
            <stop offset="1" stop-color="#cf8f43" />
        </linearGradient>
        <clipPath id="{{ $uid }}-clip">
            <ellipse cx="100" cy="104" rx="54" ry="21" />
        </clipPath>
    </defs>

    <rect width="200" height="200" fill="url(#{{ $uid }}-bg)" />
    <circle cx="30" cy="34" r="22" fill="#ffffff" opacity=".18" />
    <circle cx="176" cy="40" r="10" fill="#ffffff" opacity=".22" />
    <circle cx="168" cy="176" r="26" fill="#ffffff" opacity=".12" />

    <ellipse cx="100" cy="146" rx="80" ry="16" fill="#3e2016" opacity=".12" />
    <ellipse cx="100" cy="124" rx="74" ry="30" fill="#a8652a" />
    <rect x="26" y="106" width="148" height="18" fill="#b9763a" />
    <ellipse cx="100" cy="106" rx="74" ry="31" fill="url(#{{ $uid }}-crust)" />
    @foreach ($crimps as [$x, $y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="7.5" fill="#f0bd6b" stroke="#d99a4c" stroke-width=".8" />
    @endforeach
    <ellipse cx="100" cy="105" rx="58" ry="23.5" fill="#c7843b" />
    <ellipse cx="100" cy="104" rx="54" ry="21" fill="url(#{{ $uid }}-fill)" />

    <g clip-path="url(#{{ $uid }}-clip)">
        @switch($art)
            @case('pineapple')
                <g stroke="#e9ad5d" stroke-width="6" stroke-linecap="round" opacity=".95">
                    <path d="M58 84 98 128M78 82l40 44M98 82l40 44M118 84l30 34" />
                    <path d="M142 84 102 128M122 82l-40 44M102 82l-40 44M82 84l-30 34" />
                </g>
                @break
            @case('egg')
                <g fill="#8a4a12">
                    <ellipse cx="82" cy="98" rx="9" ry="4" opacity=".55" />
                    <ellipse cx="112" cy="94" rx="6" ry="3" opacity=".5" />
                    <ellipse cx="120" cy="110" rx="10" ry="4.5" opacity=".6" />
                    <ellipse cx="94" cy="114" rx="7" ry="3" opacity=".45" />
                    <ellipse cx="70" cy="110" rx="5" ry="2.5" opacity=".4" />
                    <ellipse cx="136" cy="100" rx="5" ry="2.5" opacity=".4" />
                </g>
                @break
            @case('cheese')
                <g fill="#c9781c">
                    <ellipse cx="88" cy="100" rx="12" ry="5" opacity=".3" />
                    <ellipse cx="118" cy="108" rx="14" ry="6" opacity=".35" />
                    <ellipse cx="74" cy="112" rx="6" ry="3" opacity=".3" />
                </g>
                <g fill="#ffffff" opacity=".55">
                    <circle cx="104" cy="96" r="2" /><circle cx="128" cy="98" r="1.5" /><circle cx="84" cy="112" r="1.5" />
                </g>
                @break
            @case('berry')
                <g>
                    <circle cx="80" cy="102" r="7" fill="#c8283c" /><circle cx="78" cy="100" r="2" fill="#fff" opacity=".4" />
                    <circle cx="100" cy="96" r="7.5" fill="#d33447" /><circle cx="98" cy="94" r="2" fill="#fff" opacity=".4" />
                    <circle cx="120" cy="103" r="7" fill="#c8283c" /><circle cx="118" cy="101" r="2" fill="#fff" opacity=".4" />
                    <circle cx="92" cy="112" r="5" fill="#3b4c8c" /><circle cx="108" cy="113" r="5" fill="#34457f" />
                    <circle cx="136" cy="110" r="4.5" fill="#3b4c8c" /><circle cx="66" cy="110" r="4.5" fill="#34457f" />
                    <circle cx="112" cy="90" r="4" fill="#a3203a" /><circle cx="88" cy="90" r="4" fill="#a3203a" />
                    <path d="M98 86c4-6 10-6 12-4-3 4-8 5-12 4z" fill="#4f8a4a" />
                </g>
                @break
            @case('mango')
                <g fill="#f7a51c" stroke="#e08a12" stroke-width="1">
                    <ellipse cx="82" cy="100" rx="14" ry="6" transform="rotate(-18 82 100)" />
                    <ellipse cx="104" cy="96" rx="14" ry="6" transform="rotate(8 104 96)" />
                    <ellipse cx="124" cy="106" rx="14" ry="6" transform="rotate(-10 124 106)" />
                    <ellipse cx="96" cy="114" rx="14" ry="6" transform="rotate(12 96 114)" />
                </g>
                <path d="M118 90c4-5 9-5 11-3-3 4-7 4-11 3z" fill="#4f8a4a" />
                @break
            @case('matcha')
                <path d="M100 92c10 0 14 6 8 10s-16 2-14 8 14 5 18 1" fill="none" stroke="#f4f7ec" stroke-width="5" stroke-linecap="round" opacity=".8" />
                <g fill="#f2d04a">
                    <circle cx="76" cy="104" r="4" /><circle cx="126" cy="100" r="3.5" /><circle cx="116" cy="114" r="3" /><circle cx="84" cy="94" r="2.5" />
                </g>
                @break
            @case('nutty')
                @foreach ([[80, 98, -20], [102, 94, 10], [124, 102, -8], [92, 112, 25], [114, 114, -15], [68, 108, 15], [136, 110, 20]] as [$cx, $cy, $rotation])
                    <g transform="rotate({{ $rotation }} {{ $cx }} {{ $cy }})">
                        <ellipse cx="{{ $cx }}" cy="{{ $cy }}" rx="9" ry="5" fill="#6b3412" />
                        <path d="M{{ $cx - 7 }} {{ $cy }}h14" stroke="#a5642e" stroke-width="1.2" />
                    </g>
                @endforeach
                @break
            @case('chocolate')
                <path d="M54 96 70 110 84 92 98 114 112 92 126 112 140 94 150 104" fill="none" stroke="#f3e3cf" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity=".9" />
                <circle cx="100" cy="102" r="8" fill="#8c5130" /><circle cx="97" cy="99" r="2.5" fill="#fff" opacity=".35" />
                @break
            @case('savory')
                <g fill="none" stroke="#a5642e" stroke-width="2.5" stroke-linecap="round">
                    <path d="M84 98q6 4 0 10M100 94q6 5 0 12M116 98q6 4 0 10" />
                </g>
                <g fill="#4f8a4a">
                    <circle cx="74" cy="108" r="2" /><circle cx="128" cy="104" r="2" /><circle cx="108" cy="116" r="1.6" /><circle cx="92" cy="114" r="1.6" />
                </g>
                @break
            @case('durian')
                <path d="M100 104c0-4 6-4 6 0 0 7-12 7-12 0 0-10 18-10 18 0 0 13-24 13-24 0 0-16 30-16 30 0" fill="none" stroke="#d9b63f" stroke-width="3" stroke-linecap="round" />
                <g fill="#c9a531" opacity=".7">
                    <circle cx="70" cy="100" r="2" /><circle cx="134" cy="108" r="2" /><circle cx="124" cy="94" r="1.6" />
                </g>
                @break
            @case('lavender')
                <circle cx="100" cy="102" r="10" fill="#fbf7ff" /><circle cx="100" cy="100" r="5" fill="#ffffff" />
                <g fill="#7b5aa6">
                    <circle cx="78" cy="98" r="2.5" /><circle cx="82" cy="94" r="2" /><circle cx="74" cy="102" r="2" />
                    <circle cx="124" cy="108" r="2.5" /><circle cx="128" cy="104" r="2" /><circle cx="120" cy="112" r="2" />
                    <circle cx="96" cy="116" r="1.8" /><circle cx="114" cy="92" r="1.8" />
                </g>
                @break
            @case('lemon')
                @foreach ([[78, 100], [100, 94], [122, 101], [90, 113], [112, 114], [66, 110], [134, 111]] as [$cx, $cy])
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="9" fill="#fffaf0" stroke="#efd9b8" stroke-width="1" />
                    <path d="M{{ $cx - 3 }} {{ $cy - 6 }}q3-7 6 0" fill="#d9964a" opacity=".85" />
                @endforeach
                @break
            @case('caramel')
                <path d="M60 100q20-8 40 0t40 0" fill="none" stroke="#f2b46b" stroke-width="3" stroke-linecap="round" opacity=".7" />
                <g fill="#ffffff">
                    <rect x="82" y="96" width="4" height="3" rx=".5" transform="rotate(20 84 97)" />
                    <rect x="104" y="104" width="5" height="3" rx=".5" transform="rotate(-15 106 105)" />
                    <rect x="120" y="96" width="3" height="3" rx=".5" />
                    <rect x="92" y="112" width="4" height="2.5" rx=".5" transform="rotate(35 94 113)" />
                    <rect x="70" y="108" width="3" height="2.5" rx=".5" />
                </g>
                @break
            @case('pistachio')
                <circle cx="100" cy="103" r="12" fill="#fde4ec" /><circle cx="100" cy="101" r="6" fill="#fff6f9" />
                <g fill="#5f8a33">
                    <circle cx="76" cy="100" r="2.5" /><circle cx="82" cy="110" r="2" /><circle cx="124" cy="98" r="2.5" /><circle cx="128" cy="110" r="2" /><circle cx="110" cy="116" r="1.8" />
                </g>
                <g fill="#e9849f">
                    <ellipse cx="88" cy="94" rx="4" ry="2" transform="rotate(-30 88 94)" />
                    <ellipse cx="116" cy="110" rx="4" ry="2" transform="rotate(25 116 110)" />
                    <ellipse cx="70" cy="106" rx="3" ry="1.6" />
                </g>
                @break
        @endswitch
    </g>

    <ellipse cx="80" cy="96" rx="18" ry="5" fill="#ffffff" opacity=".22" transform="rotate(-8 80 96)" />
</svg>
