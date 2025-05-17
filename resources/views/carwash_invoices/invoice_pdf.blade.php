<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Счет #{{ $invoice->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1 { text-align: center; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
<h1>Счет #{{ $invoice->id }}</h1>
<p><strong>Клиент:</strong> {{ $client->name }}</p>
<p><strong>Email:</strong> {{ $client->email }}</p>
<p><strong>Период:</strong> {{ $invoice->period_start->format('d.m.Y') }} - {{ $invoice->period_end->format('d.m.Y') }}</p>

<table>
    <thead>
    <tr>
        <th>Номер карты</th>
        <th>Минуты</th>
        <th>Тариф (руб./мин)</th>
        <th>Сумма (руб.)</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($details as $detail)
        <tr>
            <td>{{ $detail['card_number'] }}</td>
            <td>{{ $detail['minutes'] }}</td>
            <td>{{ number_format($detail['tariff'], 2) }}</td>
            <td>{{ number_format($detail['amount'], 2) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td colspan="3">Итого:</td>
        <td>{{ number_format($invoice->amount, 2) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
