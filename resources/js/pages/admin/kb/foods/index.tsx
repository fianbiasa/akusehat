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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Knowledge Base: Makanan', href: '/admin/kb/foods' }];

type Food = {
    id: number;
    name: string;
    name_local: string | null;
    category: string | null;
    serving_unit: string;
    serving_size: string;
    calories: string;
    protein_g: string;
    carbs_g: string;
    fat_g: string;
    fiber_g: string | null;
    sodium_mg: string | null;
    glycemic_index: number | null;
    tags: string[] | null;
    source: string | null;
};

export default function AdminKbFoodsIndex({
    foods,
    categories,
    filters,
}: {
    foods: Food[];
    categories: string[];
    filters: { category?: string; search?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (next: Partial<{ category: string; search: string }>) => {
        router.get(
            '/admin/kb/foods',
            { ...filters, ...next, category: next.category === 'all' ? undefined : (next.category ?? filters.category) },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Knowledge Base: Makanan" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <HeadingSmall title="Knowledge Base: Makanan" description="Basis data makanan beserta informasi gizi untuk rencana makan" />
                    <FoodFormDialog categories={categories} trigger={<Button>+ Tambah Makanan</Button>} />
                </div>

                <div className="flex flex-wrap gap-3">
                    <Input
                        placeholder="Cari nama makanan..."
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
                                <th className="p-3 font-medium">Porsi</th>
                                <th className="p-3 font-medium">Kalori</th>
                                <th className="p-3 font-medium">P/K/L (g)</th>
                                <th className="p-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {foods.map((food) => (
                                <tr key={food.id} className="border-t align-top">
                                    <td className="p-3">
                                        <div className="font-medium">{food.name}</div>
                                        {food.name_local && <div className="text-muted-foreground text-xs">{food.name_local}</div>}
                                    </td>
                                    <td className="p-3">{food.category ?? '-'}</td>
                                    <td className="p-3">
                                        {food.serving_size} {food.serving_unit}
                                    </td>
                                    <td className="p-3">{food.calories}</td>
                                    <td className="p-3">
                                        {food.protein_g}/{food.carbs_g}/{food.fat_g}
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <FoodFormDialog
                                            categories={categories}
                                            food={food}
                                            trigger={
                                                <Button variant="outline" size="sm" className="mr-2">
                                                    Edit
                                                </Button>
                                            }
                                        />
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => confirm(`Hapus "${food.name}"?`) && router.delete(`/admin/kb/foods/${food.id}`)}
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

function FoodFormDialog({ food, categories, trigger }: { food?: Food; categories: string[]; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, transform, processing, errors } = useForm({
        name: food?.name ?? '',
        name_local: food?.name_local ?? '',
        category: food?.category ?? categories[0] ?? '',
        serving_unit: food?.serving_unit ?? 'gram',
        serving_size: food?.serving_size ?? '100',
        calories: food?.calories ?? '',
        protein_g: food?.protein_g ?? '0',
        carbs_g: food?.carbs_g ?? '0',
        fat_g: food?.fat_g ?? '0',
        fiber_g: food?.fiber_g ?? '',
        sodium_mg: food?.sodium_mg ?? '',
        glycemic_index: food?.glycemic_index?.toString() ?? '',
        tags: food ? JSON.stringify(food.tags ?? [], null, 2) : '[]',
        source: food?.source ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        let parsedTags;
        try {
            parsedTags = data.tags.trim() ? JSON.parse(data.tags) : null;
        } catch {
            alert('Format JSON pada Tags tidak valid.');
            return;
        }

        transform((formData) => ({ ...formData, tags: parsedTags }));

        if (food) {
            patch(`/admin/kb/foods/${food.id}`, { onSuccess: () => setOpen(false) });
        } else {
            post('/admin/kb/foods', { onSuccess: () => setOpen(false) });
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogTitle>{food ? 'Edit Makanan' : 'Tambah Makanan'}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Nama</Label>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            <InputErrorText message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Nama Lokal</Label>
                            <Input value={data.name_local} onChange={(e) => setData('name_local', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Kategori</Label>
                            <Input value={data.category} onChange={(e) => setData('category', e.target.value)} />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="grid gap-2">
                                <Label>Ukuran Porsi</Label>
                                <Input type="number" step="0.01" value={data.serving_size} onChange={(e) => setData('serving_size', e.target.value)} required />
                            </div>
                            <div className="grid gap-2">
                                <Label>Satuan</Label>
                                <Input value={data.serving_unit} onChange={(e) => setData('serving_unit', e.target.value)} required />
                            </div>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div className="grid gap-2">
                            <Label>Kalori</Label>
                            <Input type="number" step="0.01" value={data.calories} onChange={(e) => setData('calories', e.target.value)} required />
                            <InputErrorText message={errors.calories} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Protein (g)</Label>
                            <Input type="number" step="0.01" value={data.protein_g} onChange={(e) => setData('protein_g', e.target.value)} required />
                        </div>
                        <div className="grid gap-2">
                            <Label>Karbo (g)</Label>
                            <Input type="number" step="0.01" value={data.carbs_g} onChange={(e) => setData('carbs_g', e.target.value)} required />
                        </div>
                        <div className="grid gap-2">
                            <Label>Lemak (g)</Label>
                            <Input type="number" step="0.01" value={data.fat_g} onChange={(e) => setData('fat_g', e.target.value)} required />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div className="grid gap-2">
                            <Label>Serat (g)</Label>
                            <Input type="number" step="0.01" value={data.fiber_g} onChange={(e) => setData('fiber_g', e.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Natrium (mg)</Label>
                            <Input type="number" step="0.01" value={data.sodium_mg} onChange={(e) => setData('sodium_mg', e.target.value)} />
                        </div>
                        <div className="col-span-2 grid gap-2">
                            <Label>Indeks Glikemik</Label>
                            <Input type="number" value={data.glycemic_index} onChange={(e) => setData('glycemic_index', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Tags (JSON array)</Label>
                        <Textarea value={data.tags} onChange={(e) => setData('tags', e.target.value)} rows={2} className="font-mono text-xs" />
                        <p className="text-muted-foreground text-xs">Mis. {'["halal", "vegetarian", "low_purine"]'}</p>
                    </div>
                    <div className="grid gap-2">
                        <Label>Sumber</Label>
                        <Input value={data.source} onChange={(e) => setData('source', e.target.value)} placeholder="mis. TKPI" />
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
