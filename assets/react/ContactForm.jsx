import React, { useState, useCallback } from 'react';

const emailLooksValid = (value) =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());

/**
 * @typedef {'idle' | 'submitting' | 'success'} SubmitState
 */

export default function ContactForm({ apiUrl, csrfToken }) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState('');
    const [status, setStatus] = useState(/** @type {SubmitState} */ ('idle'));
    const [errorBanner, setErrorBanner] = useState('');
    const [fieldErrors, setFieldErrors] = useState(/** @type {Record<string, string>} */ ({}));

    const resetFieldError = useCallback((key) => {
        setFieldErrors((prev) => {
            const next = { ...prev };
            delete next[key];
            return next;
        });
    }, []);

    const handleSubmit = useCallback(
        async (e) => {
            e.preventDefault();
            setErrorBanner('');
            setFieldErrors({});

            const nextFieldErrors = {};
            if (!name.trim()) {
                nextFieldErrors.name = 'Please enter your name.';
            }
            if (!email.trim()) {
                nextFieldErrors.email = 'Please enter your email.';
            } else if (!emailLooksValid(email)) {
                nextFieldErrors.email = 'Please enter a valid email address.';
            }
            if (!message.trim()) {
                nextFieldErrors.message = 'Please enter a message.';
            }

            if (Object.keys(nextFieldErrors).length > 0) {
                setFieldErrors(nextFieldErrors);
                return;
            }

            setStatus('submitting');

            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        name: name.trim(),
                        email: email.trim(),
                        message: message.trim(),
                    }),
                });

                const data = await res.json().catch(() => ({}));

                if (res.ok && data.ok) {
                    setStatus('success');
                    setName('');
                    setEmail('');
                    setMessage('');
                    return;
                }

                setStatus('idle');
                if (data.fieldErrors && typeof data.fieldErrors === 'object') {
                    setFieldErrors(data.fieldErrors);
                }
                setErrorBanner(
                    typeof data.error === 'string' && data.error
                        ? data.error
                        : 'Something went wrong. Please try again.',
                );
            } catch {
                setStatus('idle');
                setErrorBanner('Network error. Check your connection and try again.');
            }
        },
        [apiUrl, csrfToken, name, email, message],
    );

    if (status === 'success') {
        return (
            <div className="reservue-contact-form reservue-contact-form--success" role="status">
                <div className="reservue-contact-alert reservue-contact-alert--success">
                    <strong>Message sent</strong>
                    <p>Thank you! Your message has been sent. We will get back to you soon.</p>
                    <button
                        type="button"
                        className="reservue-contact-btn reservue-contact-btn--secondary"
                        onClick={() => {
                            setStatus('idle');
                            setErrorBanner('');
                        }}
                    >
                        Send another message
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="reservue-contact-form">
            {errorBanner ? (
                <div className="reservue-contact-alert reservue-contact-alert--error" role="alert">
                    {errorBanner}
                </div>
            ) : null}

            <form onSubmit={handleSubmit} noValidate>
                <div className="reservue-contact-field">
                    <label htmlFor="reservue-contact-name">Name</label>
                    <input
                        id="reservue-contact-name"
                        name="name"
                        type="text"
                        autoComplete="name"
                        value={name}
                        onChange={(ev) => {
                            setName(ev.target.value);
                            resetFieldError('name');
                        }}
                        disabled={status === 'submitting'}
                        className={fieldErrors.name ? 'reservue-contact-input reservue-contact-input--invalid' : 'reservue-contact-input'}
                    />
                    {fieldErrors.name ? <p className="reservue-contact-field-error">{fieldErrors.name}</p> : null}
                </div>

                <div className="reservue-contact-field">
                    <label htmlFor="reservue-contact-email">Email</label>
                    <input
                        id="reservue-contact-email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        inputMode="email"
                        value={email}
                        onChange={(ev) => {
                            setEmail(ev.target.value);
                            resetFieldError('email');
                        }}
                        disabled={status === 'submitting'}
                        className={fieldErrors.email ? 'reservue-contact-input reservue-contact-input--invalid' : 'reservue-contact-input'}
                    />
                    {fieldErrors.email ? <p className="reservue-contact-field-error">{fieldErrors.email}</p> : null}
                </div>

                <div className="reservue-contact-field">
                    <label htmlFor="reservue-contact-message">Message</label>
                    <textarea
                        id="reservue-contact-message"
                        name="message"
                        rows={5}
                        value={message}
                        onChange={(ev) => {
                            setMessage(ev.target.value);
                            resetFieldError('message');
                        }}
                        disabled={status === 'submitting'}
                        className={fieldErrors.message ? 'reservue-contact-input reservue-contact-input--invalid' : 'reservue-contact-input'}
                    />
                    {fieldErrors.message ? <p className="reservue-contact-field-error">{fieldErrors.message}</p> : null}
                </div>

                <button
                    type="submit"
                    className="reservue-contact-btn reservue-contact-btn--primary"
                    disabled={status === 'submitting'}
                >
                    {status === 'submitting' ? 'Sending…' : 'Send message'}
                </button>
            </form>
        </div>
    );
}
