export type Role = {
    id: number;
    name: string;
    permissions: string[];
};

export type Permission = {
    id: number;
    name: string;
    group: string;
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    created_at: string;
    roles: string[];
    is_superadmin: boolean;
};

export type Backup = {
    name: string;
    size: number;
    human_size: string;
    created_at: string | null;
};
