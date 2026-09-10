/* Progressive enhancement: the original POST forms still work without JavaScript. */
(() => {
    const panel = document.getElementById('room-assignment-panel');
    const status = document.getElementById('room-move-status');
    if (!panel || !status) {
        return;
    }
    let saving = false;
    panel.addEventListener('submit', async(event) => {
        const form = event.target.closest('form[data-room-move]');
        if (!form) {
            return;
        }
        event.preventDefault();
        if (saving) {
            return;
        }
        const data = new FormData(form);
        const groupid = form.dataset.roomMove;
        saving = true;
        panel.setAttribute('aria-busy', 'true');
        panel.querySelectorAll('select, button').forEach(control => control.disabled = true);
        status.className = 'alert alert-info';
        status.textContent = panel.dataset.saving;
        try {
            const response = await fetch(panel.dataset.moveUrl, {
                method: 'POST', body: data, credentials: 'same-origin', headers: {'Accept': 'application/json'}
            });
            const result = await response.json();
            if (!response.ok || !result.success || typeof result.html !== 'string') {
                throw new Error('Move not confirmed');
            }
            // Server-rendered markup uses the same escaping and capability checks as the initial page.
            panel.innerHTML = result.html;
            status.className = 'alert alert-success';
            status.textContent = result.message;
            const moved = document.getElementById('move-room-' + groupid);
            if (moved) {
                moved.focus({preventScroll: true});
            }
        } catch (error) {
            // Do not claim rollback: a lost response may arrive after the server saved the move.
            status.className = 'alert alert-danger';
            status.textContent = panel.dataset.failure;
        } finally {
            saving = false;
            panel.removeAttribute('aria-busy');
            panel.querySelectorAll('select, button').forEach(control => control.disabled = false);
        }
    });
})();
