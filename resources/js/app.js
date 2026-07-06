import anchor from '@alpinejs/anchor';
import './echo';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(anchor);
});
