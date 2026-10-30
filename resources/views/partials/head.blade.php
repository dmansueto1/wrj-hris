<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

{{-- WRJ88 icon. No favicon.svg link: browsers prefer SVG and would show the old starter-kit logo.
     ?v= changes when the file changes, so browsers do not keep showing a cached icon. --}}
<link rel="icon" href="/favicon.ico?v={{ @filemtime(public_path('favicon.ico')) }}" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ @filemtime(public_path('apple-touch-icon.png')) }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
