import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--border-radius': 'var(--radius-lg)',
                } as React.CSSProperties
            }
            toastOptions={{
                classNames: {
                    toast: 'shadow-lg! shadow-zinc-950/10! font-sans!',
                    title: 'font-semibold! tracking-tight!',
                    description: 'text-muted-foreground!',
                },
            }}
            {...props}
        />
    );
}

export { Toaster };
