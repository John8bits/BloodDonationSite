document.querySelectorAll('[data-clinic-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => document.getElementById(button.dataset.clinicDialogOpen)?.showModal());
});

document.querySelectorAll('[data-clinic-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});
