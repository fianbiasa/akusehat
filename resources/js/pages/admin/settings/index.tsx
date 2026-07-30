import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Platform Settings', href: '/admin/settings' }];

type Model = { id: number; name: string; model_key: string };
type Provider = { id: number; name: string; slug: string; type: 'cloud' | 'local'; models: Model[] };
type AiDefault = { provider_id: number; model_id: number; temperature: number; has_api_key: boolean } | null;

export default function AdminAppSettingsIndex({
    aiDefault,
    maintenanceMode,
    providers,
}: {
    aiDefault: AiDefault;
    maintenanceMode: { enabled: boolean; message: string | null };
    providers: Provider[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Platform Settings" />

            <div className="space-y-8 p-4">
                <HeadingSmall title="Platform Settings" description="Konfigurasi platform-wide: AI default dan mode perawatan" />

                <AiDefaultCard aiDefault={aiDefault} providers={providers} />
                <MaintenanceModeCard maintenanceMode={maintenanceMode} />
            </div>
        </AppLayout>
    );
}

function AiDefaultCard({ aiDefault, providers }: { aiDefault: AiDefault; providers: Provider[] }) {
    const { data, setData, patch, processing, errors } = useForm({
        provider_id: aiDefault ? String(aiDefault.provider_id) : '',
        model_id: aiDefault ? String(aiDefault.model_id) : '',
        api_key: '',
        temperature: aiDefault ? String(aiDefault.temperature) : '0.7',
    });

    const selectedProvider = providers.find((p) => String(p.id) === data.provider_id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch('/admin/settings/ai-default', { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    AI Provider Default Platform
                    {aiDefault?.has_api_key && <Badge>Aktif</Badge>}
                </CardTitle>
                <p className="text-muted-foreground text-sm">
                    Dipakai sebagai fallback saat seorang Member belum mengkonfigurasi API key AI miliknya sendiri.
                </p>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Provider</Label>
                            <Select
                                value={data.provider_id}
                                onValueChange={(value) => {
                                    setData('provider_id', value);
                                    setData('model_id', '');
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    {providers.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.provider_id && <p className="text-destructive text-sm">{errors.provider_id}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label>Model</Label>
                            <Select value={data.model_id} onValueChange={(value) => setData('model_id', value)} disabled={!selectedProvider}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih model" />
                                </SelectTrigger>
                                <SelectContent>
                                    {(selectedProvider?.models ?? []).map((m) => (
                                        <SelectItem key={m.id} value={String(m.id)}>
                                            {m.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>API Key</Label>
                        <Input
                            type="password"
                            value={data.api_key}
                            onChange={(e) => setData('api_key', e.target.value)}
                            placeholder={aiDefault?.has_api_key ? 'Biarkan kosong untuk tetap memakai key saat ini' : 'sk-...'}
                        />
                        <p className="text-muted-foreground text-xs">Tersimpan terenkripsi, tidak akan ditampilkan lagi setelah disimpan.</p>
                    </div>

                    <div className="grid gap-2">
                        <Label>Temperature</Label>
                        <Input
                            type="number"
                            step="0.1"
                            min="0"
                            max="2"
                            className="w-32"
                            value={data.temperature}
                            onChange={(e) => setData('temperature', e.target.value)}
                        />
                    </div>

                    <Button type="submit" disabled={processing || !data.provider_id || !data.model_id}>
                        Simpan
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function MaintenanceModeCard({ maintenanceMode }: { maintenanceMode: { enabled: boolean; message: string | null } }) {
    const { data, setData, patch, processing } = useForm({
        enabled: maintenanceMode.enabled,
        message: maintenanceMode.message ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch('/admin/settings/maintenance-mode', { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    Mode Perawatan
                    {maintenanceMode.enabled && <Badge variant="destructive">Aktif</Badge>}
                </CardTitle>
                <p className="text-muted-foreground text-sm">
                    Saat aktif, semua akses selain Admin akan menampilkan pesan perawatan. Halaman login tetap bisa diakses.
                </p>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Checkbox checked={data.enabled} onCheckedChange={(v) => setData('enabled', !!v)} />
                        <Label>Aktifkan mode perawatan</Label>
                    </div>
                    <div className="grid gap-2">
                        <Label>Pesan untuk pengguna</Label>
                        <Textarea
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            rows={3}
                            placeholder="Platform sedang dalam perawatan. Silakan coba lagi beberapa saat lagi."
                        />
                    </div>
                    <Button type="submit" variant={data.enabled ? 'destructive' : 'default'} disabled={processing}>
                        Simpan
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
