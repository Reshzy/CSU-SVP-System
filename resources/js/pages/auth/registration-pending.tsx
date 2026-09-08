import { Head } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

export default function RegistrationPending() {
    return (
        <>
            <Head title="Registration received" />

            <div className="flex flex-col items-center gap-6 text-center">
                <div className="bg-primary/10 text-primary flex size-12 items-center justify-center rounded-full">
                    <MailCheck className="size-6" />
                </div>

                <div className="space-y-2">
                    <p className="text-muted-foreground text-sm">
                        Your account has been created and is waiting for the
                        Executive Officer to review it. You will not be able to
                        sign in until it is approved.
                    </p>
                    <p className="text-muted-foreground text-sm">
                        Approval includes a check of the government-issued ID
                        you uploaded.
                    </p>
                </div>

                <Button asChild className="w-full">
                    <TextLink href={login()}>Back to log in</TextLink>
                </Button>
            </div>
        </>
    );
}

RegistrationPending.layout = {
    title: 'Registration received',
    description: 'Your account is pending approval',
};
