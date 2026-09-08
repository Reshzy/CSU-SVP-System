export type ApprovalStatus = 'pending' | 'approved' | 'rejected';

export type SvpRole =
    | 'System Admin'
    | 'Executive Officer'
    | 'Supply Officer'
    | 'BAC Chair'
    | 'Budget Office'
    | 'BAC Members'
    | 'BAC Secretariat'
    | 'Canvassing Unit'
    | 'Accounting Office'
    | 'Dean'
    | 'End User'
    | 'Supplier';

export type Department = {
    id: number;
    name: string;
    code: string;
    description?: string | null;
    head_name?: string | null;
    contact_person?: string | null;
    contact_email?: string | null;
    contact_number?: string | null;
    is_active?: boolean;
    is_archived?: boolean;
    users_count?: number;
};

export type Position = {
    id: number;
    name: string;
};

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    department_id: number | null;
    position_id: number | null;
    employee_id: string | null;
    phone: string | null;
    is_active: boolean;
    approval_status: ApprovalStatus;
    approved_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    department?: Department | null;
    position?: Position | null;
    approver?: Pick<User, 'id' | 'name'> | null;
    rejecter?: Pick<User, 'id' | 'name'> | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /** Nav and dashboard hints only; authorization lives on the server. */
    roles: SvpRole[];
    primaryRole: SvpRole | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
