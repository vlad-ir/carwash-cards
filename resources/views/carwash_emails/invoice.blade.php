<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Счет на оплату</title>
</head>
<body>
<p>Здравствуйте.</p>

<p>Во вложении находится счет на оплату услуг автомойки за период с {{ $invoice->period_start->format('d.m.Y') }} по {{ $invoice->period_end->format('d.m.Y') }}.</p>

<p>Номер счета: {{ $invoice->id }}</p>
<p>Сумма к оплате: {{ number_format($invoice->amount, 2, ',', ' ') }} руб.</p>

<p>Спасибо за использование наших услуг!</p>
<br>

</body>
</html>
