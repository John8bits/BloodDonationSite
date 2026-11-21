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
