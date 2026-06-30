<script>
    (() => {
        const syncAutofilledInputs = () => {
            document.querySelectorAll('.fi-simple-page input').forEach((input) => {
                if (input.value !== '') {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        };

        syncAutofilledInputs();
        window.setTimeout(syncAutofilledInputs, 100);
        window.setTimeout(syncAutofilledInputs, 500);
        document.addEventListener('livewire:navigated', syncAutofilledInputs);
    })();
</script>
