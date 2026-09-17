import { Form, Head, Link } from '@inertiajs/react';
import { store as quickLogin } from '@/actions/App/Http/Controllers/Auth/DevLoginController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasskeyVerify from '@/components/passkey-verify';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type QuickLoginUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    department: string | null;
};

type Props = {
    status?: string;
    canResetPassword: boolean;
    canQuickLogin?: boolean;
    quickLoginUsers?: QuickLoginUser[];
};

function groupQuickLoginUsers(
    users: QuickLoginUser[],
): [string, QuickLoginUser[]][] {
    const groups = new Map<string, QuickLoginUser[]>();

    for (const user of users) {
        const group = groups.get(user.role) ?? [];
        group.push(user);
        groups.set(user.role, group);
    }

    return [...groups.entries()];
}

export default function Login({
    status,
    canResetPassword,
    canQuickLogin = false,
    quickLoginUsers = [],
}: Props) {
    return (
        <>
            <Head title="Log in" />

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Forgot your password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>

                        <div className="text-muted-foreground text-center text-sm">
                            Don't have an account?{' '}
                            <TextLink href={register()} tabIndex={5}>
                                Sign up
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {canQuickLogin && quickLoginUsers.length > 0 && (
                <QuickLoginPanel users={quickLoginUsers} />
            )}

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

function QuickLoginPanel({ users }: { users: QuickLoginUser[] }) {
    const groups = groupQuickLoginUsers(users);

    return (
        <div className="mt-6 flex flex-col gap-3">
            <div className="relative">
                <div className="absolute inset-0 flex items-center">
                    <Separator className="w-full" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                    <span className="bg-card text-muted-foreground px-2">
                        Dev quick login
                    </span>
                </div>
            </div>

            <p className="text-muted-foreground text-center text-xs">
                Seeded password is{' '}
                <span className="font-medium">password123</span>
            </p>

            <div className="max-h-64 space-y-3 overflow-y-auto">
                {groups.map(([role, accounts]) => (
                    <div key={role} className="space-y-1">
                        <p className="text-muted-foreground text-xs font-medium">
                            {role}
                        </p>
                        {accounts.map((user) => (
                            <Button
                                key={user.id}
                                variant="outline"
                                size="sm"
                                className="h-auto w-full justify-start py-2"
                                asChild
                            >
                                <Link
                                    href={quickLogin.url(user)}
                                    method="post"
                                >
                                    <span className="flex min-w-0 flex-col items-start text-left">
                                        <span className="truncate font-medium">
                                            {user.name}
                                        </span>
                                        <span className="text-muted-foreground truncate text-xs">
                                            {user.email}
                                        </span>
                                    </span>
                                </Link>
                            </Button>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
