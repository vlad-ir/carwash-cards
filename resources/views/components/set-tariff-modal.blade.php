<div class="modal fade" id="setTariffModal" tabindex="-1" aria-labelledby="setTariffModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="setTariffModalLabel">Установить тариф</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="setTariffMessage"></p>
                <div class="mb-3">
                    <label for="newTariffRate" class="form-label">Новый тариф (цена за минуту, BYN)</label>
                    <input type="number" class="form-control" id="newTariffRate" name="rate_per_minute" step="0.01" min="0" placeholder="0.00" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="confirmSetTariffButton">Установить</button>
            </div>
        </div>
    </div>
</div>
