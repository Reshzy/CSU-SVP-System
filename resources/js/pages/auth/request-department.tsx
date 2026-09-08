import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/register/request-department';
import { register } from '@/routes';
import type { Department } from '@/types';

type Props = {
    departments: Pick<Department, 'name' | 'code'>[];
};

export default function RequestDepartment({ departments }: Props) {
    return (
        <>
            <Head title="Request a department" />

            <Form {...store.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Department name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    placeholder="College of Marine Sciences"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="code">Short code</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    type="text"
                                    required
                                    tabIndex={2}
                                    maxLength={20}
                                    placeholder="CMS"
                                    className="uppercase"
                                />
                                <InputError message={errors.code} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="head_name">
                                    Department head
                                </Label>
                                <Input
                                    id="head_name"
                                    name="head_name"
                                    type="text"
                                    tabIndex={3}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.head_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="requester_email">
                                    Your email address
                                </Label>
                                <Input
                                    id="requester_email"
                                    name="requester_email"
                                    type="email"
                                    required
                                    tabIndex={4}
                                    placeholder="email@cagsu.edu.ph"
                                />
                                <InputError message={errors.requester_email} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={5}
                                disabled={processing}
                                data-test="request-department-button"
                            >
                                {processing && <Spinner />}
                                Submit request
                            </Button>
                        </div>

                        <div className="text-muted-foreground text-center text-sm">
                            Found it after all?{' '}
                            <TextLink href={register()} tabIndex={6}>
                                Back to registration
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {departments.length > 0 && (
                <div className="text-muted-foreground mt-6 text-xs">
                    <p className="mb-2 font-medium">Already available:</p>
                    <p>
                        {departments
                            .map((department) => department.code)
                            .join(' · ')}
                    </p>
                </div>
            )}
        </>
    );
}

RequestDepartment.layout = {
    title: 'Request a department',
    description:
        'Ask the Executive Officer to add a department missing from the list',
};
