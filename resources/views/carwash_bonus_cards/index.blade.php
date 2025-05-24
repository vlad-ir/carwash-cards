@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Бонусные карты</h1>

        <div class="mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBonusCardModal">Добавить карту</button>
            <button id="deleteSelected" class="btn btn-danger" disabled>Удалить выбранные</button>
        </div>

        <table id="bonusCardsTable" class="table table-bordered">
            <thead>
            <tr>
                <th class="no-sort"><input type="checkbox" id="selectAll"></th>
                <th>Название</th>
                <th>Номер карты</th>
                <th>Клиент</th>
                <th>Ставка за минуту, BYN</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>

        <!-- Боковая панель фильтрации -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="filterOffcanvasLabel">Фильтры</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <form id="filterForm">
                    <div class="mb-3">
                        <label for="name_filter" class="form-label">Название</label>
                        <input type="text" name="name" id="name_filter" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="card_number_filter" class="form-label">Номер карты</label>
                        <input type="text" name="card_number" id="card_number_filter" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="client_short_name_filter" class="form-label">Краткое имя клиента</label>
                        <input type="text" name="client_short_name" id="client_short_name_filter" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="status_filter" class="form-label">Статус</label>
                        <select name="status" id="status_filter" class="form-control">
                            <option value="">Все</option>
                            <option value="active">Активна</option>
                            <option value="blocked">Заблокирована</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <button type="button" id="resetFilters" class="btn btn-secondary">Сбросить</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальное окно создания бонусной карты -->
    <div class="modal fade" id="createBonusCardModal" tabindex="-1" aria-labelledby="createBonusCardModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('carwash_bonus_cards.store') }}" method="POST" id="createBonusCardForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="createBonusCardModalLabel">Добавить бонусную карту</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create_name" class="form-label">Название</label>
                            <input type="text" class="form-control @error('name', 'store') is-invalid @enderror" id="create_name" name="name" value="{{ old('name', '', 'store') }}" required>
                            @error('name', 'store')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="create_card_number" class="form-label">Номер карты</label>
                            <input type="text" class="form-control @error('card_number', 'store') is-invalid @enderror" id="create_card_number" name="card_number" value="{{ old('card_number', '', 'store') }}" required>
                            @error('card_number', 'store')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="create_client_id" class="form-label">Клиент</label>
                            <select class="form-control @error('client_id', 'store') is-invalid @enderror" id="create_client_id" name="client_id" required>
                                <option value="">Выберите клиента</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ old('client_id', '', 'store') == $client->id ? 'selected' : '' }}>{{ $client->short_name }}</option>
                                @endforeach
                            </select>
                            @error('client_id', 'store')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="create_rate_per_minute" class="form-label">Ставка за минуту, BYN</label>
                            <input type="number" step="0.01" class="form-control @error('rate_per_minute', 'store') is-invalid @enderror" id="create_rate_per_minute" name="rate_per_minute" value="{{ old('rate_per_minute', '', 'store') }}" required>
                            @error('rate_per_minute', 'store')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="create_status" class="form-label">Статус</label>
                            <select class="form-control @error('status', 'store') is-invalid @enderror" id="create_status" name="status" required>
                                <option value="active" {{ old('status', '', 'store') == 'active' ? 'selected' : '' }}>Активна</option>
                                <option value="blocked" {{ old('status', '', 'store') == 'blocked' ? 'selected' : '' }}>Заблокирована</option>
                            </select>
                            @error('status', 'store')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальные окна редактирования бонусной карты -->
    @if(isset($bonus_cards))
        @foreach($bonus_cards as $card_instance)
            <div class="modal fade" id="editBonusCardModal{{ $card_instance->id }}" tabindex="-1" aria-labelledby="editBonusCardModalLabel{{ $card_instance->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('carwash_bonus_cards.update', $card_instance->id) }}" method="POST" id="editBonusCardForm{{ $card_instance->id }}">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title" id="editBonusCardModalLabel{{ $card_instance->id }}">Редактировать бонусную карту</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="edit_name_{{ $card_instance->id }}" class="form-label">Название</label>
                                    <input type="text" class="form-control @error('name', 'update'.$card_instance->id) is-invalid @enderror" id="edit_name_{{ $card_instance->id }}" name="name" value="{{ old('name', $card_instance->name, 'update'.$card_instance->id) }}" required>
                                    @error('name', 'update'.$card_instance->id)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label for="edit_card_number_{{ $card_instance->id }}" class="form-label">Номер карты</label>
                                    <input type="text" class="form-control @error('card_number', 'update'.$card_instance->id) is-invalid @enderror" id="edit_card_number_{{ $card_instance->id }}" name="card_number" value="{{ old('card_number', $card_instance->card_number, 'update'.$card_instance->id) }}" required>
                                    @error('card_number', 'update'.$card_instance->id)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label for="edit_client_id_{{ $card_instance->id }}" class="form-label">Клиент</label>
                                    <select class="form-control @error('client_id', 'update'.$card_instance->id) is-invalid @enderror" id="edit_client_id_{{ $card_instance->id }}" name="client_id" required>
                                        <option value="">Выберите клиента</option>
                                        @foreach($clients as $client)
                                            <option value="{{ $client->id }}" {{ old('client_id', $card_instance->client_id, 'update'.$card_instance->id) == $client->id ? 'selected' : '' }}>{{ $client->short_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('client_id', 'update'.$card_instance->id)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label for="edit_rate_per_minute_{{ $card_instance->id }}" class="form-label">Ставка за минуту, BYN</label>
                                    <input type="number" step="0.01" class="form-control @error('rate_per_minute', 'update'.$card_instance->id) is-invalid @enderror" id="edit_rate_per_minute_{{ $card_instance->id }}" name="rate_per_minute" value="{{ old('rate_per_minute', $card_instance->rate_per_minute, 'update'.$card_instance->id) }}" required>
                                    @error('rate_per_minute', 'update'.$card_instance->id)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label for="edit_status_{{ $card_instance->id }}" class="form-label">Статус</label>
                                    @php
                                        $resolved_status = strtolower(trim(old('status', $card_instance->status)));
                                    @endphp
                                    <select class="form-control @error('status', 'update'.$card_instance->id) is-invalid @enderror" id="edit_status_{{ $card_instance->id }}" name="status" required>
                                        <option value="active" @if($resolved_status == 'active') selected @endif>Активна</option>
                                        <option value="blocked" @if($resolved_status == 'blocked') selected @endif>Заблокирована</option>
                                    </select>
                                    @error('status', 'update'.$card_instance->id)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    @push('styles')
        <style>
            .no-sort,
            .no-sort:hover,
            .no-sort:active,
            .no-sort:focus {
                background-image: none !important;
                background: none !important;
                cursor: default !important;
                user-select: none !important;
            }
            .dataTables_filter {
                display: flex;
                align-items: center;
                justify-content: end;
            }
            .dataTables_filter .btn-filter {
                margin-left: 5px;
            }
            .action-buttons {
                display: flex;
            }
            .action-buttons .btn {
                margin-right: 5px;
            }
            .action-buttons .btn i {
                margin: 0;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            $(document).ready(function () {
                let selectedIds = [];

                const table = $('#bonusCardsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route('carwash_bonus_cards.data') }}',
                        data: function (d) {
                            d.name = $('#name_filter').val();
                            d.card_number = $('#card_number_filter').val();
                            d.client_short_name = $('#client_short_name_filter').val();
                            d.status = $('#status_filter').val();
                        }
                    },
                    columns: [
                        { data: 'checkbox', orderable: false, searchable: false },
                        { data: 'name' },
                        { data: 'card_number' },
                        { data: 'client_short_name' },
                        { data: 'rate_per_minute' },
                        {
                            data: 'status',
                            render: function (data) {
                                return data === 'active'
                                    ? '<i class="fas fa-check text-success"></i>'
                                    : data === 'blocked'
                                        ? '<i class="fas fa-ban text-danger"></i>'
                                        : '<i class="fas fa-pause text-muted"></i>';
                            }
                        },
                        { data: 'action', name: 'action', orderable: false, searchable: false }
                    ],
                    order: [[1, 'asc']],
                    initComplete: function () {
                        var api = this.api();

                        // Кнопка "Фильтр"
                        var $filterBtn = $('<button id="filter-btn" class="btn btn-secondary btn-sm btn-filter"><i class="fas fa-filter"></i> Фильтр</button>')
                            .on('click', function () {
                                $('#filterOffcanvas').offcanvas('show');
                            });

                        // Кнопка "Сбросить фильтры" (по умолчанию скрыта)
                        var $resetFilterBtn = $('<button id="filter-reset-btn" class="btn btn-danger btn-sm ms-1" title="Сбросить фильтры"><i class="fas fa-times"></i></button>')
                            .on('click', function () {
                                $('#name_filter, #card_number_filter, #client_short_name_filter, #status_filter').val('');
                                table.draw();
                            });

                        // Добавляем кнопки к фильтру DataTables
                        var $filterContainer = $(api.table().container()).find('div.dataTables_filter');
                        $filterContainer.append($filterBtn).append($resetFilterBtn);

                        // Проверяем, есть ли заполненные фильтры
                        function hasActiveFilters() {
                            return $('#name_filter, #card_number_filter, #client_short_name_filter, #status_filter')
                                .filter(function () { return $(this).val(); }).length > 0;
                        }

                        // Обновляем видимость кнопки "Сбросить фильтры"
                        function toggleResetButtonVisibility() {
                            if (hasActiveFilters()) {
                                $resetFilterBtn.show();
                                $filterBtn.removeClass('btn-secondary').addClass('btn-primary');
                            } else {
                                $resetFilterBtn.hide();
                                $filterBtn.removeClass('btn-primary').addClass('btn-secondary');
                            }
                        }

                        // Вызываем проверку при загрузке
                        toggleResetButtonVisibility();

                        // При применении фильтров через форму — обновляем статус кнопки
                        $('#filterForm').on('submit', function () {
                            setTimeout(toggleResetButtonVisibility, 100);
                        });

                        // При изменении любого поля фильтрации — обновляем статус кнопки
                        $('#name_filter, #card_number_filter, #client_short_name_filter, #status_filter').on('change input', function () {
                            toggleResetButtonVisibility();
                        });

                        // Также проверяем после перерисовки таблицы
                        table.on('draw', function () {
                            toggleResetButtonVisibility();
                        });
                    },
                    drawCallback: function () {
                        $('.select-row').each(function () {
                            $(this).prop('checked', selectedIds.includes($(this).val()));
                        });
                        updateSelectedCount();

                        $('.delete-single').off('click').on('click', function (e) {
                            e.preventDefault();
                            const form = $(this).closest('form');
                            const cardName = $(this).data('card-name');
                            const cardNumber = $(this).data('card-number');
                            showConfirmModal(`Вы уверены, что хотите удалить бонусную карту ${cardName} (${cardNumber})?`, function () {
                                form.submit();
                            });
                        });
                    }
                });

                // Clear errors and handle backdrop on modal hidden event
                $('#createBonusCardModal').on('hidden.bs.modal', function (e) {
                    const form = $(this).find('form');
                    if (form.length) {
                        form[0].reset();
                        form.find('.is-invalid').removeClass('is-invalid');
                        form.find('.invalid-feedback').remove();
                    }
                });

                // For dynamically generated edit modals, attach event listener to a static parent if needed,
                // or rely on Bootstrap's default behavior for form reset if forms are not re-used.
                // If using Laravel's old() and error directives, the state will be handled on page reload.
                // The main thing is to clear validation classes if the modal is simply closed without submitting.
                $(document).on('hidden.bs.modal', '[id^="editBonusCardModal"]', function () {
                    const form = $(this).find('form');
                    if (form.length) {
                        // Don't reset the form fields to allow Laravel's old() to work on validation error
                        // form[0].reset();
                        form.find('.is-invalid').removeClass('is-invalid');
                        form.find('.invalid-feedback').remove();
                    }
                });


                // Logic to potentially open the correct edit modal if validation fails
                // This assumes that Laravel redirects back with an error, and we might want to reopen the modal.
                // This can be complex. For now, we'll rely on standard form error display.
                // Example: if (session('error_card_id')) { $('#editBonusCardModal' + session('error_card_id')).modal('show'); }
                // This would require passing 'error_card_id' from the controller on validation failure.

                @if($errors->any())
                @php
                    $errorBagKey = null;
                    $updateErrorCardId = null;

                    // Check for errors related to the 'store' form (createBonusCardModal)
                    if ($errors->store->any()) {
                        $errorBagKey = 'store';
                    } else {
                        // Check for errors related to any 'update' form (editBonusCardModal)
                        foreach($bonus_cards as $card_instance) {
                            if ($errors->{'update'.$card_instance->id}->any()) {
                                $errorBagKey = 'update'.$card_instance->id;
                                $updateErrorCardId = $card_instance->id;
                                break;
                            }
                        }
                    }
                @endphp

                @if($errorBagKey === 'store')
                var createModal = new bootstrap.Modal(document.getElementById('createBonusCardModal'));
                createModal.show();
                @elseif($updateErrorCardId)
                var editModal = new bootstrap.Modal(document.getElementById('editBonusCardModal{{ $updateErrorCardId }}'));
                editModal.show();
                @endif
                @endif


                function updateSelectedCount() {
                    const count = selectedIds.length;
                    const info = table.page.info();
                    const statusText = `Выбрано записей: ${count}`;
                    $('.dataTables_info').text(`${statusText} | Всего записей: ${info.recordsTotal}`);
                }

                // Применение фильтров
                $('#filterForm').on('submit', function(e) {
                    e.preventDefault();
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                // Сброс фильтров
                $('#resetFilters').on('click', function() {
                    $('#name_filter, #card_number_filter, #client_short_name_filter, #status_filter').val('');
                    table.draw();
                    $('#filterOffcanvas').offcanvas('hide');
                });

                $('#selectAll').on('change', function () {
                    const checked = this.checked;
                    $('.select-row').each(function () {
                        const id = $(this).val();
                        if (checked && !selectedIds.includes(id)) {
                            selectedIds.push(id);
                        } else if (!checked && selectedIds.includes(id)) {
                            selectedIds = selectedIds.filter(item => item !== id);
                        }
                        $(this).prop('checked', checked);
                    });
                    $('#deleteSelected').prop('disabled', selectedIds.length === 0);
                    updateSelectedCount();
                });

                $(document).on('change', '.select-row', function () {
                    const id = $(this).val();
                    if ($(this).is(':checked')) {
                        if (!selectedIds.includes(id)) selectedIds.push(id);
                    } else {
                        selectedIds = selectedIds.filter(item => item !== id);
                    }
                    $('#selectAll').prop('checked', $('.select-row:checked').length === $('.select-row').length);
                    $('#deleteSelected').prop('disabled', selectedIds.length === 0);
                    updateSelectedCount();
                });

                $('#deleteSelected').on('click', function () {
                    if (selectedIds.length === 0) return;

                    showConfirmModal(`Вы уверены, что хотите удалить ${selectedIds.length} бонусных карт(ы)?`, function () {
                        $.ajax({
                            url: "{{ route('carwash_bonus_cards.deleteSelected') }}",
                            method: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                ids: selectedIds
                            },
                            success: function (response) {
                                showToast('Успех', response.success, 'success');
                                selectedIds = [];
                                $('#deleteSelected').prop('disabled', true);
                                table.draw();
                            },
                            error: function () {
                                showToast('Ошибка', 'Ошибка при удалении бонусных карт.', 'error');
                            }
                        });
                    });
                });
            });
        </script>
    @endpush
@endsection
