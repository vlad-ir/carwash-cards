<div class="modal fade" id="createInvoicesModal" tabindex="-1" aria-labelledby="createInvoicesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createInvoicesModalLabel">Создание счетов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="createInvoicesMessage"></p>
                <div class="mb-3">
                    <label for="invoiceMonth" class="form-label">Месяц</label>
                    <select class="form-select" id="invoiceMonth">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->monthName }}</option>
                        @endfor
                    </select>
                </div>
                <div class="mb-3">
                    <label for="invoiceYear" class="form-label">Год</label>
                    <select class="form-select" id="invoiceYear">
                        @for ($y = now()->year - 5; $y <= now()->year + 1; $y++)
                            <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="sendEmailOnCreate" checked>
                    <label class="form-check-label" for="sendEmailOnCreate">Отправлять счета на email</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="confirmCreateInvoicesButton">Создать</button>
            </div>
        </div>
    </div>
</div>
