import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    ChevronDown,
    FileSignature,
    FileText,
    LayoutDashboard,
    LogOut,
    Menu,
    Package,
    Plus,
    Search,
    ShoppingCart,
    Target,
    TrendingUp,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn, initials } from '../lib/format';
import Button from '../Components/UI/Button';

const NAV_SECTIONS = [
    {
        title: 'Workspace',
        items: [{ label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard }],
    },
    {
        title: 'Pipeline',
        items: [
            { label: 'Leads', href: '/leads', icon: Target },
            { label: 'Accounts', href: '/accounts', icon: Building2 },
            { label: 'Contacts', href: '/contacts', icon: Users },
            { label: 'Opportunities', href: '/opportunities', icon: TrendingUp },
        ],
    },
    {
        title: 'Revenue',
        items: [
            { label: 'Quotations', href: '/quotations', icon: FileText },
            { label: 'Contracts', href: '/contracts', icon: FileSignature },
            { label: 'Sales Orders', href: '/sales-orders', icon: ShoppingCart },
        ],
    },
    {
        title: 'Catalog',
        items: [{ label: 'Products', href: '/products', icon: Package }],
    },
];

function isActive(url, href) {
    return url === href || url.startsWith(`${href}/`) || url.startsWith(`${href}?`);
}

function FlashToast() {
    const { flash } = usePage().props;
    const [message, setMessage] = useState(null);

    useEffect(() => {
        const next = flash?.success
            ? { tone: 'success', text: flash.success }
            : flash?.error
              ? { tone: 'error', text: flash.error }
              : null;

        setMessage(next);

        if (!next) return undefined;

        const timeout = setTimeout(() => setMessage(null), 4500);

        return () => clearTimeout(timeout);
    }, [flash?.success, flash?.error]);

    if (!message) return null;

    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4">
            <div
                className={cn(
                    'pointer-events-auto flex items-start gap-2 rounded-lg px-4 py-3 text-sm shadow-lg ring-1',
                    message.tone === 'success'
                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                        : 'bg-rose-50 text-rose-800 ring-rose-200',
                )}
                role="status"
            >
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                <span>{message.text}</span>
            </div>
        </div>
    );
}

function Sidebar({ onNavigate }) {
    const { url } = usePage();
    const { appName } = usePage().props;

    return (
        <div className="flex h-full flex-col">
            <div className="flex h-16 items-center gap-2.5 border-b border-slate-200 px-5">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                    N
                </span>
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-slate-900">{appName || 'Nimbus CRM'}</p>
                    <p className="text-[11px] text-slate-500">Sales Cloud</p>
                </div>
            </div>

            <nav className="scrollbar-thin flex-1 space-y-5 overflow-y-auto px-3 py-4">
                {NAV_SECTIONS.map((section) => (
                    <div key={section.title}>
                        <p className="px-2 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-slate-400">
                            {section.title}
                        </p>
                        <ul className="space-y-0.5">
                            {section.items.map((item) => {
                                const Icon = item.icon;
                                const active = isActive(url, item.href);

                                return (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            prefetch
                                            onClick={onNavigate}
                                            className={cn(
                                                'flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm font-medium transition',
                                                active
                                                    ? 'bg-brand-50 text-brand-700'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                                            )}
                                        >
                                            <Icon className={cn('h-4 w-4', active ? 'text-brand-600' : 'text-slate-400')} />
                                            {item.label}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>

            <div className="border-t border-slate-200 p-3">
                <Link
                    href="/quotations/create"
                    onClick={onNavigate}
                    className="flex items-center justify-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                >
                    <Plus className="h-4 w-4" />
                    New quotation
                </Link>
            </div>
        </div>
    );
}

function UserMenu() {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const user = auth?.user;

    if (!user) return null;

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="flex items-center gap-2 rounded-md px-1.5 py-1 text-left transition hover:bg-slate-100"
            >
                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">
                    {initials(user.name)}
                </span>
                <span className="hidden min-w-0 sm:block">
                    <span className="block truncate text-xs font-semibold text-slate-900">{user.name}</span>
                    <span className="block truncate text-[11px] text-slate-500">{user.role}</span>
                </span>
                <ChevronDown className="hidden h-3.5 w-3.5 text-slate-400 sm:block" />
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-30" onClick={() => setOpen(false)} aria-hidden="true" />
                    <div className="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                        <div className="border-b border-slate-100 px-3 py-2.5">
                            <p className="truncate text-sm font-medium text-slate-900">{user.name}</p>
                            <p className="truncate text-xs text-slate-500">{user.email}</p>
                            <p className="mt-1 inline-flex rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                {user.role}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post('/logout')}
                            className="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                        >
                            <LogOut className="h-4 w-4" />
                            Sign out
                        </button>
                    </div>
                </>
            )}
        </div>
    );
}

export default function AppLayout({ title, children }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const { url } = usePage();

    useEffect(() => {
        setMobileOpen(false);
    }, [url]);

    return (
        <div className="min-h-screen bg-slate-100">
            <Head title={title} />

            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-slate-200 bg-white lg:flex lg:flex-col">
                <Sidebar />
            </aside>

            {mobileOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-slate-900/40" onClick={() => setMobileOpen(false)} aria-hidden="true" />
                    <aside className="relative flex h-full w-72 flex-col bg-white shadow-xl">
                        <button
                            type="button"
                            onClick={() => setMobileOpen(false)}
                            className="absolute right-3 top-4 rounded-md p-1 text-slate-400 hover:bg-slate-100"
                            aria-label="Close navigation"
                        >
                            <X className="h-4 w-4" />
                        </button>
                        <Sidebar onNavigate={() => setMobileOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
                    <button
                        type="button"
                        onClick={() => setMobileOpen(true)}
                        className="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                        aria-label="Open navigation"
                    >
                        <Menu className="h-5 w-5" />
                    </button>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            const value = new FormData(event.currentTarget).get('search');
                            router.get('/leads', value ? { search: value } : {}, { preserveState: false });
                        }}
                        className="relative hidden max-w-md flex-1 sm:block"
                    >
                        <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            name="search"
                            type="search"
                            placeholder="Search leads, companies, emails…"
                            className="h-9 w-full rounded-md border-0 bg-slate-100 pl-8 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-inset focus:ring-brand-600"
                        />
                    </form>

                    <div className="ml-auto flex items-center gap-2">
                        <Link href="/quotations/create" className="hidden sm:block">
                            <Button size="sm" variant="secondary">
                                <Plus className="h-3.5 w-3.5" />
                                Quote
                            </Button>
                        </Link>
                        <UserMenu />
                    </div>
                </header>

                <main className="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">{children}</main>
            </div>

            <FlashToast />
        </div>
    );
}
