import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Plans', href: '/admin/plans' }];

type Plan = {
    id: number;
    name: string;
    slug: string;
    price: string;
    billing_cycle: 'monthly' | 'yearly' | 'lifetime';
    features: string[] | null;
    max_programs: number;
    has_coach_access: boolean;
    is_active: boolean;
};

export default function AdminPlansIndex({ plans }: { plans: Plan[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Plans" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <HeadingSmall title="Plans" description="Kelola tier langganan dan fitur yang dibuka tiap paket" />
                    <PlanFormDialog trigger={<Button>+ Tambah Plan</Button>} />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    {plans.map((plan) => (
                        <Card key={plan.id}>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-base">{plan.name}</CardTitle>
                                <Badge variant={plan.is_active ? 'default' : 'secondary'}>{plan.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p className="font-semibold">
                                    Rp {Number(plan.price).toLocaleString('id-ID')} / {plan.billing_cycle}
                                </p>
                                <p className="text-muted-foreground">Max program: {plan.max_programs}</p>
                                <p className="text-muted-foreground">Akses Coach: {plan.has_coach_access ? 'Ya' : 'Tidak'}</p>
                                <PlanFormDialog plan={plan} trigger={<Button variant="outline" size="sm" className="w-full">Edit</Button>} />
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function PlanFormDialog({ plan, trigger }: { plan?: Plan; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, transform, processing, errors } = useForm({
        name: plan?.name ?? '',
        slug: plan?.slug ?? '',
        price: plan?.price ?? '0',
        billing_cycle: plan?.billing_cycle ?? 'monthly',
        features: (plan?.features ?? []).join('\n'),
        max_programs: plan?.max_programs ?? 1,
        has_coach_access: plan?.has_coach_access ?? false,
        is_active: plan?.is_active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            features: formData.features
                .split('\n')
                .map((f) => f.trim())
                .filter(Boolean),
        }));

        if (plan) {
            patch(`/admin/plans/${plan.id}`, { onSuccess: () => setOpen(false) });
        } else {
            post('/admin/plans', { onSuccess: () => setOpen(false) });
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{plan ? `Edit ${plan.name}` : 'Tambah Plan'}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Nama</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </div>
                    {!plan && (
                        <div className="grid gap-2">
                            <Label>Slug</Label>
                            <Input value={data.slug} onChange={(e) => setData('slug', e.target.value)} required />
                        </div>
                    )}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label>Harga (IDR)</Label>
                            <Input type="number" value={data.price} onChange={(e) => setData('price', e.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Siklus</Label>
                            <Select value={data.billing_cycle} onValueChange={(v) => setData('billing_cycle', v as typeof data.billing_cycle)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="monthly">Bulanan</SelectItem>
                                    <SelectItem value="yearly">Tahunan</SelectItem>
                                    <SelectItem value="lifetime">Selamanya</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Fitur (satu per baris)</Label>
                        <Textarea value={data.features} onChange={(e) => setData('features', e.target.value)} rows={4} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Maks. Program Aktif</Label>
                        <Input
                            type="number"
                            min={1}
                            value={data.max_programs}
                            onChange={(e) => setData('max_programs', Number(e.target.value))}
                        />
                        <p className="text-muted-foreground text-xs">{errors.max_programs}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox checked={data.has_coach_access} onCheckedChange={(v) => setData('has_coach_access', !!v)} />
                        <Label>Akses Coach</Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', !!v)} />
                        <Label>Aktif (tampil di katalog)</Label>
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
