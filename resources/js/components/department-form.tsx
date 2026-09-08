import { Form } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Department } from '@/types';

type Props = {
    /** Spread from a Wayfinder `.form()` helper. */
    action: ComponentProps<typeof Form>['action'];
    method: ComponentProps<typeof Form>['method'];
    department?: Department;
    submitLabel: string;
};

export function DepartmentForm({
    action,
    method,
    department,
    submitLabel,
}: Props) {
    return (
        <Form
            action={action}
            method={method}
            disableWhileProcessing
            className="max-w-xl space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            autoFocus
                            defaultValue={department?.name ?? ''}
                            placeholder="College of Engineering"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="code">Code</Label>
                        <Input
                            id="code"
                            name="code"
                            required
                            maxLength={20}
                            className="uppercase"
                            defaultValue={department?.code ?? ''}
                            placeholder="COE"
                        />
                        <InputError message={errors.code} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Input
                            id="description"
                            name="description"
                            defaultValue={department?.description ?? ''}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="head_name">Department head</Label>
                        <Input
                            id="head_name"
                            name="head_name"
                            defaultValue={department?.head_name ?? ''}
                        />
                        <InputError message={errors.head_name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="contact_person">
                                Contact person
                            </Label>
                            <Input
                                id="contact_person"
                                name="contact_person"
                                defaultValue={department?.contact_person ?? ''}
                            />
                            <InputError message={errors.contact_person} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="contact_number">
                                Contact number
                            </Label>
                            <Input
                                id="contact_number"
                                name="contact_number"
                                defaultValue={department?.contact_number ?? ''}
                            />
                            <InputError message={errors.contact_number} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="contact_email">Contact email</Label>
                        <Input
                            id="contact_email"
                            name="contact_email"
                            type="email"
                            defaultValue={department?.contact_email ?? ''}
                        />
                        <InputError message={errors.contact_email} />
                    </div>

                    <div className="space-y-3 pt-2">
                        <div className="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0" />
                            <Checkbox
                                id="is_active"
                                name="is_active"
                                value="1"
                                defaultChecked={department?.is_active ?? true}
                            />
                            <Label htmlFor="is_active" className="font-normal">
                                Selectable on the registration form
                            </Label>
                        </div>

                        <div className="flex items-center gap-2">
                            <input type="hidden" name="is_archived" value="0" />
                            <Checkbox
                                id="is_archived"
                                name="is_archived"
                                value="1"
                                defaultChecked={
                                    department?.is_archived ?? false
                                }
                            />
                            <Label
                                htmlFor="is_archived"
                                className="font-normal"
                            >
                                Archived
                            </Label>
                        </div>
                    </div>

                    <Button type="submit" data-test="save-department-button">
                        {processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
