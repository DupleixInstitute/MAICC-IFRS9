<html>
<head>
    <title>Video Consultation with {{$consultation->patient->name}}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">


    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
    <script src="{{ mix('js/app.js') }}" defer></script>
</head>

<body>
<div class="container mx-auto">
    <div class="grid grid-cols-2">
        <div id="client-video"></div>
        <div id="doctor-video"></div>
    </div>
</div>
<script>
    const websocket=new Websocket();
</script>
</body>
</html>
