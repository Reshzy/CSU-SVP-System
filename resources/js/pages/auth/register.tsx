import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { requestDepartment } from '@/routes/register';
import type { Department, Position } from '@/types';

type Props = {
    passwordRules: string;
    departments: Pick<Department, 'id' | 'name' | 'code'>[];
    positions: Position[];
    idProofTypes: string[];
};

export default function Register({
    passwordRules,
    departments,
    positions,
    idProofTypes,
}: Props) {
    const defaultPosition = positions.find(
        (position) => position.name === 'Employee',
    );
    const accept = idProofTypes.map((type) => `.${type}`).join(',');

    return (
        <>
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Full name"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@cagsu.edu.ph"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="department_id">
                                    Department
                                </Label>
                                <Select name="department_id" required>
                                    <SelectTrigger
                                        id="department_id"
                                        tabIndex={3}
                                    >
                                        <SelectValue placeholder="Select your department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((department) => (
                                            <SelectItem
                                                key={department.id}
                                                value={String(department.id)}
                                            >
                                                {department.code} —{' '}
                                                {department.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.department_id} />
                                <p className="text-muted-foreground text-xs">
                                    Not listed?{' '}
                                    <TextLink href={requestDepartment()}>
                                        Request a new department
                                    </TextLink>
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="position_id">Position</Label>
                                <Select
                                    name="position_id"
                                    required
                                    defaultValue={
                                        defaultPosition
                                            ? String(defaultPosition.id)
                                            : undefined
                                    }
                                >
                                    <SelectTrigger
                                        id="position_id"
                                        tabIndex={4}
                                    >
                                        <SelectValue placeholder="Select your position" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {positions.map((position) => (
                                            <SelectItem
                                                key={position.id}
                                                value={String(position.id)}
                                            >
                                                {position.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.position_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="id_proofs">
                                    Government-issued ID
                                </Label>
                                <Input
                                    id="id_proofs"
                                    type="file"
                                    required
                                    multiple
                                    tabIndex={5}
                                    name="id_proofs[]"
                                    accept={accept}
                                />
                                <p className="text-muted-foreground text-xs">
                                    JPEG, PNG, WEBP or PDF. Up to 10 MB each.
                                </p>
                                <InputError
                                    message={
                                        errors.id_proofs ??
                                        errors['id_proofs.0']
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={6}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={7}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={8}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

                        <div className="text-muted-foreground text-center text-sm">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={9}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description:
        'The Executive Officer reviews every registration before it can be used',
};
