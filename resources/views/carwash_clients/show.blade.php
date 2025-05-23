@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Клиент: {{ $client->short_name }}</h1>

        <div class="card">
            <div class="card-header">Детали клиента</div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Краткое имя</dt>
                    <dd class="col-sm-9">{{ $client->short_name }}</dd>

                    <dt class="col-sm-3">Полное имя</dt>
                    <dd class="col-sm-9">{{ $client->full_name }}</dd>

                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9">{{ $client->email }}</dd>

                    <dt class="col-sm-3">УНП</dt>
                    <dd class="col-sm-9">{{ $client->unp ?? '-' }}</dd>

                    <dt class="col-sm-3">Номер банковского счета</dt>
                    <dd class="col-sm-9">{{ $client->bank_account_number ?? '-' }}</dd>

                    <dt class="col-sm-3">БИК банка</dt>
                    <dd class="col-sm-9">{{ $client->bank_bic ?? '-' }}</dd>

                    <dt class="col-sm-3">Статус</dt>
                    <dd class="col-sm-9">{{ $client->status == 'active' ? 'Активен' : 'Заблокирован' }}</dd>

                    <dt class="col-sm-3">Требуется отправка счета на email</dt>
                    <dd class="col-sm-9">{{ $client->invoice_email_required ? 'Да' : 'Нет' }}</dd>

                    <dt class="col-sm-3">День для отправки счета</dt>
                    <dd class="col-sm-9">{{ $client->invoice_email_day ?? '-' }}</dd>

                    <dt class="col-sm-3">Почтовый адрес</dt>
                    <dd class="col-sm-9">{{ $client->postal_address ?? '-' }}</dd>

                    <dt class="col-sm-3">Банковский почтовый адрес</dt>
                    <dd class="col-sm-9">{{ $client->bank_postal_address ?? '-' }}</dd>

                    <dt class="col-sm-3">Договор</dt>
                    <dd class="col-sm-9">{{ $client->contract ?? '-' }}</dd>
                </dl>
            </div>
            <div class="card-footer">
                <a href="{{ route('carwash_clients.edit', $client->id) }}" class="btn btn-warning">Редактировать</a>
                <form action="{{ route('carwash_clients.destroy', $client->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger delete-single" title="Удалить" data-short-name="{{ htmlspecialchars($client->short_name) }}">Удалить</button>
                </form>
                <a href="{{ route('carwash_clients.index') }}" class="btn btn-secondary">Назад</a>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                $('.delete-single').on('click', function(e) {
                    e.preventDefault();
                    const form = $(this).closest('form');
                    const shortName = $(this).data('short-name');
                    showConfirmModal(`Вы уверены, что хотите удалить клиента ${shortName}?`, function() {
                        form.submit();
                    });
                });
            });
        </script>
    @endpush
@endsection
