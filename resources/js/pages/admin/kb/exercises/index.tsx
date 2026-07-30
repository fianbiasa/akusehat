import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Knowledge Base: Latihan', href: '/admin/kb/exercises' }];

const difficulties = ['beginner', 'intermediate', 'advanced'] as const;

type Exercise = {
    id: number;
    name: string;
    category: string | null;
    target_muscle: string | null;
    met_value: string | null;
    difficulty: (typeof difficulties)[number];
    equipment: string | null;
    instructions: string | null;
    video_url: string | null;
    contraindications: string[] | null;
};

export default function AdminKbExercisesIndex({
    exercises,
    categories,
    filters,
}: {
    exercises: Exercise[];
    categories: string[];
    filters: { category?: string; search?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (next: Partial<{ category: string; search: string }>) => {
        router.get(
            '/admin/kb/exercises',
            { ...filters, ...next, category: next.category === 'all' ? undefined : (next.category ?? filters.category) },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Knowledge Base: Latihan" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <HeadingSmall title="Knowledge Base: Latihan" description="Basis data latihan/olahraga untuk rencana workout" />
                    <ExerciseFormDialog categories={categories} trigger={<Button>+ Tambah Latihan</Button>} />
                </div>

                <div className="flex flex-wrap gap-3">
                    <Input
                        placeholder="Cari nama latihan..."
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
                                <th className="p-3 font-medium">Target Otot</th>
                                <th className="p-3 font-medium">MET</th>
                                <th className="p-3 font-medium">Kesulitan</th>
                                <th className="p-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {exercises.map((exercise) => (
                                <tr key={exercise.id} className="border-t align-top">
                                    <td className="p-3 font-medium">{exercise.name}</td>
                                    <td className="p-3">{exercise.category ?? '-'}</td>
                                    <td className="p-3">{exercise.target_muscle ?? '-'}</td>
                                    <td className="p-3">{exercise.met_value ?? '-'}</td>
                                    <td className="p-3">
                                        <Badge variant="outline">{exercise.difficulty}</Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <ExerciseFormDialog
                                            categories={categories}
                                            exercise={exercise}
                                            trigger={
                                                <Button variant="outline" size="sm" className="mr-2">
                                                    Edit
                                                </Button>
                                            }
                                        />
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() =>
                                                confirm(`Hapus "${exercise.name}"?`) && router.delete(`/admin/kb/exercises/${exercise.id}`)
                                            }
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

function ExerciseFormDialog({ exercise, categories, trigger }: { exercise?: Exercise; categories: string[]; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, transform, processing, errors } = useForm({
        name: exercise?.name ?? '',
        category: exercise?.category ?? categories[0] ?? '',
        target_muscle: exercise?.target_muscle ?? '',
        met_value: exercise?.met_value ?? '',
        difficulty: exercise?.difficulty ?? 'beginner',
        equipment: exercise?.equipment ?? '',
        instructions: exercise?.instructions ?? '',
        video_url: exercise?.video_url ?? '',
        contraindications: exercise ? JSON.stringify(exercise.contraindications ?? [], null, 2) : '[]',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        let parsed;
        try {
            parsed = data.contraindications.trim() ? JSON.parse(data.contraindications) : null;
        } catch {
            alert('Format JSON pada Contraindications tidak valid.');
            return;
        }

        transform((formData) => ({ ...formData, contraindications: parsed }));

        if (exercise) {
            patch(`/admin/kb/exercises/${exercise.id}`, { onSuccess: () => setOpen(false) });
        } else {
            post('/admin/kb/exercises', { onSuccess: () => setOpen(false) });
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogTitle>{exercise ? 'Edit Latihan' : 'Tambah Latihan'}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Nama</Label>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            <InputErrorText message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Kategori</Label>
                            <Input value={data.category} onChange={(e) => setData('category', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Target Otot</Label>
                            <Input value={data.target_muscle} onChange={(e) => setData('target_muscle', e.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Nilai MET</Label>
                            <Input type="number" step="0.01" value={data.met_value} onChange={(e) => setData('met_value', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Kesulitan</Label>
                            <Select value={data.difficulty} onValueChange={(v) => setData('difficulty', v as typeof data.difficulty)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {difficulties.map((d) => (
                                        <SelectItem key={d} value={d}>
                                            {d}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Peralatan</Label>
                            <Input value={data.equipment} onChange={(e) => setData('equipment', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Instruksi</Label>
                        <Textarea value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} rows={3} />
                    </div>
                    <div className="grid gap-2">
                        <Label>URL Video</Label>
                        <Input value={data.video_url} onChange={(e) => setData('video_url', e.target.value)} placeholder="https://..." />
                        <InputErrorText message={errors.video_url} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Kontraindikasi (JSON array slug penyakit)</Label>
                        <Textarea
                            value={data.contraindications}
                            onChange={(e) => setData('contraindications', e.target.value)}
                            rows={2}
                            className="font-mono text-xs"
                        />
                        <p className="text-muted-foreground text-xs">Mis. {'["hipertensi", "jantung"]'}</p>
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
