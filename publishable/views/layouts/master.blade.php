<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>RequestInsurance</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/night-owl.min.css">

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script charset="UTF-8" src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/languages/json.min.js"></script>

    <style>
        .table-vertical tr td:first-child {
            width: 1%;
            white-space: nowrap;
            padding-right: 0.4em;
            font-weight: bold;
            vertical-align: top;
        }

        pre {
            white-space: pre-wrap;       /* Since CSS 2.1 */
            white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
            white-space: -pre-wrap;      /* Opera 4-6 */
            white-space: -o-pre-wrap;    /* Opera 7 */
            word-wrap: break-word;       /* Internet Explorer 5.5+ */
            word-break: break-word;
            margin-bottom: 0px;
            border-radius: 10px;
        }

        .check-lg {
            transform: scale(1.5);
            -webkit-transform: scale(1.5);
            margin-right: 0.45em !important;
            margin-left: 0.2em !important;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid col-12">
        @yield('content')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            hljs.highlightAll();
        });
    </script>
</body>
</html>
