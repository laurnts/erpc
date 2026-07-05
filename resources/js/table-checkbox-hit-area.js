// Filament renders record-selection checkboxes as a 16px input inside a much
// larger table cell that otherwise swallows clicks. Forward clicks anywhere in
// the selection cell to its checkbox so the whole cell is the hit target.
document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }
    if (event.target.closest('input, button, a, label, [role="button"]')) {
        return;
    }
    const cell = event.target.closest('.fi-ta-selection-cell');
    if (!cell) {
        return;
    }
    cell.querySelector('input[type="checkbox"]:not(:disabled)')?.click();
});
