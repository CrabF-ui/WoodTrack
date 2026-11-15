<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&amp;family=IBM+Plex+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F1F2F3;
            color: #111315;
            font-family: 'IBM Plex Sans', sans-serif;
        }

        .login-page {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .login-brand {
            margin: 0;
            color: #2C221E;
            font-family: 'Cormorant Garamond', serif;
            font-size: 48px;
            font-weight: 600;
            line-height: 1;
            text-align: center;
        }

        .login-subtitle {
            margin: 10px 0 24px;
            color: #4A4F55;
            font-size: 14px;
            text-align: center;
        }

        .login-card {
            width: min(440px, 100% - 32px);
            overflow: hidden;
            border-radius: 12px;
            background: #FFFFFF;
            box-shadow: 0 1px 2px rgba(17, 19, 21, .06), 0 0 0 1px rgba(17, 19, 21, .07), 0 12px 32px rgba(17, 19, 21, .06);
        }

        .login-card::before {
            content: '';
            display: block;
            height: 6px;
            background: linear-gradient(to right, #2C221E 0 77.777%, #C5A059 77.777% 100%);
        }

        .login-card__body {
            padding: 28px 32px;
        }

        .login-form {
            display: grid;
            gap: 18px;
        }

        .login-title {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            line-height: 1.25;
        }

        .status-message,
        .error-message {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14.5px;
            line-height: 1.45;
        }

        .status-message {
            border: 1px solid #8FC5A4;
            background: #EDF8F1;
            color: #185C32;
        }

        .error-message {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            border: 1px solid #E2A9B0;
            background: #FDF0F1;
            color: #7A1522;
        }

        .error-message svg {
            width: 20px;
            height: 20px;
            flex: 0 0 20px;
            color: #A32635;
        }

        .form-field {
            display: grid;
            gap: 7px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
            height: 46px;
            border: 1px solid #8A9099;
            border-radius: 8px;
            background: #FFFFFF;
            padding: 0 14px;
            color: #111315;
            font: inherit;
            font-size: 15.5px;
        }

        .form-input--invalid {
            border: 2px solid #A32635;
        }

        .form-input:focus,
        .login-button:focus-visible {
            outline: 2px solid #C5A059;
            outline-offset: 2px;
        }

        .remember-option {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #2B2F34;
            font-size: 14.5px;
        }

        .remember-option input {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: #2C221E;
        }

        .login-button {
            width: 100%;
            height: 48px;
            border: 0;
            border-radius: 8px;
            background: #2C221E;
            color: #FFFFFF;
            cursor: pointer;
            font: inherit;
            font-size: 16px;
            font-weight: 600;
        }

        .login-button:hover {
            background: #181514;
        }

        .login-help {
            margin: 0;
            color: #4A4F55;
            font-size: 13.5px;
            text-align: center;
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
