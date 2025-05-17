$(document).ready(function() {
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ru-RU.json',
            paginate: {
                previous: "Предыдущая",
                next: "Следующая"
            },
            info: "Показано с _START_ по _END_ из _TOTAL_ записей",
            infoEmpty: "Показано с 0 по 0 из 0 записей",
            infoFiltered: "(отфильтровано из _MAX_ записей)",
            lengthMenu: "Показать _MENU_ записей",
            search: "Поиск:",
            zeroRecords: "Записи не найдены",
            processing: "Обработка...",
            loadingRecords: "Загрузка...",
            emptyTable: "В таблице нет записей",
            aria: {
                sortAscending: ": сортировать по возрастанию",
                sortDescending: ": сортировать по убыванию"
            }
        }
    });
});
