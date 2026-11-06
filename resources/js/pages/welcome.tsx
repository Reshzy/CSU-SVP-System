import { useGSAP } from '@gsap/react';
import { Head, Link, usePage } from '@inertiajs/react';
import gsap from 'gsap';
import { ArrowRight, ArrowUpRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { dashboard, login, register } from '@/routes';

gsap.registerPlugin(useGSAP);

const heroImage =
    'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1400&q=80';

const pillImage =
    'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=600&q=80';

const noiseTexture =
    "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E\")";

const procurementStages = [
    'Purchase request',
    'Supply review',
    'Budget earmark',
    'CEO approval',
    'BAC award',
];

const marqueeTerms = [
    'Purchase Request',
    'Supply Review',
    'Budget Earmark',
    'CEO Approval',
    'Request for Quotation',
    'Abstract of Quotations',
    'BAC Resolution',
    'Notice of Award',
    'Purchase Order',
    'Delivery & Inspection',
];

const highlights = [
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
] as const;

function stageClipPath(stageIndex: number): string {
    const rowShare = 100 / procurementStages.length;
    const top = stageIndex * rowShare;
    const bottom = 100 - (stageIndex + 1) * rowShare;

    return `inset(${top}% 0% ${bottom}% 0% round 0.5rem)`;
}

function SplitCharacters({ text }: { text: string }) {
    return text.split('').map((character, index) => (
        <span
            key={`${character}-${index}`}
            data-hero-char
            className="inline-block will-change-transform"
        >
            {character === ' ' ? '\u00A0' : character}
        </span>
    ));
}

function ManilaClock() {
    const [time, setTime] = useState<string | null>(null);

    useEffect(() => {
        const formatter = new Intl.DateTimeFormat('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: 'Asia/Manila',
        });
        const tick = () => setTime(formatter.format(new Date()));

        tick();
        const interval = window.setInterval(tick, 1000);

        return () => window.clearInterval(interval);
    }, []);

    return <span className="tabular-nums">{time ?? '--:--:--'} PHT</span>;
}

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

            const intro = gsap.timeline({ defaults: { ease: 'expo.out' } });

            intro
                .from(
                    '[data-grid-line]',
                    {
                        scaleY: 0,
                        transformOrigin: 'top',
                        duration: 1.6,
                        stagger: 0.08,
                        ease: 'expo.inOut',
                    },
                    0,
                )
                .fromTo(
                    '[data-hero-media]',
                    { clipPath: 'inset(100% 0% 0% 0% round 1.75rem)' },
                    {
                        clipPath: 'inset(0% 0% 0% 0% round 1.75rem)',
                        duration: 1.6,
                        ease: 'expo.inOut',
                    },
                    0.15,
                )
                .from('[data-hero-image]', { scale: 1.5, duration: 2.4 }, 0.35)
                .from(
                    '[data-hero-nav] > *',
                    { y: -24, autoAlpha: 0, duration: 1.1, stagger: 0.08 },
                    0.25,
                )
                .from(
                    '[data-hero-char]',
                    {
                        yPercent: 120,
                        rotate: 8,
                        transformOrigin: '0% 100%',
                        duration: 1.3,
                        stagger: 0.035,
                    },
                    0.35,
                )
                .from(
                    '[data-hero-pill]',
                    { width: 0, duration: 1.4, ease: 'expo.inOut' },
                    0.7,
                )
                .from(
                    '[data-hero-rule]',
                    {
                        scaleX: 0,
                        transformOrigin: 'left',
                        duration: 1.4,
                        ease: 'expo.inOut',
                    },
                    0.6,
                )
                .from(
                    '[data-hero-fade]',
                    { y: 28, autoAlpha: 0, duration: 1.1, stagger: 0.1 },
                    0.9,
                )
                .from(
                    '[data-hero-float]',
                    { y: 48, autoAlpha: 0, duration: 1.2 },
                    1.2,
                )
                .from(
                    '[data-hero-badge]',
                    { scale: 0, rotate: -120, duration: 1.4 },
                    1.3,
                )
                .from(
                    '[data-hero-feature]',
                    { y: 24, autoAlpha: 0, duration: 1, stagger: 0.1 },
                    1.25,
                )
                .from('[data-marquee]', { yPercent: 100, duration: 1.2 }, 1.1);

            gsap.to('[data-hero-image]', {
                scale: 1.08,
                duration: 14,
                ease: 'sine.inOut',
                yoyo: true,
                repeat: -1,
                delay: 2.8,
            });

            gsap.to('[data-hero-badge-ring]', {
                rotation: 360,
                transformOrigin: '50% 50%',
                duration: 18,
                ease: 'none',
                repeat: -1,
            });

            gsap.to('[data-marquee-track]', {
                xPercent: -50,
                duration: 45,
                ease: 'none',
                repeat: -1,
            });

            const stages = gsap.timeline({ repeat: -1, delay: 2.4 });

            procurementStages.forEach((_, stageIndex) => {
                if (stageIndex === 0) {
                    return;
                }

                stages
                    .fromTo(
                        '[data-stage-highlight]',
                        { clipPath: stageClipPath(stageIndex - 1) },
                        {
                            clipPath: stageClipPath(stageIndex),
                            duration: 0.9,
                            ease: 'expo.inOut',
                        },
                        '+=1.4',
                    )
                    .to(
                        '[data-stage-counter]',
                        {
                            yPercent:
                                (-100 / procurementStages.length) * stageIndex,
                            duration: 0.9,
                            ease: 'expo.inOut',
                        },
                        '<',
                    )
                    .to(
                        '[data-stage-progress]',
                        {
                            scaleX: (stageIndex + 1) / procurementStages.length,
                            duration: 0.9,
                            ease: 'expo.inOut',
                        },
                        '<',
                    );
            });

            stages
                .fromTo(
                    '[data-stage-highlight]',
                    {
                        clipPath: stageClipPath(procurementStages.length - 1),
                    },
                    {
                        clipPath: stageClipPath(0),
                        duration: 1.1,
                        ease: 'expo.inOut',
                    },
                    '+=1.8',
                )
                .to(
                    '[data-stage-counter]',
                    { yPercent: 0, duration: 1.1, ease: 'expo.inOut' },
                    '<',
                )
                .to(
                    '[data-stage-progress]',
                    {
                        scaleX: 1 / procurementStages.length,
                        duration: 1.1,
                        ease: 'expo.inOut',
                    },
                    '<',
                );

            const moveMediaX = gsap.quickTo('[data-hero-parallax]', 'x', {
                duration: 1.4,
                ease: 'power3',
            });
            const moveMediaY = gsap.quickTo('[data-hero-parallax]', 'y', {
                duration: 1.4,
                ease: 'power3',
            });
            const moveFloatX = gsap.quickTo('[data-float-parallax]', 'x', {
                duration: 1.1,
                ease: 'power3',
            });
            const moveFloatY = gsap.quickTo('[data-float-parallax]', 'y', {
                duration: 1.1,
                ease: 'power3',
            });

            const handlePointerMove = (event: PointerEvent) => {
                const offsetX = event.clientX / window.innerWidth - 0.5;
                const offsetY = event.clientY / window.innerHeight - 0.5;

                moveMediaX(offsetX * -28);
                moveMediaY(offsetY * -28);
                moveFloatX(offsetX * 16);
                moveFloatY(offsetY * 16);
            };

            window.addEventListener('pointermove', handlePointerMove);

            return () => {
                window.removeEventListener('pointermove', handlePointerMove);
            };
        },
        { scope: container },
    );

    return (
        <>
            <Head title="CagSU SVP Portal" />
            <div
                ref={container}
                className="relative flex h-dvh flex-col overflow-hidden bg-zinc-100 text-zinc-950 selection:bg-zinc-950 selection:text-white"
            >
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 flex justify-between px-6 lg:px-10"
                >
                    {Array.from({ length: 5 }, (_, index) => (
                        <span
                            key={index}
                            data-grid-line
                            className="h-full w-px bg-zinc-900/[0.06]"
                        />
                    ))}
                </div>
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 z-50 opacity-[0.05] mix-blend-multiply"
                    style={{ backgroundImage: noiseTexture }}
                />

                <header
                    data-hero-nav
                    className="relative z-10 flex items-center justify-between gap-6 px-6 py-5 lg:px-10"
                >
                    <div className="flex items-center gap-3">
                        <span className="grid size-9 place-items-center rounded-full bg-zinc-950 text-[11px] font-semibold tracking-tight text-white">
                            CS
                        </span>
                        <span className="text-sm leading-tight font-semibold tracking-tight">
                            CagSU
                            <span className="block font-normal text-zinc-500">
                                SVP Portal
                            </span>
                        </span>
                    </div>

                    <div className="hidden items-center gap-8 font-mono text-[11px] tracking-[0.18em] text-zinc-500 uppercase md:flex">
                        <span>Cagayan State University</span>
                        <span className="h-3 w-px bg-zinc-300" />
                        <span>Sanchez Mira Campus</span>
                        <span className="h-3 w-px bg-zinc-300" />
                        <span className="flex items-center gap-2 text-zinc-950">
                            <span className="relative flex size-1.5">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-zinc-950 opacity-60" />
                                <span className="relative inline-flex size-1.5 rounded-full bg-zinc-950" />
                            </span>
                            <ManilaClock />
                        </span>
                    </div>

                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-full bg-zinc-950 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-zinc-800"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="relative px-4 py-2.5 text-sm font-medium after:absolute after:inset-x-4 after:bottom-2 after:h-px after:origin-right after:scale-x-0 after:bg-current after:transition-transform after:duration-500 hover:after:origin-left hover:after:scale-x-100"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-full bg-zinc-950 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-zinc-800"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="relative z-10 grid min-h-0 flex-1 grid-rows-[auto_minmax(0,1fr)] gap-6 px-6 pb-5 lg:grid-cols-12 lg:grid-rows-1 lg:gap-10 lg:px-10">
                    <section className="flex min-h-0 flex-col justify-between gap-6 lg:col-span-7 lg:py-4">
                        <div>
                            <p
                                data-hero-fade
                                className="flex items-center gap-3 font-mono text-[11px] tracking-[0.2em] text-zinc-500 uppercase"
                            >
                                <span className="text-zinc-950">(SVP)</span>
                                <span className="hidden h-px w-10 bg-zinc-300 sm:block" />
                                <span>Small value procurement · RA 9184</span>
                            </p>

                            <h1
                                aria-label="CagSU SVP Portal"
                                className="mt-5 text-[clamp(3.25rem,15vw,5.5rem)] leading-[0.86] font-semibold tracking-[-0.055em] lg:mt-8 lg:text-[clamp(4rem,min(10vw,20vh),12rem)]"
                            >
                                <span
                                    aria-hidden
                                    className="-mb-[0.14em] block overflow-hidden pb-[0.14em]"
                                >
                                    <SplitCharacters text="CagSU SVP" />
                                </span>
                                <span
                                    aria-hidden
                                    className="-mb-[0.14em] flex items-center gap-[0.14em] overflow-hidden pb-[0.14em]"
                                >
                                    <span className="text-zinc-400">
                                        <SplitCharacters text="Portal" />
                                    </span>
                                    <span
                                        data-hero-pill
                                        className="group inline-block h-[0.7em] w-[1.7em] shrink-0 overflow-hidden rounded-full bg-zinc-300"
                                    >
                                        <img
                                            src={pillImage}
                                            alt=""
                                            className="size-full object-cover grayscale transition duration-700 group-hover:scale-110 group-hover:grayscale-0"
                                        />
                                    </span>
                                </span>
                            </h1>
                        </div>

                        <div className="flex flex-col gap-6">
                            <span
                                data-hero-rule
                                className="hidden h-px w-full bg-zinc-300 lg:block"
                            />
                            <div className="grid items-end gap-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-8">
                                <p
                                    data-hero-fade
                                    className="max-w-md text-sm leading-6 text-zinc-600 lg:text-base lg:leading-7"
                                >
                                    Small Value Procurement for Cagayan State
                                    University — from purchase request to BAC
                                    award and delivery, aligned with RA 9184.
                                </p>

                                <div
                                    data-hero-fade
                                    className="flex flex-wrap items-center gap-3"
                                >
                                    {auth.user ? (
                                        <Link
                                            href={dashboard()}
                                            className="group inline-flex items-center gap-4 rounded-full bg-zinc-950 py-2 pr-2 pl-6 text-sm font-medium text-white transition-colors hover:bg-zinc-800"
                                        >
                                            Open dashboard
                                            <span className="grid size-9 place-items-center rounded-full bg-white text-zinc-950 transition-transform duration-500 group-hover:rotate-45">
                                                <ArrowUpRight className="size-4" />
                                            </span>
                                        </Link>
                                    ) : (
                                        <>
                                            <Link
                                                href={login()}
                                                className="group inline-flex items-center gap-4 rounded-full bg-zinc-950 py-2 pr-2 pl-6 text-sm font-medium text-white transition-colors hover:bg-zinc-800"
                                            >
                                                Log in
                                                <span className="grid size-9 place-items-center rounded-full bg-white text-zinc-950 transition-transform duration-500 group-hover:rotate-45">
                                                    <ArrowUpRight className="size-4" />
                                                </span>
                                            </Link>
                                            <Link
                                                href={register()}
                                                className="inline-flex h-13 items-center rounded-full border border-zinc-300 px-6 text-sm font-medium transition-colors hover:border-zinc-950 hover:bg-zinc-950 hover:text-white"
                                            >
                                                Request access
                                            </Link>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="relative min-h-0 lg:col-span-5">
                        <div
                            data-hero-media
                            className="group relative h-full min-h-44 overflow-hidden rounded-[1.75rem] bg-zinc-900"
                        >
                            <div
                                data-hero-parallax
                                className="absolute -inset-8"
                            >
                                <img
                                    data-hero-image
                                    src={heroImage}
                                    alt="Glass facade of a modern office tower"
                                    className="size-full object-cover grayscale transition-[filter] duration-1000 group-hover:grayscale-0"
                                />
                            </div>
                            <div className="absolute inset-0 bg-linear-to-t from-zinc-950/85 via-zinc-950/10 to-zinc-950/40" />

                            <div className="absolute inset-x-5 top-5 flex items-start justify-between font-mono text-[10px] tracking-[0.2em] text-white/70 uppercase">
                                <span className="rounded-full border border-white/20 px-3 py-1.5 backdrop-blur-md">
                                    Live workflow
                                </span>
                                <div
                                    data-hero-badge
                                    className="relative grid size-20 place-items-center lg:size-24"
                                >
                                    <svg
                                        data-hero-badge-ring
                                        viewBox="0 0 100 100"
                                        className="absolute inset-0 size-full text-white"
                                        aria-hidden
                                    >
                                        <defs>
                                            <path
                                                id="landing-badge-path"
                                                d="M50,50 m-38,0 a38,38 0 1,1 76,0 a38,38 0 1,1 -76,0"
                                            />
                                        </defs>
                                        <text className="fill-current text-[8.5px] font-medium uppercase">
                                            <textPath
                                                href="#landing-badge-path"
                                                textLength="236"
                                                lengthAdjust="spacing"
                                            >
                                                Request · Review · Approve ·
                                                Award ·
                                            </textPath>
                                        </text>
                                    </svg>
                                    <span className="grid size-8 place-items-center rounded-full bg-white text-zinc-950 lg:size-9">
                                        <ArrowUpRight className="size-4" />
                                    </span>
                                </div>
                            </div>

                            <div
                                data-float-parallax
                                className="absolute inset-x-4 bottom-4 sm:inset-x-auto sm:bottom-5 sm:left-5 sm:w-72"
                            >
                                <div
                                    data-hero-float
                                    className="rounded-2xl border border-white/10 bg-zinc-950/55 p-4 text-white shadow-2xl shadow-black/40 backdrop-blur-xl"
                                >
                                    <div className="flex items-center justify-between font-mono text-[10px] tracking-[0.18em] text-white/50 uppercase">
                                        <span>Procurement flow</span>
                                        <span className="flex items-center text-white">
                                            0
                                            <span className="relative block h-[1em] overflow-hidden leading-none">
                                                <span
                                                    data-stage-counter
                                                    className="flex flex-col"
                                                >
                                                    {procurementStages.map(
                                                        (stage, stageIndex) => (
                                                            <span
                                                                key={stage}
                                                                className="block h-[1em] leading-none"
                                                            >
                                                                {stageIndex + 1}
                                                            </span>
                                                        ),
                                                    )}
                                                </span>
                                            </span>
                                            <span className="text-white/40">
                                                &nbsp;/ 0
                                                {procurementStages.length}
                                            </span>
                                        </span>
                                    </div>

                                    <div className="relative mt-3 hidden text-sm sm:block [@media(max-height:620px)]:hidden">
                                        <ul className="text-white/45">
                                            {procurementStages.map((stage) => (
                                                <li
                                                    key={stage}
                                                    className="flex h-8 items-center px-3"
                                                >
                                                    {stage}
                                                </li>
                                            ))}
                                        </ul>
                                        <ul
                                            data-stage-highlight
                                            aria-hidden
                                            className="absolute inset-0 bg-white text-zinc-950"
                                            style={{
                                                clipPath: stageClipPath(0),
                                            }}
                                        >
                                            {procurementStages.map((stage) => (
                                                <li
                                                    key={stage}
                                                    className="flex h-8 items-center justify-between px-3 font-medium"
                                                >
                                                    {stage}
                                                    <ArrowRight className="size-3.5" />
                                                </li>
                                            ))}
                                        </ul>
                                    </div>

                                    <div className="mt-3 h-px bg-white/15">
                                        <div
                                            data-stage-progress
                                            className="h-full origin-left bg-white"
                                            style={{
                                                transform: `scaleX(${1 / procurementStages.length})`,
                                            }}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="relative z-10 hidden px-6 md:block lg:px-10 [@media(max-height:680px)]:hidden">
                    <span
                        data-hero-rule
                        className="block h-px w-full bg-zinc-300"
                    />
                    <div className="grid grid-cols-3">
                        {highlights.map(([title, copy], index) => (
                            <article
                                key={title}
                                data-hero-feature
                                className="flex gap-4 py-4 pr-6 not-first:border-l not-first:border-zinc-300 not-first:pl-6"
                            >
                                <span className="font-mono text-[11px] text-zinc-400">
                                    0{index + 1}
                                </span>
                                <div>
                                    <h2 className="text-sm font-medium">
                                        {title}
                                    </h2>
                                    <p className="mt-1 line-clamp-2 text-[13px] leading-5 text-zinc-500">
                                        {copy}
                                    </p>
                                </div>
                            </article>
                        ))}
                    </div>
                </footer>

                <div
                    data-marquee
                    aria-hidden
                    className="relative z-10 overflow-hidden bg-zinc-950 py-2.5 text-white"
                >
                    <div data-marquee-track className="flex w-max">
                        {[0, 1].map((copyIndex) =>
                            marqueeTerms.map((term) => (
                                <span
                                    key={`${copyIndex}-${term}`}
                                    className="flex items-center gap-8 pr-8 font-mono text-[11px] tracking-[0.2em] text-white/70 uppercase"
                                >
                                    {term}
                                    <span className="text-white/30">✦</span>
                                </span>
                            )),
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Welcome.layout = null;
