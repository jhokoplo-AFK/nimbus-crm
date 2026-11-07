import { Head, useForm } from '@inertiajs/react';
import { BarChart3, ShieldCheck, Target } from 'lucide-react';
import Button from '../../Components/UI/Button';
import { Field, Input } from '../../Components/UI/Input';

const FEATURES = [
    {
        icon: Target,
        title: 'Pipeline intelligence',
        description: 'Drag-and-drop stages with live weighted pipeline totals per column.',
    },
    {
        icon: ShieldCheck,
        title: 'Discount governance',
        description: 'Quotes above 15% discount or $50,000 route to a Sales Manager automatically.',
    },
    {
        icon: BarChart3,
        title: 'ERP-ready revenue',
        description: 'Closed-Won deals generate draft sales orders for SAP Business One.',
    },
];

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (event) => {
        event.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <div className="flex min-h-screen bg-white">
            <Head title="Sign in" />

            <section className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-slate-900 p-10 text-white lg:flex">
                <div className="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-brand-600/30 blur-3xl" />
                <div className="absolute -bottom-32 -left-16 h-72 w-72 rounded-full bg-indigo-500/20 blur-3xl" />

                <div className="relative">
                    <div className="flex items-center gap-2.5">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold">
                            N
                        </span>
                        <div>
                            <p className="text-sm font-semibold">Nimbus CRM</p>
                            <p className="text-[11px] text-slate-400">Sales Cloud for B2B teams</p>
                        </div>
                    </div>

                    <h1 className="mt-16 max-w-md text-3xl font-semibold leading-tight tracking-tight">
                        Every lead, quote and contract in one governed revenue system.
                    </h1>
                    <p className="mt-3 max-w-md text-sm text-slate-400">
                        Built for sales organisations that need pricebook discipline, approval workflows and a clean
                        hand-off to the ERP.
                    </p>
                </div>

                <ul className="relative space-y-5">
                    {FEATURES.map((feature) => {
                        const Icon = feature.icon;

                        return (
                            <li key={feature.title} className="flex gap-3">
                                <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-white/10 text-brand-200">
                                    <Icon className="h-4 w-4" />
                                </span>
                                <div>
                                    <p className="text-sm font-medium">{feature.title}</p>
                                    <p className="text-xs text-slate-400">{feature.description}</p>
                                </div>
                            </li>
                        );
                    })}
                </ul>

                <p className="relative text-[11px] text-slate-500">
                    © {new Date().getFullYear()} Nimbus Systems. Internal demonstration environment.
                </p>
            </section>

            <section className="flex flex-1 items-center justify-center px-5 py-12 sm:px-8">
                <div className="w-full max-w-sm">
                    <div className="mb-8 lg:hidden">
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                            N
                        </span>
                    </div>

                    <h2 className="text-xl font-semibold tracking-tight text-slate-900">Sign in to your workspace</h2>
                    <p className="mt-1 text-sm text-slate-500">Use one of the seeded demo accounts below.</p>

                    <form onSubmit={submit} className="mt-6 space-y-4">
                        <Field label="Work email" htmlFor="email" required error={errors.email}>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="username"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                invalid={Boolean(errors.email)}
                                required
                            />
                        </Field>

                        <Field label="Password" htmlFor="password" required error={errors.password}>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                invalid={Boolean(errors.password)}
                                required
                            />
                        </Field>

                        <label className="flex items-center gap-2 text-xs text-slate-600">
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={(event) => setData('remember', event.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600"
                            />
                            Keep me signed in
                        </label>

                        <Button type="submit" className="w-full" size="lg" loading={processing}>
                            Sign in
                        </Button>
                    </form>

                    <div className="mt-7 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                        <p className="font-semibold text-slate-700">Demo accounts</p>
                        <ul className="mt-1.5 space-y-1">
                            <li><span className="font-medium">Admin</span> — admin@nimbus.test</li>
                            <li><span className="font-medium">Sales Manager</span> — manager@nimbus.test</li>
                            <li><span className="font-medium">Sales Rep</span> — rep@nimbus.test</li>
                            <li className="text-slate-500">Password for all: <code className="rounded bg-white px-1">password</code></li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    );
}
