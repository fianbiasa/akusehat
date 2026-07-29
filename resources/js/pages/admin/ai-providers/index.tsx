import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Providers', href: '/admin/ai/providers' }];

type Model = {
    id: number;
    name: string;
    model_key: string;
    is_active: boolean;
    input_cost_per_1k: string | null;
    output_cost_per_1k: string | null;
};
type Provider = { id: number; name: string; slug: string; type: 'cloud' | 'local'; is_active: boolean; models: Model[] };

export default function AdminAiProvidersIndex({ providers }: { providers: Provider[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Providers" />

            <div className="space-y-6 p-4">
                <HeadingSmall title="AI Providers" description="Katalog provider dan model AI yang tersedia untuk member" />

                <div className="grid gap-4 md:grid-cols-2">
                    {providers.map((provider) => (
                        <ProviderCard key={provider.id} provider={provider} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function ProviderCard({ provider }: { provider: Provider }) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2 text-base">
                    {provider.name}
                    <Badge variant="outline">{provider.type}</Badge>
                </CardTitle>
                <Button
                    size="sm"
                    variant={provider.is_active ? 'secondary' : 'default'}
                    onClick={() =>
                        router.patch(`/admin/ai/providers/${provider.id}`, {
                            name: provider.name,
                            type: provider.type,
                            is_active: !provider.is_active,
                        })
                    }
                >
                    {provider.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                </Button>
            </CardHeader>
            <CardContent className="space-y-2">
                {provider.models.map((model) => (
                    <div key={model.id} className="flex items-center justify-between rounded border p-2 text-sm">
                        <div>
                            <div className="font-medium">{model.name}</div>
                            <div className="text-muted-foreground text-xs">{model.model_key}</div>
                        </div>
                        <Badge variant={model.is_active ? 'default' : 'secondary'}>{model.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                    </div>
                ))}
                <AddModelDialog provider={provider} />
            </CardContent>
        </Card>
    );
}

function AddModelDialog({ provider }: { provider: Provider }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        model_key: '',
        input_cost_per_1k: '',
        output_cost_per_1k: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/admin/ai/providers/${provider.id}/models`, { onSuccess: () => (setOpen(false), reset()) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="w-full">
                    + Tambah Model
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Tambah Model - {provider.name}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Nama</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </div>
                    <div className="grid gap-2">
                        <Label>Model Key (API identifier)</Label>
                        <Input value={data.model_key} onChange={(e) => setData('model_key', e.target.value)} required />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label>Input cost /1k</Label>
                            <Input
                                type="number"
                                step="0.000001"
                                value={data.input_cost_per_1k}
                                onChange={(e) => setData('input_cost_per_1k', e.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Output cost /1k</Label>
                            <Input
                                type="number"
                                step="0.000001"
                                value={data.output_cost_per_1k}
                                onChange={(e) => setData('output_cost_per_1k', e.target.value)}
                            />
                        </div>
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
