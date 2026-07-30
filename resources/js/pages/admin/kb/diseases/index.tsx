import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Knowledge Base: Penyakit', href: '/admin/kb/diseases' }];

type Disease = {
    id: number;
    name: string;
    slug: string;
    category: string | null;
    description: string | null;
    dietary_restrictions: string[] | null;
    recommended_exercise: string[] | null;
    contraindicated_exercise: string[] | null;
    reference_source: string | null;
};

export default function AdminKbDiseasesIndex({
    diseases,
    categories,
    filters,
}: {
    diseases: Disease[];
    categories: string[];
    filters: { category?: string; search?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (next: Partial<{ category: string; search: string }>) => {
        router.get(
            '/admin/kb/diseases',
            { ...filters, ...next, category: next.category === 'all' ? undefined : (next.category ?? filters.category) },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Knowledge Base: Penyakit" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <HeadingSmall
                        title="Knowledge Base: Penyakit"
                        description="Basis data penyakit beserta pantangan diet dan olahraga"
                    />
                    <DiseaseFormDialog categories={categories} trigger={<Button>+ Tambah Penyakit</Button>} />
                </div>

                <div className="flex flex-wrap gap-3">
                    <Input
                        placeholder="Cari nama penyakit..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && applyFilters({ search })}
                        onBlur={() => applyFilters({ search })}
                        className="w-64"
                    />
                    <Select value={filters.category ?? 'all'} onValueChange={(value) => applyFilters({ category: value })}>
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
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Nama</th>
                                <th className="p-3 font-medium">Kategori</th>
                                <th className="p-3 font-medium">Pantangan Diet</th>
                                <th className="p-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {diseases.map((disease) => (
                                <tr key={disease.id} className="border-t align-top">
                                    <td className="p-3">
                                        <div className="font-medium">{disease.name}</div>
                                        <div className="text-muted-foreground text-xs">{disease.slug}</div>
                                    </td>
                                    <td className="p-3">{disease.category ?? '-'}</td>
                                    <td className="max-w-xs p-3 text-xs">{(disease.dietary_restrictions ?? []).join(', ') || '-'}</td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <DiseaseFormDialog
                                            categories={categories}
                                            disease={disease}
                                            trigger={
                                                <Button variant="outline" size="sm" className="mr-2">
                                                    Edit
                                                </Button>
                                            }
                                        />
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => confirm(`Hapus "${disease.name}"?`) && router.delete(`/admin/kb/diseases/${disease.id}`)}
                                        >
                                            Hapus
                                        </Button>
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

function DiseaseFormDialog({ disease, categories, trigger }: { disease?: Disease; categories: string[]; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, transform, processing, errors } = useForm({
        name: disease?.name ?? '',
        category: disease?.category ?? categories[0] ?? '',
        description: disease?.description ?? '',
        dietary_restrictions: disease ? JSON.stringify(disease.dietary_restrictions ?? [], null, 2) : '[]',
        recommended_exercise: disease ? JSON.stringify(disease.recommended_exercise ?? [], null, 2) : '[]',
        contraindicated_exercise: disease ? JSON.stringify(disease.contraindicated_exercise ?? [], null, 2) : '[]',
        reference_source: disease?.reference_source ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        let dietary, recommended, contraindicated;
        try {
            dietary = data.dietary_restrictions.trim() ? JSON.parse(data.dietary_restrictions) : null;
            recommended = data.recommended_exercise.trim() ? JSON.parse(data.recommended_exercise) : null;
            contraindicated = data.contraindicated_exercise.trim() ? JSON.parse(data.contraindicated_exercise) : null;
        } catch {
            alert('Format JSON pada salah satu field tidak valid.');
            return;
        }

        transform((formData) => ({
            ...formData,
            dietary_restrictions: dietary,
            recommended_exercise: recommended,
            contraindicated_exercise: contraindicated,
        }));

        if (disease) {
            patch(`/admin/kb/diseases/${disease.id}`, { onSuccess: () => setOpen(false) });
        } else {
            post('/admin/kb/diseases', { onSuccess: () => setOpen(false) });
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogTitle>{disease ? 'Edit Penyakit' : 'Tambah Penyakit'}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Nama</Label>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            <InputErrorText message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Kategori</Label>
                            <Input value={data.category} onChange={(e) => setData('category', e.target.value)} placeholder="metabolic, dll" />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Deskripsi</Label>
                        <Textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Pantangan Diet (JSON array)</Label>
                        <Textarea
                            value={data.dietary_restrictions}
                            onChange={(e) => setData('dietary_restrictions', e.target.value)}
                            rows={2}
                            className="font-mono text-xs"
                        />
                        <p className="text-muted-foreground text-xs">Mis. {'["low_sodium", "low_sugar"]'}</p>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Olahraga Dianjurkan (JSON array)</Label>
                            <Textarea
                                value={data.recommended_exercise}
                                onChange={(e) => setData('recommended_exercise', e.target.value)}
                                rows={2}
                                className="font-mono text-xs"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Olahraga Terlarang (JSON array)</Label>
                            <Textarea
                                value={data.contraindicated_exercise}
                                onChange={(e) => setData('contraindicated_exercise', e.target.value)}
                                rows={2}
                                className="font-mono text-xs"
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Sumber Referensi</Label>
                        <Input value={data.reference_source} onChange={(e) => setData('reference_source', e.target.value)} />
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

function InputErrorText({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="text-destructive text-sm">{message}</p>;
}
