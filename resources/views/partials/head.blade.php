<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<script>
(function () {
    const appearanceKey = 'flux-appearance';
    const stored = localStorage.getItem(appearanceKey);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark = stored === 'dark'
        || (stored !== 'light' && (stored === 'system' || ! stored) && prefersDark);
    document.documentElement.classList.toggle('dark', isDark);

    const colorblindKey = 'pageant-colorblind';
    const colorblindModes = ['deuteranopia', 'protanopia', 'tritanopia'];
    const colorblind = localStorage.getItem(colorblindKey);
    if (colorblind && colorblindModes.includes(colorblind)) {
        document.documentElement.setAttribute('data-colorblind', colorblind);
    } else if (colorblind) {
        document.documentElement.removeAttribute('data-colorblind');
    }
})();
</script>
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Pageant') : config('app.name', 'Pageant') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
