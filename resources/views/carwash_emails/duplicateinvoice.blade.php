<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Копия счета на оплату услуг автомойки</title>
</head>
<body>

<p>Во вложении находится копия счета на оплату услуг автомойки за период с {{ $invoice->period_start->format('d.m.Y') }} по {{ $invoice->period_end->format('d.m.Y') }}.<br>
для клиента {{ $client->full_name }}, УНП: {{ $client->unp }}
</p>

<p>Номер счета: {{ $invoice->id }}</p>
<p>Сумма к оплате: {{ number_format($invoice->amount, 2, ',', ' ') }} руб.</p>
<br>

</body>
</html>
