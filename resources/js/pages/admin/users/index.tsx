import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated, type User } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users', href: '/admin/users' }];

type Role = { id: number; name: string; label: string };

const statusVariant = {
    active: 'default',
    pending: 'secondary',
    suspended: 'destructive',
} as const;

export default function AdminUsersIndex({
    users,
    roles,
    filters,
}: {
    users: Paginated<User & { role: Role }>;
    roles: Role[];
    filters: { q?: string; role?: string; status?: string };
}) {
    const [q, setQ] = useState(filters.q ?? '');

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get('/admin/users', { q, role: filters.role, status: filters.status, ...next }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <HeadingSmall title="Users" description="Manage member, coach, and admin accounts" />
                    <CreateUserDialog roles={roles} />
                </div>

                <div className="flex flex-wrap gap-3">
                    <Input
                        placeholder="Cari nama atau email..."
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && applyFilters({ q })}
                        className="max-w-xs"
                    />
                    <Select value={filters.role ?? 'all'} onValueChange={(value) => applyFilters({ role: value === 'all' ? undefined : value })}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua role</SelectItem>
                            {roles.map((role) => (
                                <SelectItem key={role.id} value={role.name}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.status ?? 'all'} onValueChange={(value) => applyFilters({ status: value === 'all' ? undefined : value })}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="suspended">Suspended</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Nama</th>
                                <th className="p-3 font-medium">Role</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="p-3">
                                        <div className="font-medium">{user.name}</div>
                                        <div className="text-muted-foreground text-xs">{user.email}</div>
                                    </td>
                                    <td className="p-3">{user.role.label}</td>
                                    <td className="p-3">
                                        <Badge variant={statusVariant[user.status]}>{user.status}</Badge>
                                    </td>
                                    <td className="p-3 text-right">
                                        <EditUserDialog user={user} roles={roles} />
                                        <DeleteUserDialog user={user} />
                                    </td>
                                </tr>
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="text-muted-foreground p-6 text-center">
                                        Tidak ada user yang cocok.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {users.links.map((link, index) => (
                        <Button
                            key={index}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function CreateUserDialog({ roles }: { roles: Role[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role_id: '',
        status: 'active',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/admin/users', {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>+ Tambah</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Tambah User</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nama</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required />
                        <InputError message={errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Konfirmasi Password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Role</Label>
                        <Select value={data.role_id} onValueChange={(value) => setData('role_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih role" />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role.id} value={String(role.id)}>
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role_id} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditUserDialog({ user, roles }: { user: User & { role: Role }; roles: Role[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        role_id: String(user.role.id),
        status: user.status,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(`/admin/users/${user.id}`, { onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="mr-2">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Edit User</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`name-${user.id}`}>Nama</Label>
                        <Input id={`name-${user.id}`} value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`email-${user.id}`}>Email</Label>
                        <Input id={`email-${user.id}`} type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Role</Label>
                        <Select value={data.role_id} onValueChange={(value) => setData('role_id', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role.id} value={String(role.id)}>
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label>Status</Label>
                        <Select value={data.status} onValueChange={(value) => setData('status', value as typeof data.status)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Aktif</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="suspended">Suspended</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteUserDialog({ user }: { user: User }) {
    const [open, setOpen] = useState(false);
    const { delete: destroy, processing } = useForm();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    Hapus
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Hapus {user.name}?</DialogTitle>
                <p className="text-muted-foreground text-sm">Akun akan dinonaktifkan (soft delete) dan bisa dipulihkan dari database bila perlu.</p>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={processing}
                        onClick={() => destroy(`/admin/users/${user.id}`, { onSuccess: () => setOpen(false) })}
                    >
                        Hapus
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
