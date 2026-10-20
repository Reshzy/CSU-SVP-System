import { useGSAP } from '@gsap/react';
import { Head, Link, usePage } from '@inertiajs/react';
import gsap from 'gsap';
import { useRef } from 'react';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';

gsap.registerPlugin(useGSAP);

export default function Welcome() {
    const { auth } = usePage().props;
    const container = useRef<HTMLDivElement>(null);

    useGSAP(
        () => {
            const reduced = window.matchMedia(
                '(prefers-reduced-motion: reduce)',
            ).matches;

            if (reduced) {
                return;
            }

            gsap.from('[data-landing-hero]', {
                opacity: 0,
                y: 28,
                duration: 0.8,
                ease: 'power2.out',
            });
            gsap.from('[data-landing-card]', {
                opacity: 0,
                y: 16,
                duration: 0.6,
                stagger: 0.12,
                delay: 0.2,
                ease: 'power2.out',
            });
        },
        { scope: container },
    );

    return (
        <>
            <Head title="CagSU SVP Portal" />
            <div
                ref={container}
                className="min-h-screen bg-[#f7f1e8] text-[#2b1212]"
            >
                <header className="flex items-center justify-between px-6 py-5 lg:px-12">
                    <p className="text-sm font-semibold tracking-[0.18em] text-[#800000] uppercase">
                        Cagayan State University
                    </p>
                    <nav className="flex items-center gap-3">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-full bg-[#800000] px-4 py-2 text-sm font-medium text-white"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="rounded-full px-4 py-2 text-sm font-medium text-[#800000]"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-full bg-[#800000] px-4 py-2 text-sm font-medium text-white"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto grid max-w-6xl gap-12 px-6 py-16 lg:grid-cols-[1.1fr_0.9fr] lg:px-12 lg:py-24">
                    <section data-landing-hero className="space-y-6">
                        <p className="text-sm font-medium text-[#b45309]">
                            Sanchez Mira Campus
                        </p>
                        <h1 className="max-w-xl text-4xl font-semibold tracking-tight text-[#800000] sm:text-5xl">
                            CagSU SVP Portal
                        </h1>
                        <p className="max-w-xl text-lg leading-8 text-[#4a3228]">
                            Small Value Procurement for Cagayan State University
                            — from purchase request to BAC award and delivery,
                            aligned with RA 9184.
                        </p>
                        {!auth.user && (
                            <div className="flex flex-wrap gap-3">
                                <Link
                                    href={login()}
                                    className="rounded-full bg-[#800000] px-5 py-2.5 text-sm font-medium text-white"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-full border border-[#800000] px-5 py-2.5 text-sm font-medium text-[#800000]"
                                >
                                    Request access
                                </Link>
                            </div>
                        )}
                    </section>

                    <aside className="grid gap-4">
                        {[
                            [
                                'Campus-owned workflow',
                                'Supply review, budget earmark, CEO approval, then BAC quotations.',
                            ],
                            [
                                'Lots and item groups stay distinct',
                                'Supply lots are not BAC groups. The system keeps both scopes honest.',
                            ],
                            [
                                'Paperwork stays on the server',
                                'RFQ, AOQ, resolutions, and Excel exports generate as official files.',
                            ],
                        ].map(([title, copy]) => (
                            <article
                                key={title}
                                data-landing-card
                                className="rounded-2xl border border-[#e6d5b8] bg-white p-5 shadow-[0_12px_40px_-24px_rgba(128,0,0,0.45)]"
                            >
                                <h2 className="font-medium text-[#800000]">
                                    {title}
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-[#5c4033]">
                                    {copy}
                                </p>
                            </article>
                        ))}
                    </aside>
                </main>
            </div>
        </>
    );
}

Welcome.layout = null;
