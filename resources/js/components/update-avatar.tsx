import { router } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import AvatarController from '@/actions/App/Http/Controllers/Settings/AvatarController';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export default function UpdateAvatar({ user }: { user: User }) {
    const getInitials = useInitials();
    const fileInput = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const clearPreview = () => {
        setPreviewUrl(null);

        if (fileInput.current) {
            fileInput.current.value = '';
        }
    };

    const handleFileSelected = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setError(undefined);
        setPreviewUrl(URL.createObjectURL(file));

        router.post(
            AvatarController.update.url(),
            { avatar: file },
            {
                preserveScroll: true,
                forceFormData: true,
                onStart: () => setProcessing(true),
                onError: (errors) => setError(errors.avatar),
                onFinish: () => {
                    setProcessing(false);
                    clearPreview();
                },
            },
        );
    };

    const removeAvatar = () => {
        setError(undefined);

        router.delete(AvatarController.destroy.url(), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-4">
                <Avatar className="size-20 overflow-hidden rounded-full border">
                    <AvatarImage
                        src={previewUrl ?? user.avatar}
                        alt={user.name}
                        className="object-cover"
                    />
                    <AvatarFallback className="bg-neutral-200 text-lg text-black dark:bg-neutral-700 dark:text-white">
                        {getInitials(user.name)}
                    </AvatarFallback>
                </Avatar>

                <div className="flex flex-wrap gap-2">
                    <input
                        ref={fileInput}
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={handleFileSelected}
                        data-test="avatar-input"
                    />

                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => fileInput.current?.click()}
                    >
                        <Camera />
                        {processing ? 'Uploading...' : 'Change photo'}
                    </Button>

                    {user.avatar_path && (
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={processing}
                            onClick={removeAvatar}
                        >
                            <Trash2 />
                            Remove photo
                        </Button>
                    )}
                </div>
            </div>

            <p className="text-muted-foreground text-xs">
                JPG, PNG, WEBP, or GIF up to 2 MB.
            </p>

            <InputError message={error} />
        </div>
    );
}
