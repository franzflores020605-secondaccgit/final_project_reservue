import { createRoot } from 'react-dom/client';
import React from 'react';
import ContactForm from './react/ContactForm';
import './styles/contact_form.css';

document.querySelectorAll('[data-reservue-contact-root]').forEach((el) => {
    const apiUrl = el.getAttribute('data-api-url') || '';
    const csrfToken = el.getAttribute('data-csrf-token') || '';
    if (!apiUrl || !csrfToken) {
        return;
    }
    const root = createRoot(el);
    root.render(
        <React.StrictMode>
            <ContactForm apiUrl={apiUrl} csrfToken={csrfToken} />
        </React.StrictMode>,
    );
});
