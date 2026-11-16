import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';
import { marked } from 'marked';
import hljs from 'highlight.js';

export default class extends Controller {
    static values = {
        url: String,
        token: String,
    };

    async migrate(event) {
        const file = event.params.file;

        const sourceModalElement = document.getElementById('phpFilesModal');
        const resultModalElement = document.getElementById('codeMigrationModal');

        const sourceModal = Modal.getOrCreateInstance(sourceModalElement);
        const resultModal = Modal.getOrCreateInstance(resultModalElement);

        const loading = document.getElementById('migrationLoading');
        const error = document.getElementById('migrationError');
        const result = document.getElementById('migrationResult');

        document.getElementById('migrationFileName').textContent = file;

        loading.classList.remove('d-none');
        error.classList.add('d-none');
        result.classList.add('d-none');

        sourceModalElement.addEventListener(
            'hidden.bs.modal',
            () => resultModal.show(),
            { once: true }
        );

        sourceModal.hide();

        try {
            const body = new URLSearchParams({
                file,
                _token: this.tokenValue,
            });

            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                },
                body,
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Migration failed.');
            }

            document.getElementById('originalPhpCode').textContent = data.sourceCode;
            const generated = document.getElementById('generatedSymfonyCode');

            generated.innerHTML = marked.parse(data.generatedCode);

            generated.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightElement(block);
            });

            result.classList.remove('d-none');
        } catch (exception) {
            error.textContent = exception.message;
            error.classList.remove('d-none');
        } finally {
            loading.classList.add('d-none');
        }
    }

    async copy(event) {
        const element = document.getElementById(event.params.target);
        if (!element) return;

        const button = event.currentTarget;
        const icon = button.querySelector('svg');
        if (!icon) return;

        try {
            await navigator.clipboard.writeText(element.textContent);

            const originalIcon = icon.innerHTML;

            icon.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
            button.title = 'Copied!';

            setTimeout(() => {
                icon.innerHTML = originalIcon;
                button.title = 'Copy';
            }, 1500);

        } catch (error) {
            console.error('Copy failed:', error);
        }
    }
}