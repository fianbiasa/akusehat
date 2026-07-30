import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Calendar, Flame, LucideIcon, Trophy, TrendingDown } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Kesehatan', href: '/profile/health' }];

type HealthProfile = {
    date_of_birth: string | null;
    gender: 'male' | 'female' | null;
    height_cm: number | null;
    initial_weight_kg: number | null;
    blood_type: string | null;
    bmi: number | null;
    bmr: number | null;
    tdee: number | null;
} | null;

type LifestyleProfile = {
    activity_level: string;
    sleep_time: string | null;
    wake_time: string | null;
    work_hours_per_day: number | null;
    diet_pattern: string | null;
    sugary_drinks_frequency: string | null;
    smoking_status: string | null;
    alcohol_frequency: string | null;
    exercise_frequency: string | null;
} | null;

type Disease = { id: number; status: string; disease: { id: number; name: string } };
type Allergy = { id: number; allergen: string; severity: string };
type Medication = { id: number; name: string; dosage: string | null; frequency: string | null };
type Measurement = { id: number; measured_at: string; weight_kg: number | null; waist_cm: number | null };
type KbDisease = { id: number; name: string };
type AchievementBadge = { id: number; name: string; description: string | null; icon: string | null; earned_at: string | null };

export default function HealthSettings({
    healthProfile,
    lifestyleProfile,
    diseases,
    allergies,
    medications,
    measurements,
    kbDiseases,
    achievements,
}: {
    healthProfile: HealthProfile;
    lifestyleProfile: LifestyleProfile;
    diseases: Disease[];
    allergies: Allergy[];
    medications: Medication[];
    measurements: Measurement[];
    kbDiseases: KbDisease[];
    achievements: AchievementBadge[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profil Kesehatan" />

            <SettingsLayout>
                <div className="space-y-8">
                    <HealthProfileForm profile={healthProfile} />
                    <LifestyleProfileForm profile={lifestyleProfile} />
                    <DiseasesSection diseases={diseases} kbDiseases={kbDiseases} />
                    <AllergiesSection allergies={allergies} />
                    <MedicationsSection medications={medications} />
                    <MeasurementsSection measurements={measurements} />
                    <AchievementsSection achievements={achievements} />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

const ACHIEVEMENT_ICONS: Record<string, LucideIcon> = {
    Trophy,
    Flame,
    Calendar,
    TrendingDown,
};

function AchievementsSection({ achievements }: { achievements: AchievementBadge[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Pencapaian</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {achievements.map((achievement) => {
                        const Icon = (achievement.icon && ACHIEVEMENT_ICONS[achievement.icon]) || Trophy;
                        const earned = achievement.earned_at !== null;

                        return (
                            <div
                                key={achievement.id}
                                className={`flex flex-col items-center gap-2 rounded-lg border p-4 text-center ${earned ? '' : 'opacity-40 grayscale'}`}
                            >
                                <Icon className={`h-8 w-8 ${earned ? 'text-primary' : 'text-muted-foreground'}`} />
                                <p className="text-sm font-medium">{achievement.name}</p>
                                <p className="text-muted-foreground text-xs">{achievement.description}</p>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}

function HealthProfileForm({ profile }: { profile: HealthProfile }) {
    const { data, setData, patch, processing, errors } = useForm({
        date_of_birth: profile?.date_of_birth ?? '',
        gender: profile?.gender ?? '',
        height_cm: profile?.height_cm ?? '',
        initial_weight_kg: profile?.initial_weight_kg ?? '',
        blood_type: profile?.blood_type ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch('/profile/health', { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <HeadingSmall
                title="Profil Kesehatan"
                description="Data dasar untuk perhitungan BMI, BMR, dan TDEE. Berat badan terkini dilacak lewat 'Riwayat Pengukuran' di bawah — BMI mengikuti angka itu begitu ada."
            />

            {profile?.bmi && (
                <div className="flex gap-6 rounded-lg border p-4 text-sm">
                    <div>
                        <div className="text-muted-foreground text-xs">BMI</div>
                        <div className="font-semibold">{profile.bmi}</div>
                    </div>
                    <div>
                        <div className="text-muted-foreground text-xs">BMR</div>
                        <div className="font-semibold">{profile.bmr} kkal</div>
                    </div>
                    <div>
                        <div className="text-muted-foreground text-xs">TDEE</div>
                        <div className="font-semibold">{profile.tdee} kkal</div>
                    </div>
                </div>
            )}

            <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="date_of_birth">Tanggal Lahir</Label>
                    <Input id="date_of_birth" type="date" value={data.date_of_birth} onChange={(e) => setData('date_of_birth', e.target.value)} />
                    <InputError message={errors.date_of_birth} />
                </div>
                <div className="grid gap-2">
                    <Label>Jenis Kelamin</Label>
                    <Select value={data.gender} onValueChange={(v) => setData('gender', v as 'male' | 'female')}>
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="male">Laki-laki</SelectItem>
                            <SelectItem value="female">Perempuan</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="height_cm">Tinggi Badan (cm)</Label>
                    <Input id="height_cm" type="number" value={data.height_cm} onChange={(e) => setData('height_cm', e.target.value)} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="initial_weight_kg">Berat Badan Awal (kg)</Label>
                    <Input
                        id="initial_weight_kg"
                        type="number"
                        value={data.initial_weight_kg}
                        onChange={(e) => setData('initial_weight_kg', e.target.value)}
                    />
                </div>
                <div className="col-span-2">
                    <Button type="submit" disabled={processing}>
                        Simpan
                    </Button>
                </div>
            </form>
        </div>
    );
}

function LifestyleProfileForm({ profile }: { profile: LifestyleProfile }) {
    const { data, setData, patch, processing } = useForm({
        activity_level: profile?.activity_level ?? 'sedentary',
        sleep_time: profile?.sleep_time ?? '',
        wake_time: profile?.wake_time ?? '',
        smoking_status: profile?.smoking_status ?? '',
        alcohol_frequency: profile?.alcohol_frequency ?? '',
        exercise_frequency: profile?.exercise_frequency ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch('/profile/lifestyle', { preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <HeadingSmall title="Gaya Hidup" description="Aktivitas dan kebiasaan sehari-hari" />

            <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label>Tingkat Aktivitas</Label>
                    <Select value={data.activity_level} onValueChange={(v) => setData('activity_level', v)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="sedentary">Duduk terus</SelectItem>
                            <SelectItem value="light">Ringan</SelectItem>
                            <SelectItem value="moderate">Sedang</SelectItem>
                            <SelectItem value="heavy">Berat</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-2">
                    <Label>Merokok</Label>
                    <Select value={data.smoking_status} onValueChange={(v) => setData('smoking_status', v)}>
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="never">Tidak pernah</SelectItem>
                            <SelectItem value="former">Sudah berhenti</SelectItem>
                            <SelectItem value="current">Aktif merokok</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="sleep_time">Jam Tidur</Label>
                    <Input id="sleep_time" type="time" value={data.sleep_time} onChange={(e) => setData('sleep_time', e.target.value)} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="wake_time">Jam Bangun</Label>
                    <Input id="wake_time" type="time" value={data.wake_time} onChange={(e) => setData('wake_time', e.target.value)} />
                </div>
                <div className="col-span-2">
                    <Button type="submit" disabled={processing}>
                        Simpan
                    </Button>
                </div>
            </form>
        </div>
    );
}

function DiseasesSection({ diseases, kbDiseases }: { diseases: Disease[]; kbDiseases: KbDisease[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ kb_disease_id: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/profile/diseases', { preserveScroll: true, onSuccess: () => (setOpen(false), reset()) });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">Kondisi Kesehatan</CardTitle>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm">+ Tambah</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Tambah Kondisi Kesehatan</DialogTitle>
                        <form onSubmit={submit} className="space-y-4">
                            <Select value={data.kb_disease_id} onValueChange={(v) => setData('kb_disease_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih kondisi" />
                                </SelectTrigger>
                                <SelectContent>
                                    {kbDiseases.map((d) => (
                                        <SelectItem key={d.id} value={String(d.id)}>
                                            {d.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2">
                {diseases.length === 0 && <p className="text-muted-foreground text-sm">Belum ada kondisi kesehatan tercatat.</p>}
                {diseases.map((d) => (
                    <RemovableBadge key={d.id} label={d.disease.name} url={`/profile/diseases/${d.id}`} />
                ))}
            </CardContent>
        </Card>
    );
}

function AllergiesSection({ allergies }: { allergies: Allergy[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ allergen: '', severity: 'mild' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/profile/allergies', { preserveScroll: true, onSuccess: () => (setOpen(false), reset()) });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">Alergi</CardTitle>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm">+ Tambah</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Tambah Alergi</DialogTitle>
                        <form onSubmit={submit} className="space-y-4">
                            <Input placeholder="Alergen" value={data.allergen} onChange={(e) => setData('allergen', e.target.value)} required />
                            <Select value={data.severity} onValueChange={(v) => setData('severity', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="mild">Ringan</SelectItem>
                                    <SelectItem value="moderate">Sedang</SelectItem>
                                    <SelectItem value="severe">Berat</SelectItem>
                                </SelectContent>
                            </Select>
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
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2">
                {allergies.length === 0 && <p className="text-muted-foreground text-sm">Belum ada alergi tercatat.</p>}
                {allergies.map((a) => (
                    <RemovableBadge key={a.id} label={`${a.allergen} (${a.severity})`} url={`/profile/allergies/${a.id}`} />
                ))}
            </CardContent>
        </Card>
    );
}

function MedicationsSection({ medications }: { medications: Medication[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ name: '', dosage: '', frequency: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/profile/medications', { preserveScroll: true, onSuccess: () => (setOpen(false), reset()) });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">Obat-obatan Rutin</CardTitle>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm">+ Tambah</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Tambah Obat</DialogTitle>
                        <form onSubmit={submit} className="space-y-4">
                            <Input placeholder="Nama obat" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            <Input placeholder="Dosis" value={data.dosage} onChange={(e) => setData('dosage', e.target.value)} />
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
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2">
                {medications.length === 0 && <p className="text-muted-foreground text-sm">Belum ada obat rutin tercatat.</p>}
                {medications.map((m) => (
                    <RemovableBadge key={m.id} label={[m.name, m.dosage].filter(Boolean).join(' - ')} url={`/profile/medications/${m.id}`} />
                ))}
            </CardContent>
        </Card>
    );
}

function MeasurementsSection({ measurements }: { measurements: Measurement[] }) {
    const { data, setData, post, processing, reset } = useForm({
        measured_at: new Date().toISOString().slice(0, 10),
        weight_kg: '',
        waist_cm: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/profile/measurements', { preserveScroll: true, onSuccess: () => reset('weight_kg', 'waist_cm') });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Riwayat Pengukuran</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <form onSubmit={submit} className="flex items-end gap-2">
                    <div className="grid gap-1">
                        <Label className="text-xs">Tanggal</Label>
                        <Input type="date" value={data.measured_at} onChange={(e) => setData('measured_at', e.target.value)} />
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Berat (kg)</Label>
                        <Input type="number" value={data.weight_kg} onChange={(e) => setData('weight_kg', e.target.value)} className="w-24" />
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Pinggang (cm)</Label>
                        <Input type="number" value={data.waist_cm} onChange={(e) => setData('waist_cm', e.target.value)} className="w-24" />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Catat
                    </Button>
                </form>

                <div className="space-y-1 text-sm">
                    {measurements.map((m) => (
                        <div key={m.id} className="flex justify-between border-b py-1">
                            <span>{m.measured_at}</span>
                            <span className="text-muted-foreground">
                                {m.weight_kg ? `${m.weight_kg} kg` : '-'} {m.waist_cm ? `· ${m.waist_cm} cm` : ''}
                            </span>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function RemovableBadge({ label, url }: { label: string; url: string }) {
    const { delete: destroy, processing } = useForm();

    return (
        <Badge variant="secondary" className="gap-2 py-1.5">
            {label}
            <button
                type="button"
                disabled={processing}
                onClick={() => destroy(url, { preserveScroll: true })}
                className="text-muted-foreground hover:text-destructive"
            >
                ×
            </button>
        </Badge>
    );
}
