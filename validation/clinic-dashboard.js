document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => document.getElementById(button.dataset.modalOpen)?.showModal());
});

document.querySelectorAll('.dashboard-modal').forEach((modal) => {
    modal.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => modal.close());
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) modal.close();
    });
});

document.addEventListener('click', (event) => {
    document.querySelectorAll('.content-management-menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});

document.querySelectorAll('.confirm-delete-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        if (form.dataset.confirmed === 'true') return;
        event.preventDefault();
        if (window.Swal) {
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Delete this item?',
                text: 'This action cannot be undone.',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#c62828'
            });
            if (!result.isConfirmed) return;
        } else if (!window.confirm('Delete this item? This action cannot be undone.')) {
            return;
        }
        form.dataset.confirmed = 'true';
        form.requestSubmit();
    });
});
