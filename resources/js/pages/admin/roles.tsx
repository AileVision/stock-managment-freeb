import { Head, router } from '@inertiajs/react';
import { Check, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import roles from '@/routes/roles';
import type { Permission, Role } from '@/types';

type Props = {
    roles: Role[];
    permissions: Permission[];
};

const GROUP_LABELS: Record<string, string> = {
    dashboard: 'Tableau de bord',
    products: 'Produits',
    categories: 'Catégories',
    suppliers: 'Fournisseurs',
    movements: 'Mouvements',
    reports: 'Rapports',
    replenishment: 'Réapprovisionnement',
    users: 'Utilisateurs',
    roles: 'Rôles',
};

function RoleEditor({
    role,
    permissions,
    locked = false,
}: {
    role: Role;
    permissions: Permission[];
    locked?: boolean;
}) {
    const [selected, setSelected] = useState<Set<string>>(
        () => new Set(role.permissions),
    );
    const [saving, setSaving] = useState(false);

    const groups = permissions.reduce<Record<string, Permission[]>>(
        (acc, permission) => {
            acc[permission.group] ??= [];
            acc[permission.group].push(permission);

            return acc;
        },
        {},
    );

    const toggle = (name: string) => {
        setSelected((current) => {
            const next = new Set(current);

            if (next.has(name)) {
                next.delete(name);
            } else {
                next.add(name);
            }

            return next;
        });
    };

    const toggleGroup = (group: string) => {
        const names = groups[group].map((permission) => permission.name);
        const allSelected = names.every((name) => selected.has(name));

        setSelected((current) => {
            const next = new Set(current);

            names.forEach((name) => {
                if (allSelected) {
                    next.delete(name);
                } else {
                    next.add(name);
                }
            });

            return next;
        });
    };

    const save = () => {
        setSaving(true);

        router.patch(
            roles.update(role.id).url,
            { permissions: [...selected] },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Card className="gap-4">
            <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                <div className="flex items-center gap-2">
                    <CardTitle className="text-base capitalize">
                        {role.name}
                    </CardTitle>
                    {locked ? (
                        <Badge>Rôle protégé</Badge>
                    ) : (
                        <Badge variant="secondary" className="tabular-nums">
                            {selected.size} permission
                            {selected.size > 1 ? 's' : ''}
                        </Badge>
                    )}
                </div>
                {!locked && (
                    <Button size="sm" onClick={save} disabled={saving}>
                        <Check />
                        Enregistrer
                    </Button>
                )}
            </CardHeader>
            <CardContent className="grid gap-4 px-5 sm:grid-cols-2 lg:grid-cols-3">
                {locked ? (
                    <p className="text-sm text-muted-foreground">
                        Le superadministrateur dispose automatiquement de tous
                        les droits sur le système. Ses permissions ne peuvent
                        pas être modifiées et son compte ne peut être ni
                        supprimé ni modifié.
                    </p>
                ) : (
                    Object.entries(groups).map(([group, items]) => {
                        const allSelected = items.every((permission) =>
                            selected.has(permission.name),
                        );

                        return (
                            <div key={group} className="space-y-2">
                                <label className="flex items-center gap-2 text-sm font-medium">
                                    <Checkbox
                                        checked={allSelected}
                                        onCheckedChange={() =>
                                            toggleGroup(group)
                                        }
                                    />
                                    {GROUP_LABELS[group] ?? group}
                                </label>
                                <div className="ml-6 space-y-1.5">
                                    {items.map((permission) => (
                                        <label
                                            key={permission.id}
                                            className="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground"
                                        >
                                            <Checkbox
                                                checked={selected.has(
                                                    permission.name,
                                                )}
                                                onCheckedChange={() =>
                                                    toggle(permission.name)
                                                }
                                            />
                                            <span className="capitalize">
                                                {permission.name
                                                    .split('.')
                                                    .pop()}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        );
                    })
                )}
            </CardContent>
        </Card>
    );
}

export default function Roles({ roles, permissions }: Props) {
    return (
        <>
            <Head title="Rôles & permissions" />

            <div className="flex flex-col gap-6">
                <div className="space-y-1">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Rôles & permissions
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Définissez les droits de chaque rôle.
                    </p>
                </div>

                {roles.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border py-10 text-center">
                        <ShieldCheck className="size-6 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Aucun rôle défini pour le moment.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {roles.map((role) => (
                            <RoleEditor
                                key={role.id}
                                role={role}
                                permissions={permissions}
                                locked={role.name === 'superadmin'}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Roles.layout = {
    breadcrumbs: [
        {
            title: 'Rôles & permissions',
            href: roles.index().url,
        },
    ],
};
