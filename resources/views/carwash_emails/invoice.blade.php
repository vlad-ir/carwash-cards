Уважаемый {{ $invoice->client->name }},

Ваш счет за период с {{ $invoice->period_start->format('d.m.Y') }} по {{ $invoice->period_end->format('d.m.Y') }} на сумму {{ number_format($invoice->amount, 2) }} руб. приложен к этому письму.

С уважением,
Команда автомойки
