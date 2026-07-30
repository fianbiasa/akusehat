import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Progress', href: '/progress' }];

type WeightLog = { logged_at: string; weight_kg: number };
type WaistLog = { logged_at: string; waist_cm: number };
type HealthScoreEntry = { scored_at: string; score: number; explanation: string | null };
type Photo = { id: number; logged_at: string; angle: 'front' | 'side' | 'back'; is_private: boolean; url: string };
type ConsistencyDay = { date: string; completed: boolean };

type Range = 'week' | 'month' | '90d';

const RANGE_DAYS: Record<Range, number> = { week: 7, month: 30, '90d': 90 };

function filterByRange<T extends { logged_at?: string; scored_at?: string }>(items: T[], range: Range): T[] {
    const days = RANGE_DAYS[range];
    return items.slice(-days);
}

export default function ProgressIndex({
    weightLogs,
    waistLogs,
    healthScores,
    sleepAvg7d,
    waterAvg7d,
    waterTargetMl,
    photos,
    checklistConsistency,
}: {
    weightLogs: WeightLog[];
    waistLogs: WaistLog[];
    healthScores: HealthScoreEntry[];
    sleepAvg7d: number;
    waterAvg7d: number;
    waterTargetMl: number;
    photos: Photo[];
    checklistConsistency: ConsistencyDay[];
}) {
    const [range, setRange] = useState<Range>('month');
    const [selectedScore, setSelectedScore] = useState<HealthScoreEntry | null>(null);

    const visibleScores = useMemo(() => filterByRange(healthScores, range), [healthScores, range]);
    const visibleWeights = useMemo(() => filterByRange(weightLogs, range), [weightLogs, range]);
    const visibleWaists = useMemo(() => filterByRange(waistLogs, range), [waistLogs, range]);

    const latestScore = healthScores.at(-1);
    const previousScore = healthScores.at(-2);
    const scoreDelta = latestScore && previousScore ? Math.round((latestScore.score - previousScore.score) * 10) / 10 : null;

    const dayLabels = ['M', 'S', 'S', 'R', 'K', 'J', 'S'];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Progress" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Progress</h1>
                    <div className="flex gap-2">
                        {(['week', 'month', '90d'] as Range[]).map((r) => (
                            <Button key={r} size="sm" variant={range === r ? 'default' : 'outline'} onClick={() => setRange(r)}>
                                {r === 'week' ? 'Minggu' : r === 'month' ? 'Bulan' : '90 Hari'}
                            </Button>
                        ))}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            Health Score Trend
                            {latestScore && (
                                <span className="text-2xl font-bold">
                                    {latestScore.score}
                                    {scoreDelta !== null && (
                                        <span className={`ml-2 text-sm font-normal ${scoreDelta >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                            {scoreDelta >= 0 ? '▲' : '▼'} {Math.abs(scoreDelta)}
                                        </span>
                                    )}
                                </span>
                            )}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {visibleScores.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Belum ada Health Score. Skor dihitung otomatis setiap hari.</p>
                        ) : (
                            <ResponsiveContainer width="100%" height={200}>
                                <LineChart
                                    data={visibleScores}
                                    onClick={(e: unknown) => {
                                        const payload = (e as { activePayload?: { payload: HealthScoreEntry }[] } | null)?.activePayload;
                                        if (payload?.[0]) setSelectedScore(payload[0].payload);
                                    }}
                                >
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="scored_at" tick={{ fontSize: 11 }} />
                                    <YAxis domain={[0, 100]} tick={{ fontSize: 11 }} />
                                    <Tooltip />
                                    <Line type="monotone" dataKey="score" stroke="var(--primary)" strokeWidth={2} dot={{ r: 3, cursor: 'pointer' }} />
                                </LineChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>

                <Dialog open={!!selectedScore} onOpenChange={(open) => !open && setSelectedScore(null)}>
                    <DialogContent>
                        <DialogTitle>Health Score — {selectedScore?.scored_at}</DialogTitle>
                        <p className="text-2xl font-bold">{selectedScore?.score}</p>
                        <p className="text-muted-foreground text-sm">{selectedScore?.explanation ?? 'Belum ada penjelasan untuk skor ini.'}</p>
                    </DialogContent>
                </Dialog>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">Berat Badan</CardTitle>
                            <QuickLogDialog metric="weight" />
                        </CardHeader>
                        <CardContent>
                            {visibleWeights.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Belum ada data.</p>
                            ) : (
                                <>
                                    <p className="mb-2 text-sm font-medium">
                                        {visibleWeights[0].weight_kg} → {visibleWeights.at(-1)?.weight_kg} kg
                                    </p>
                                    <ResponsiveContainer width="100%" height={100}>
                                        <LineChart data={visibleWeights}>
                                            <Line type="monotone" dataKey="weight_kg" stroke="var(--primary)" strokeWidth={2} dot={false} />
                                            <XAxis dataKey="logged_at" hide />
                                            <YAxis hide domain={['dataMin - 1', 'dataMax + 1']} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">Lingkar Pinggang</CardTitle>
                            <QuickLogDialog metric="waist" />
                        </CardHeader>
                        <CardContent>
                            {visibleWaists.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Belum ada data.</p>
                            ) : (
                                <>
                                    <p className="mb-2 text-sm font-medium">
                                        {visibleWaists[0].waist_cm} → {visibleWaists.at(-1)?.waist_cm} cm
                                    </p>
                                    <ResponsiveContainer width="100%" height={100}>
                                        <LineChart data={visibleWaists}>
                                            <Line type="monotone" dataKey="waist_cm" stroke="var(--primary)" strokeWidth={2} dot={false} />
                                            <XAxis dataKey="logged_at" hide />
                                            <YAxis hide domain={['dataMin - 1', 'dataMax + 1']} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">Tidur (avg 7 hari)</CardTitle>
                            <QuickLogDialog metric="sleep" />
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">{sleepAvg7d} jam</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">Air Minum (avg/hari)</CardTitle>
                            <QuickLogDialog metric="water" />
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {waterAvg7d.toLocaleString('id-ID')} / {waterTargetMl.toLocaleString('id-ID')} ml
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Foto Progress</CardTitle>
                        <UploadPhotoDialog />
                    </CardHeader>
                    <CardContent>
                        {photos.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Belum ada foto progress.</p>
                        ) : (
                            <div className="flex flex-wrap gap-4">
                                {photos.map((photo) => (
                                    <PhotoCard key={photo.id} photo={photo} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Konsistensi Checklist</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex gap-2">
                            {checklistConsistency.map((day, i) => (
                                <div key={day.date} className="flex flex-col items-center gap-1">
                                    <span className="text-muted-foreground text-xs">
                                        {dayLabels[new Date(day.date).getDay()] ?? dayLabels[i % 7]}
                                    </span>
                                    <span
                                        className={`flex size-6 items-center justify-center rounded-full text-xs ${
                                            day.completed ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        {day.completed ? '✓' : '✗'}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function QuickLogDialog({ metric }: { metric: 'weight' | 'waist' | 'sleep' | 'water' }) {
    const [open, setOpen] = useState(false);

    const config = {
        weight: { title: 'Log Berat Badan', url: '/progress/weight', field: 'weight_kg', label: 'Berat (kg)', step: '0.1' },
        waist: { title: 'Log Lingkar Pinggang', url: '/progress/waist', field: 'waist_cm', label: 'Lingkar Pinggang (cm)', step: '0.1' },
        sleep: { title: 'Log Tidur', url: '/progress/sleep', field: 'sleep_hours', label: 'Jam Tidur', step: '0.5' },
        water: { title: 'Log Air Minum', url: '/progress/water', field: 'amount_ml', label: 'Jumlah (ml)', step: '50' },
    }[metric];

    const { data, setData, post, processing, reset, errors } = useForm({ [config.field]: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(config.url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost">
                    +
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{config.title}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label htmlFor={config.field}>{config.label}</Label>
                        <Input
                            id={config.field}
                            type="number"
                            step={config.step}
                            value={data[config.field]}
                            onChange={(e) => setData(config.field, e.target.value)}
                        />
                        {errors[config.field as keyof typeof errors] && (
                            <p className="text-destructive mt-1 text-sm">{errors[config.field as keyof typeof errors]}</p>
                        )}
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
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

function UploadPhotoDialog() {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset, errors } = useForm<{ angle: 'front' | 'side' | 'back'; photo: File | null }>({
        angle: 'front',
        photo: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/progress/photos', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    +
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Tambah Foto Progress</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label htmlFor="angle">Sudut</Label>
                        <Select value={data.angle} onValueChange={(v) => setData('angle', v as 'front' | 'side' | 'back')}>
                            <SelectTrigger id="angle">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="front">Depan</SelectItem>
                                <SelectItem value="side">Samping</SelectItem>
                                <SelectItem value="back">Belakang</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label htmlFor="photo">Foto</Label>
                        <Input id="photo" type="file" accept="image/*" onChange={(e) => setData('photo', e.target.files?.[0] ?? null)} />
                        {errors.photo && <p className="text-destructive mt-1 text-sm">{errors.photo}</p>}
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing || !data.photo}>
                            Unggah
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PhotoCard({ photo }: { photo: Photo }) {
    const toggleShare = () => {
        router.patch(`/progress/photos/${photo.id}`, { is_private: !photo.is_private }, { preserveScroll: true });
    };

    const remove = () => {
        router.delete(`/progress/photos/${photo.id}`, { preserveScroll: true });
    };

    return (
        <div className="w-32 space-y-2">
            <img src={photo.url} alt={`Progress ${photo.angle} ${photo.logged_at}`} className="aspect-square w-32 rounded-md object-cover" />
            <p className="text-muted-foreground text-center text-xs">{photo.logged_at}</p>
            <div className="flex items-center justify-center gap-1 text-xs">
                <Checkbox checked={!photo.is_private} onCheckedChange={toggleShare} id={`share-${photo.id}`} />
                <Label htmlFor={`share-${photo.id}`} className="cursor-pointer text-xs font-normal">
                    Bagikan ke Coach
                </Label>
            </div>
            {!photo.is_private && (
                <Badge variant="secondary" className="w-full justify-center text-xs">
                    Dibagikan
                </Badge>
            )}
            <Button size="sm" variant="ghost" className="text-destructive w-full" onClick={remove}>
                Hapus
            </Button>
        </div>
    );
}
