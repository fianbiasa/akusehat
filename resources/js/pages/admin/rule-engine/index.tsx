import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { api } from '@/lib/api';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rule Engine', href: '/admin/rule-engine/rules' }];

type Rule = {
    id: number;
    category: string;
    name: string;
    condition: Record<string, unknown>;
    action: Record<string, unknown>;
    priority: number;
    is_active: boolean;
};

export default function AdminRuleEngineIndex({
    rules,
    categories,
    filters,
}: {
    rules: Rule[];
    categories: string[];
    filters: { category?: string };
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rule Engine" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <HeadingSmall
                        title="Rule Engine"
                        description="Aturan deterministik untuk target kalori, macro, level olahraga, air, dan pantangan penyakit"
                    />
                    <RuleFormDialog categories={categories} trigger={<Button>+ Tambah Rule</Button>} />
                </div>

                <Select
                    value={filters.category ?? 'all'}
                    onValueChange={(value) =>
                        router.get('/admin/rule-engine/rules', value === 'all' ? {} : { category: value }, { preserveState: true })
                    }
                >
                    <SelectTrigger className="w-56">
                        <SelectValue placeholder="Semua kategori" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua kategori</SelectItem>
                        {categories.map((c) => (
                            <SelectItem key={c} value={c}>
                                {c}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Kategori</th>
                                <th className="p-3 font-medium">Nama</th>
                                <th className="p-3 font-medium">Prioritas</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {rules.map((rule) => (
                                <tr key={rule.id} className="border-t align-top">
                                    <td className="p-3">
                                        <Badge variant="outline">{rule.category}</Badge>
                                    </td>
                                    <td className="p-3">
                                        <div className="font-medium">{rule.name}</div>
                                        <div className="text-muted-foreground mt-1 font-mono text-xs break-all">{JSON.stringify(rule.condition)}</div>
                                        <div className="text-muted-foreground font-mono text-xs break-all">{JSON.stringify(rule.action)}</div>
                                    </td>
                                    <td className="p-3">{rule.priority}</td>
                                    <td className="p-3">
                                        <Badge variant={rule.is_active ? 'default' : 'secondary'}>{rule.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                                    </td>
                                    <td className="space-x-2 p-3 text-right whitespace-nowrap">
                                        <TestRuleDialog rule={rule} />
                                        <RuleFormDialog
                                            categories={categories}
                                            rule={rule}
                                            trigger={
                                                <Button variant="outline" size="sm">
                                                    Edit
                                                </Button>
                                            }
                                        />
                                        {rule.is_active && (
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => router.delete(`/admin/rule-engine/rules/${rule.id}`)}
                                            >
                                                Nonaktifkan
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function RuleFormDialog({ categories, rule, trigger }: { categories: string[]; rule?: Rule; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const [jsonError, setJsonError] = useState<string | null>(null);
    const { data, setData, post, patch, processing, errors } = useForm({
        category: rule?.category ?? categories[0] ?? 'calorie_target',
        name: rule?.name ?? '',
        condition: JSON.stringify(rule?.condition ?? {}, null, 2),
        action: JSON.stringify(rule?.action ?? {}, null, 2),
        priority: rule?.priority ?? 100,
        is_active: rule?.is_active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setJsonError(null);

        let condition, action;
        try {
            condition = JSON.parse(data.condition);
            action = JSON.parse(data.action);
        } catch {
            setJsonError('Condition/Action harus JSON yang valid.');
            return;
        }

        const payload = { ...data, condition, action };
        const onSuccess = () => setOpen(false);

        if (rule) {
            patch(`/admin/rule-engine/rules/${rule.id}`, { ...payload, onSuccess } as never);
        } else {
            post('/admin/rule-engine/rules', { ...payload, onSuccess } as never);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-w-lg">
                <DialogTitle>{rule ? 'Edit Rule' : 'Tambah Rule'}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label>Kategori</Label>
                            <Input value={data.category} onChange={(e) => setData('category', e.target.value)} required />
                        </div>
                        <div className="grid gap-2">
                            <Label>Prioritas</Label>
                            <Input type="number" value={data.priority} onChange={(e) => setData('priority', Number(e.target.value))} required />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Nama</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label>Condition (JSON)</Label>
                        <textarea
                            className="border-input min-h-24 rounded-md border p-2 font-mono text-xs"
                            value={data.condition}
                            onChange={(e) => setData('condition', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Action (JSON)</Label>
                        <textarea
                            className="border-input min-h-24 rounded-md border p-2 font-mono text-xs"
                            value={data.action}
                            onChange={(e) => setData('action', e.target.value)}
                        />
                    </div>
                    {jsonError && <p className="text-destructive text-sm">{jsonError}</p>}
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

function TestRuleDialog({ rule }: { rule: Rule }) {
    const [open, setOpen] = useState(false);
    const [sample, setSample] = useState({ bmi: '', age: '', gender: '', activity_level: '', diseases: '', weight_kg: '', tdee: '' });
    const [result, setResult] = useState<{ matches: boolean; action: Record<string, unknown> | null } | null>(null);
    const [loading, setLoading] = useState(false);

    const run = async () => {
        setLoading(true);
        const payload = {
            bmi: sample.bmi ? Number(sample.bmi) : null,
            age: sample.age ? Number(sample.age) : null,
            gender: sample.gender || null,
            activity_level: sample.activity_level || null,
            diseases: sample.diseases ? sample.diseases.split(',').map((s) => s.trim()) : [],
            weight_kg: sample.weight_kg ? Number(sample.weight_kg) : null,
            tdee: sample.tdee ? Number(sample.tdee) : null,
        };
        const res = await api.post<{ matches: boolean; action: Record<string, unknown> | null }>(`/admin/rule-engine/rules/${rule.id}/test`, payload);
        setResult(res);
        setLoading(false);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Uji Coba
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Uji Coba: {rule.name}</DialogTitle>
                <div className="grid grid-cols-2 gap-3">
                    <Input placeholder="bmi" value={sample.bmi} onChange={(e) => setSample({ ...sample, bmi: e.target.value })} />
                    <Input placeholder="age" value={sample.age} onChange={(e) => setSample({ ...sample, age: e.target.value })} />
                    <Input
                        placeholder="gender (male/female)"
                        value={sample.gender}
                        onChange={(e) => setSample({ ...sample, gender: e.target.value })}
                    />
                    <Input
                        placeholder="activity_level"
                        value={sample.activity_level}
                        onChange={(e) => setSample({ ...sample, activity_level: e.target.value })}
                    />
                    <Input placeholder="weight_kg" value={sample.weight_kg} onChange={(e) => setSample({ ...sample, weight_kg: e.target.value })} />
                    <Input placeholder="tdee" value={sample.tdee} onChange={(e) => setSample({ ...sample, tdee: e.target.value })} />
                    <Input
                        className="col-span-2"
                        placeholder="diseases (comma separated slugs)"
                        value={sample.diseases}
                        onChange={(e) => setSample({ ...sample, diseases: e.target.value })}
                    />
                </div>
                <Button type="button" onClick={run} disabled={loading}>
                    Jalankan
                </Button>
                {result && (
                    <div className="rounded-md border p-3 text-sm">
                        <p className="font-medium">{result.matches ? '✅ Cocok' : '❌ Tidak cocok'}</p>
                        {result.action && <pre className="mt-2 text-xs">{JSON.stringify(result.action, null, 2)}</pre>}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
