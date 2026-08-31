/**
 * Gmail Automation Frontend Scripts
 */

document.addEventListener('DOMContentLoaded', () => {
    // Variable badge click to insert into textarea
    document.querySelectorAll('.variable-badge').forEach(badge => {
        badge.addEventListener('click', (e) => {
            const varName = e.target.getAttribute('data-variable') || e.target.innerText.trim();
            const targetInputId = e.target.getAttribute('data-target') || 'reply_message';
            const textarea = document.getElementById(targetInputId);
            if (textarea) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const text = textarea.value;
                textarea.value = text.substring(0, start) + varName + text.substring(end);
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = start + varName.length;
            }
        });
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert-dismissible').forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
