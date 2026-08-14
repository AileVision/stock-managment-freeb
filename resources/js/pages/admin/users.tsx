import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
    UserRound,
    Users as UsersIcon,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import usersRoutes from '@/routes/users';
import type { AdminUser, Auth, Paginated, Role } from '@/types';

type Props = {
    users: Paginated<AdminUser>;
    roles: Pick<Role, 'id' | 'name'>[];
};

type UserFormData = {
    name: string;
    email: string;
    password: string;
    role: string;
};

const emptyForm: UserFormData = {
    name: '',
    email: '',
    password: '',
    role: '',
};

function UserForm({
    user,
    roles,
    canManageRoles,
    onDone,
}: {
    user: AdminUser | null;
    roles: Props['roles'];
    canManageRoles: boolean;
    onDone: () => void;
}) {
    const editing = user !== null;
    const form = useForm<UserFormData>(
        editing && user
            ? {
                  name: user.name,
                  email: user.email,
                  password: '',
                  role: user.roles[0] ?? '',
              }
            : emptyForm,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing && user) {
            form.patch(usersRoutes.update(user.id).url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(usersRoutes.store().url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form onSubmit={submit} className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="user-name">Nom</Label>
                    <Input
                        id="user-name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Ex. : Awa Diallo"
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="user-email">Adresse e-mail</Label>
                    <Input
                        id="user-email"
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        placeholder="email@example.com"
                        required
                    />
                    <InputError message={form.errors.email} />
                </div>

                {canManageRoles && !(editing && user?.is_superadmin) ? (
                    <div className="space-y-2">
                        <Label htmlFor="user-role">Rôle</Label>
                        <Select
                            value={form.data.role}
                            onValueChange={(v) => form.setData('role', v)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Choisir…" />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role.id} value={role.name}>
                                        {role.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.role} />
                    </div>
                ) : (
                    <div className="space-y-2">
                        <Label>Rôle</Label>
                        <p className="text-sm text-muted-foreground">
                            {editing && user?.is_superadmin
                                ? 'Ce compte superadmin conserve son rôle.'
                                : editing && user
                                  ? `Rôle actuel : ${user.roles[0] ?? '—'}. `
                                  : 'Nouveau compte : rôle « gestionnaire » par défaut. '}
                            {!(editing && user?.is_superadmin) &&
                                ' Seul le superadministrateur peut attribuer les rôles.'}
                        </p>
                    </div>
                )}

                <div className="space-y-2">
                    <Label htmlFor="user-password">
                        {editing
                            ? 'Nouveau mot de passe (optionnel)'
                            : 'Mot de passe'}
                    </Label>
                    <Input
                        id="user-password"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                        placeholder={
                            editing
                                ? 'Laisser vide pour conserver'
                                : '8 caractères minimum'
                        }
                        required={!editing}
                    />
                    <InputError message={form.errors.password} />
                </div>
            </div>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {editing
                        ? 'Enregistrer les modifications'
                        : 'Créer l’utilisateur'}
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function Users({ users, roles }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<AdminUser | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<AdminUser | null>(null);
    const { user: currentUser, permissions } = usePage<{ auth: Auth }>().props
        .auth;
    const canManageRoles = permissions.includes('roles.manage');

    const openCreate = () => {
        setEditingUser(null);
        setDialogOpen(true);
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(usersRoutes.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <>
            <Head title="Utilisateurs" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Utilisateurs
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Gérez les comptes et leurs rôles.
                        </p>
                    </div>
                    <Button onClick={openCreate} size="sm">
                        <Plus />
                        Nouvel utilisateur
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Utilisateur</TableHead>
                                <TableHead>Rôles</TableHead>
                                <TableHead className="text-right">
                                    Créé le
                                </TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted">
                                                <UserRound className="size-4 text-muted-foreground" />
                                            </span>
                                            <div>
                                                <div className="font-medium">
                                                    {user.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {user.email}
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1.5">
                                            {user.is_superadmin ? (
                                                <Badge className="gap-1">
                                                    <ShieldCheck className="size-3" />
                                                    Super administrateur
                                                </Badge>
                                            ) : (
                                                user.roles.map((role) => (
                                                    <Badge
                                                        key={role}
                                                        variant="secondary"
                                                    >
                                                        {role}
                                                    </Badge>
                                                ))
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground">
                                        {formatDate(user.created_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {user.is_superadmin &&
                                        user.id !== currentUser.id ? (
                                            <span className="text-xs text-muted-foreground">
                                                Compte protégé
                                            </span>
                                        ) : (
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => {
                                                        setEditingUser(user);
                                                        setDialogOpen(true);
                                                    }}
                                                    aria-label={`Modifier ${user.name}`}
                                                >
                                                    <Pencil />
                                                </Button>
                                                {!user.is_superadmin && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                        onClick={() =>
                                                            setDeleteTarget(
                                                                user,
                                                            )
                                                        }
                                                        aria-label={`Supprimer ${user.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {users.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <UsersIcon className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun utilisateur
                                            </p>
                                            <p className="text-xs">
                                                Créez votre premier compte.
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingUser
                                ? 'Modifier l’utilisateur'
                                : 'Nouvel utilisateur'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingUser
                                ? `Mise à jour de « ${editingUser.name} ».`
                                : 'Créez un compte et attribuez-lui un rôle.'}
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <UserForm
                            key={editingUser?.id ?? 'new'}
                            user={editingUser}
                            roles={roles}
                            canManageRoles={canManageRoles}
                            onDone={() => setDialogOpen(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Supprimer l’utilisateur</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer «{' '}
                            {deleteTarget?.name} » ? Cette action est
                            irréversible.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            Annuler
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Supprimer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Users.layout = {
    breadcrumbs: [
        {
            title: 'Utilisateurs',
            href: usersRoutes.index().url,
        },
    ],
};
