<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Cheques</title>
    <link rel="stylesheet" href="{{ public_path('css/custom_pdf.css') }}">
    <link rel="stylesheet" href="{{ public_path('css/custom_page.css') }}">
</head>
<body>
    <header>
        <table width="100%">
            <tr>
                <td class="text-center">
                    <span style="font-size: 25px; font-weigth: bold;">Importadora Capriles</span>
                </td>
            </tr>
            <tr>
                <td class="text-center">
                    <span style="font-size: 20px; font-weigth: bold;">Cheques por Cobrar</span>
                </td>
            </tr>
            <tr>
                <td class="text-center">
                    <span style="font-size: 16px"><strong>Total por Cobrar: ${{ number_format($my_total,2) }}</strong></span>
                    <br>
                    <span style="font-size: 16px"><strong>Fecha de Consulta: {{ \Carbon\Carbon::now()->format('d-m-Y') }}</strong></span>
                </td>
            </tr>
        </table>
    </header>
    <section>
        <table cellpadding="0" cellspacing="0" class="table-items" width="100%">
            <thead>
                <tr>
                    <th>CLIENTE</th>
                    <th>BANCO</th>
                    <th>N° CHEQUE</th>
                    <th>DESCRIPCION</th>
                    <th>SALDO</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($checks as $check)
                    <tr>
                        <td align="center">{{ $check->costumer }}</td>
                        <td align="center">{{ $check->bank }}</td>
                        <td align="center">{{ $check->number }}</td>
                        <td align="center">{{ $check->description }}</td>
                        <td align="center">${{number_format($check->amount,2)}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</body>
</html>