import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Roles', href: '/admin/roles' }];

type Permission = { id: number; name: string; module: string };
type Role = { id: number; name: string; label: string; permissions: Permission[] };

export default function AdminRolesIndex({ roles, permissions }: { roles: Role[]; permissions: Permission[] }) {
    const grouped = permissions.reduce<Record<string, Permission[]>>((acc, permission) => {
        (acc[permission.module] ??= []).push(permission);
        return acc;
    }, {});

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles & Permissions" />

            <div className="space-y-6 p-4">
                <HeadingSmall title="Roles & Permissions" description="Atur permission apa saja yang dimiliki tiap role" />

                <div className="grid gap-6 lg:grid-cols-3">
                    {roles.map((role) => (
                        <RoleCard key={role.id} role={role} grouped={grouped} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function RoleCard({ role, grouped }: { role: Role; grouped: Record<string, Permission[]> }) {
    const [selected, setSelected] = useState<Set<number>>(new Set(role.permissions.map((p) => p.id)));
    const [processing, setProcessing] = useState(false);

    const toggle = (id: number) => {
        setSelected((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };

    const save = () => {
        setProcessing(true);
        router.patch(
            `/admin/roles/${role.id}/permissions`,
            { permission_ids: Array.from(selected) },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>{role.label}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {Object.entries(grouped).map(([module, modulePermissions]) => (
                    <div key={module}>
                        <p className="text-muted-foreground mb-2 text-xs font-semibold uppercase">{module}</p>
                        <div className="space-y-2">
                            {modulePermissions.map((permission) => (
                                <label key={permission.id} className="flex items-center gap-2 text-sm">
                                    <Checkbox checked={selected.has(permission.id)} onCheckedChange={() => toggle(permission.id)} />
                                    {permission.name}
                                </label>
                            ))}
                        </div>
                    </div>
                ))}
            </CardContent>
            <CardFooter>
                <Button onClick={save} disabled={processing}>
                    Simpan
                </Button>
            </CardFooter>
        </Card>
    );
}
