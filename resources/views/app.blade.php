<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (str_starts_with($page['component'] ?? '', 'demo/'))
        <meta name="description" content="Persiapkan TOEFL 500+ dalam 15 hari dengan metode belajar terstruktur dari Full Bright Indonesia. Sudah membantu 45.000+ alumni meraih beasiswa &amp; CPNS. Mulai dari Rp99rb.">
    @endif
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preload" href="/logo/Logo-Fullbright.webp" as="image" type="image/webp" fetchpriority="high">

    <script>
        window.__META_PAGE_VIEW_EVENT_ID = window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        window.__PBM_META_EVENTS = @js(app(\App\Analytics\MetaEventMapper::class)->forMode(config('analytics.mode')));
    </script>

    @if (filled(config('meta.pixel_id')))
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=true;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=true;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', @js(config('meta.pixel_id')));
        </script>
    @endif

    @if ($gtmId = config('integrations.gtm_container_id'))
        <script>
            window.dataLayer = window.dataLayer || [];
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':Date.now(),event:'gtm.js'});
            var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
            j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@js($gtmId));
        </script>
    @elseif ($ga4Id = config('integrations.ga4_measurement_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($ga4Id) }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments)}
            gtag('js', new Date());
            gtag('config', @js($ga4Id));
        </script>
    @endif

    @if ($clarityId = config('integrations.clarity_project_id'))
        <script>
            (function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script',@js($clarityId));
            const pbmLandingSource = sessionStorage.getItem('pbm_landing_source') || location.pathname;
            clarity('set', 'landing_source', pbmLandingSource);
            clarity('identify', @js(request()->attributes->get('pbm_visitor_id')));
        </script>
    @endif

    <script>
        (() => {
            const appearance = @js($appearance ?? 'system');
            if (appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    {{-- The landing page uses Nunito via its own styles and, like production, loads no web fonts. --}}
    @unless (str_starts_with($page['component'] ?? '', 'demo/'))
        @fonts
    @endunless
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
    <x-inertia::head>
        @if (str_starts_with($page['component'] ?? '', 'demo/'))
            <title>Kelas TOEFL Skor 500+ untuk Submission Beasiswa dan Kerja</title>
        @else
            <title>{{ config('app.name', 'PBM Landing Page Boilerplate') }}</title>
        @endif
    </x-inertia::head>
</head>
<body class="font-sans antialiased">
    @if ($gtmId = config('integrations.gtm_container_id'))
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($gtmId) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    <x-inertia::app />
</body>
</html>
