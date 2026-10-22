import { useForm } from '@inertiajs/react';

type HttpMethod = 'post' | 'put' | 'patch';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function usePrecognitiveForm<TForm extends Record<string, unknown>>(
    method: HttpMethod,
    url: string,
    initial: TForm,
) {
    const form = useForm(initial);

    function validate(field: string): void {
        fetch(url, {
            method: method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Precognition: 'true',
                'Precognition-Validate-Only': field,
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(form.data),
        })
            .then(async (response) => {
                if (response.status === 422) {
                    const body = (await response.json()) as {
                        errors?: Record<string, string[]>;
                    };
                    const messages = body.errors?.[field];

                    if (messages?.[0]) {
                        form.setError(field, messages[0]);
                    }

                    return;
                }

                form.clearErrors(field);
            })
            .catch(() => {
                // Live validation is best-effort; submit still hits the full request.
            });
    }

    return { ...form, validate };
}
