<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>PHP Valencia</title>
    <meta name="description" content="phpvalencia.es es un grupo local de desarrolladores web que usan el lenguaje #PHP." />
    <meta name="author" content="Valentin Rusev, Carlos Reig, Stephen O'Flynn, David Peña" />

    <link rel="apple-touch-icon" sizes="57x57" href="/favicons/apple-touch-icon-57x57.png?v=XBBlYrBb4N">
    <link rel="apple-touch-icon" sizes="60x60" href="/favicons/apple-touch-icon-60x60.png?v=XBBlYrBb4N">
    <link rel="apple-touch-icon" sizes="72x72" href="/favicons/apple-touch-icon-72x72.png?v=XBBlYrBb4N">
    <link rel="apple-touch-icon" sizes="76x76" href="/favicons/apple-touch-icon-76x76.png?v=XBBlYrBb4N">
    <link rel="apple-touch-icon" sizes="114x114" href="/favicons/apple-touch-icon-114x114.png?v=XBBlYrBb4N">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png?v=XBBlYrBb4N">
    <link rel="apple-touch-icon" sizes="144x144" href="/favicons/apple-touch-icon-144x144.png?v=XBBlYrBb4N">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-152x152.png?v=XBBlYrBb4N">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon-180x180.png?v=XBBlYrBb4N">
    <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png?v=XBBlYrBb4N" sizes="32x32">
    <link rel="icon" type="image/png" href="/favicons/android-chrome-192x192.png?v=XBBlYrBb4N" sizes="192x192">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png?v=XBBlYrBb4N" sizes="96x96">
    <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png?v=XBBlYrBb4N" sizes="16x16">
    <link rel="manifest" href="/favicons/manifest.json?v=XBBlYrBb4N">
    <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg?v=XBBlYrBb4N" color="#5bbad5">
    <link rel="shortcut icon" href="/favicons/favicon.ico?v=XBBlYrBb4N">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="msapplication-TileImage" content="/favicons/mstile-144x144.png?v=XBBlYrBb4N">
    <meta name="theme-color" content="#ffffff">

    <meta name="viewport" content="width=device-width" />
    <link href='https://fonts.googleapis.com/css?family=Rambla:400,700' rel='stylesheet' type='text/css' />
    <link rel="stylesheet" href="/css/normalize.css" />
    <link rel="stylesheet" href="/css/style.css" />
    <script src="/js/libs/modernizr.min.js"></script>
</head>

<body>
<!--[if lt IE 7]>
<p class="chromeframe">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> or <a href="http://www.google.com/chromeframe/?redirect=true">activate Google Chrome Frame</a> to improve your experience.</p>
<![endif]-->
<div id="container">
    @include('_partials.header')
    <div id="main" role="main" class="clearfix">
        @yield('contents')
    </div>
    @include('_partials.footer')
</div>
<!--! end of #container -->
</body>

</html>
