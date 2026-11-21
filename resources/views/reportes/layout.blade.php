<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('titulo') - {{ config('reportes.nombre') }}</title>
    <style>
        @page { margin: 105px 42px 55px; }
        body { color: #222; font-family: "{{ config('reportes.fuente') }}", sans-serif; font-size: 11px; line-height: 1.45; }
        header { border-bottom: 1px solid #777; left: 42px; position: fixed; right: 42px; top: -82px; }
        header table { border-collapse: collapse; width: 100%; }
        header td { padding: 0 0 8px; vertical-align: bottom; }
        header .business { font-size: 18px; font-weight: bold; }
        header .report-title { font-size: 14px; font-weight: bold; text-align: right; }
        header .meta { color: #555; font-size: 9px; text-align: right; }
        footer { border-top: 1px solid #aaa; bottom: -35px; color: #666; font-size: 9px; left: 42px; padding-top: 6px; position: fixed; right: 42px; text-align: center; }
        h1, h2, h3 { color: #222; }
        table.report-table { border-collapse: collapse; margin-top: 12px; width: 100%; }
        table.report-table th, table.report-table td { border: 1px solid #aaa; padding: 6px 8px; text-align: left; }
        table.report-table th { background: #e8e8e8; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                <td class="business">{{ config('reportes.nombre') }}</td>
                <td class="report-title">@yield('titulo')</td>
            </tr>
            <tr>
                <td></td>
                <td class="meta">
                    Generado: {{ now(config('reportes.zona_horaria'))->format('d/m/Y H:i') }}<br>
                    Usuario: {{ Auth::user()->name }}
                </td>
            </tr>
        </table>
    </header>

    <footer>&nbsp;</footer>

    <main>@yield('contenido')</main>
</body>
</html>
