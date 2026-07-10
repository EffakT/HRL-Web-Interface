import anchor from '@alpinejs/anchor';
import focus from '@alpinejs/focus';
import './echo';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(anchor);
    window.Alpine.plugin(focus);
});
