import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { api } from '@/lib/api';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Provider', href: '/ai/settings' }];

type Model = { id: number; name: string; model_key: string };
type Provider = { id: number; name: string; slug: string; type: 'cloud' | 'local'; models: Model[] };
type Setting = {
    id: number;
    temperature: string;
    is_default: boolean;
    provider: { id: number; name: string; slug: string; type: 'cloud' | 'local' };
    model: { id: number; name: string };
};

export default function AiSettings({ settings, providers }: { settings: Setting[]; providers: Provider[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Provider" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <HeadingSmall
                            title="AI Provider"
                            description="Konfigurasi penyedia AI kamu sendiri. Provider kedua otomatis jadi cadangan jika yang utama gagal."
                        />
                        <AddSettingDialog providers={providers} />
                    </div>

                    <div className="space-y-3">
                        {settings.length === 0 && <p className="text-muted-foreground text-sm">Belum ada provider AI dikonfigurasi.</p>}
                        {settings.map((setting) => (
                            <SettingRow key={setting.id} setting={setting} />
                        ))}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function SettingRow({ setting }: { setting: Setting }) {
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<{ success: boolean; latency_ms: number; message: string } | null>(null);
    const { delete: destroy, processing } = useForm();

    const runTest = async () => {
        setTesting(true);
        setTestResult(null);
        const result = await api.post<{ success: boolean; latency_ms: number; message: string }>(`/ai/settings/${setting.id}/test`);
        setTestResult(result);
        setTesting(false);
    };

    return (
        <div className="space-y-2 rounded-lg border p-4">
            <div className="flex items-center justify-between">
                <div>
                    <span className="font-medium">{setting.provider.name}</span>
                    <span className="text-muted-foreground ml-2 text-sm">{setting.model.name}</span>
                    {setting.is_default && (
                        <Badge className="ml-2" variant="default">
                            Default
                        </Badge>
                    )}
                    <Badge className="ml-2" variant="outline">
                        {setting.provider.type === 'local' ? 'Local' : 'Cloud'}
                    </Badge>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" size="sm" onClick={runTest} disabled={testing}>
                        {testing ? 'Menguji...' : 'Test Koneksi'}
                    </Button>
                    {!setting.is_default && (
                        <Button variant="outline" size="sm" onClick={() => router.post(`/ai/settings/${setting.id}/set-default`)}>
                            Jadikan Default
                        </Button>
                    )}
                    <Button variant="destructive" size="sm" disabled={processing} onClick={() => destroy(`/ai/settings/${setting.id}`)}>
                        Hapus
                    </Button>
                </div>
            </div>
            {testResult && (
                <p className={testResult.success ? 'text-sm text-green-600' : 'text-destructive text-sm'}>
                    {testResult.success ? '✅' : '❌'} {testResult.message} ({testResult.latency_ms}ms)
                </p>
            )}
        </div>
    );
}

function AddSettingDialog({ providers }: { providers: Provider[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        provider_id: '',
        model_id: '',
        api_key: '',
        temperature: '0.7',
        is_default: false,
    });

    const selectedProvider = providers.find((p) => String(p.id) === data.provider_id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/ai/settings', { onSuccess: () => (setOpen(false), reset()) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">+ Tambah Provider</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Tambah Provider AI</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
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
                                        {p.name} {p.type === 'local' && '(Local)'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.provider_id && <p className="text-destructive text-sm">{errors.provider_id}</p>}
                    </div>

                    {selectedProvider && (
                        <div className="grid gap-2">
                            <Label>Model</Label>
                            <Select value={data.model_id} onValueChange={(value) => setData('model_id', value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih model" />
                                </SelectTrigger>
                                <SelectContent>
                                    {selectedProvider.models.map((m) => (
                                        <SelectItem key={m.id} value={String(m.id)}>
                                            {m.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label>{selectedProvider?.type === 'local' ? 'Base URL (opsional)' : 'API Key'}</Label>
                        <Input
                            type={selectedProvider?.type === 'local' ? 'text' : 'password'}
                            value={data.api_key}
                            onChange={(e) => setData('api_key', e.target.value)}
                            placeholder={selectedProvider?.type === 'local' ? 'http://localhost:11434' : 'sk-...'}
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
                            value={data.temperature}
                            onChange={(e) => setData('temperature', e.target.value)}
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing || !data.provider_id || !data.model_id}>
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
