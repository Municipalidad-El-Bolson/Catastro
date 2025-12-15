<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name', 'Centenario') }}</title>

  @vite(['resources/css/app.css','resources/js/app.js'])
  @stack('styles')
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
  <main>
    @yield('content')
  </main>

  @stack('scripts')
</body>
</html>
