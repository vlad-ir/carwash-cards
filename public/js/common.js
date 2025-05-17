function showToast(title, message, type) {
    const toast = $('#toastMessage');
    const toastTitle = $('#toastTitle');
    const toastBody = $('#toastBody');

    toastTitle.text(title);
    toastBody.text(message);

    toast.removeClass('text-bg-success text-bg-danger');
    if (type === 'success') {
        toast.addClass('text-bg-success');
    } else {
        toast.addClass('text-bg-danger');
    }

    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    setTimeout(() => bsToast.hide(), 3000);
}

function showConfirmModal(message, callback) {
    $('#confirmDeleteMessage').text(message);
    const modal = new bootstrap.Modal('#confirmDeleteModal');
    modal.show();

    $('#confirmDeleteButton').off('click').on('click', function() {
        modal.hide();
        callback();
    });
}
