<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title')</title>
    <style>
        html {
            font-family: Arial, Helvetica, sans-serif;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        tr th {
            background: #3c4043;
            color: #ffffff;
        }
        td,
        th {
            border: 1px solid #ececec;
            text-align: left;
            padding: 8px;
        }
        th {
            font-size: 13px;
        }
        td {
            font-size: 12px;
        }
        tr:nth-child(even) {
            background-color: #ececec;
        }
        hr {
            border: 0;
            border-top: 1px dotted #3c4043;
        }
        .alert {
            text-align: center;
            background: #3c4043;
            color: #ffffff;
            padding: 10px;
        }
    </style>
</head>

<body>

@yield('content')

</body>

</html>
