import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, Award, Bot, Calendar, CheckCircle2, HeartPulse, TrendingUp, UserCheck } from 'lucide-react';

type Plan = {
    id: number;
    name: string;
    price: string;
    billing_cycle: 'monthly' | 'yearly' | 'lifetime';
    features: string[] | null;
    has_coach_access: boolean;
};

const currency = (value: string) => `Rp ${Number(value).toLocaleString('id-ID')}`;

const cycleLabel: Record<Plan['billing_cycle'], string> = {
    monthly: '/bulan',
    yearly: '/tahun',
    lifetime: ' sekali bayar',
};

const features = [
    {
        icon: Bot,
        title: 'AI Personal Coach',
        description: 'Program harianmu disusun dan disesuaikan otomatis oleh AI berdasarkan profil kesehatan dan progresmu.',
    },
    {
        icon: HeartPulse,
        title: 'Rule Engine Adaptif',
        description: 'Target kalori, pola makan, dan olahraga dihitung dari data medis dasar - tetap aman jalan walau AI sedang tidak tersedia.',
    },
    {
        icon: UserCheck,
        title: 'Didampingi Coach Asli',
        description: 'Upgrade ke Premium untuk dapat Coach kesehatan sungguhan yang memantau progresmu dan bisa diajak diskusi langsung.',
    },
    {
        icon: TrendingUp,
        title: 'Progress Tracking',
        description: 'Catat berat badan, lingkar pinggang, tidur, dan asupan air - lihat Health Score harianmu naik dari waktu ke waktu.',
    },
    {
        icon: Award,
        title: 'Pencapaian & Motivasi',
        description: 'Kumpulkan achievement dari konsistensi checklist harian sampai target berat badan tercapai.',
    },
    {
        icon: Calendar,
        title: 'Rencana 90 Hari',
        description: 'Bukan sekadar meal plan sekali jadi - programmu ditinjau ulang tiap minggu mengikuti progres nyata.',
    },
];

const steps = [
    { title: 'Isi Profil Kesehatan', description: 'Ceritakan kondisi tubuh, gaya hidup, dan tujuanmu lewat wizard onboarding singkat.' },
    { title: 'Dapatkan Program Personal', description: 'AI dan Rule Engine menyusun target kalori, meal plan, dan jadwal olahraga khusus untukmu.' },
    { title: 'Jalani & Catat Progres', description: 'Checklist harian, log berat badan, dan lihat Health Score-mu terus terpantau.' },
    { title: 'Ditinjau Tiap Minggu', description: 'Programmu otomatis disesuaikan tiap minggu - upgrade kapan saja untuk didampingi Coach asli.' },
];

export default function Welcome({ plans }: { plans: Plan[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="AkuSehat - AI Personal Health Coach" />

            <div className="bg-background text-foreground min-h-screen">
                <header className="border-b">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-2 text-lg font-semibold">
                            <HeartPulse className="text-primary h-6 w-6" />
                            AkuSehat
                        </div>
                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Button asChild>
                                    <Link href="/dashboard">Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost">
                                        <Link href="/login">Masuk</Link>
                                    </Button>
                                    <Button asChild>
                                        <Link href="/register">Mulai Gratis</Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="mx-auto max-w-4xl px-6 py-20 text-center">
                        <Badge variant="secondary" className="mb-4">
                            Program Diet & Transformasi 90 Hari
                        </Badge>
                        <h1 className="text-4xl font-bold text-balance sm:text-5xl">
                            Coach kesehatan pribadi yang <span className="text-primary">selalu ada</span>, ditenagai AI
                        </h1>
                        <p className="text-muted-foreground mx-auto mt-6 max-w-2xl text-lg text-balance">
                            AkuSehat menyusun program diet, olahraga, dan kebiasaan harian yang dipersonalisasi untukmu - dipandu Rule Engine
                            medis dan AI, dengan opsi didampingi Coach kesehatan sungguhan.
                        </p>
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                            <Button asChild size="lg">
                                <Link href={auth.user ? '/dashboard' : '/register'}>{auth.user ? 'Buka Dashboard' : 'Mulai Gratis Sekarang'}</Link>
                            </Button>
                            <Button asChild size="lg" variant="outline">
                                <a href="#fitur">Lihat Fitur</a>
                            </Button>
                        </div>
                    </section>

                    <section id="fitur" className="border-t py-20">
                        <div className="mx-auto max-w-6xl px-6">
                            <div className="mx-auto max-w-2xl text-center">
                                <h2 className="text-3xl font-bold">Semua yang kamu butuhkan untuk berubah</h2>
                                <p className="text-muted-foreground mt-3">Bukan aplikasi diet biasa - kombinasi data medis, AI, dan manusia.</p>
                            </div>
                            <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {features.map((feature) => (
                                    <Card key={feature.title}>
                                        <CardHeader>
                                            <feature.icon className="text-primary mb-2 h-8 w-8" />
                                            <CardTitle className="text-base">{feature.title}</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-muted-foreground text-sm">{feature.description}</p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="border-t py-20">
                        <div className="mx-auto max-w-4xl px-6">
                            <div className="mx-auto max-w-2xl text-center">
                                <h2 className="text-3xl font-bold">Cara Kerjanya</h2>
                            </div>
                            <div className="mt-12 grid gap-8 sm:grid-cols-2">
                                {steps.map((step, index) => (
                                    <div key={step.title} className="flex gap-4">
                                        <div className="bg-primary text-primary-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
                                            {index + 1}
                                        </div>
                                        <div>
                                            <h3 className="font-semibold">{step.title}</h3>
                                            <p className="text-muted-foreground mt-1 text-sm">{step.description}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {plans.length > 0 && (
                        <section id="harga" className="border-t py-20">
                            <div className="mx-auto max-w-6xl px-6">
                                <div className="mx-auto max-w-2xl text-center">
                                    <h2 className="text-3xl font-bold">Pilih Paket</h2>
                                    <p className="text-muted-foreground mt-3">Mulai gratis, upgrade kapan saja untuk akses Coach pribadi.</p>
                                </div>
                                <div className="mx-auto mt-12 grid max-w-4xl gap-6 sm:grid-cols-3">
                                    {plans.map((plan) => (
                                        <Card key={plan.id}>
                                            <CardHeader>
                                                <CardTitle className="text-base">{plan.name}</CardTitle>
                                                <p className="text-2xl font-bold">
                                                    {Number(plan.price) === 0 ? 'Gratis' : currency(plan.price)}
                                                    {Number(plan.price) > 0 && (
                                                        <span className="text-muted-foreground text-sm font-normal">
                                                            {cycleLabel[plan.billing_cycle]}
                                                        </span>
                                                    )}
                                                </p>
                                            </CardHeader>
                                            <CardContent>
                                                <ul className="space-y-2 text-sm">
                                                    {(plan.features ?? []).map((feature) => (
                                                        <li key={feature} className="flex items-start gap-2">
                                                            <CheckCircle2 className="text-primary mt-0.5 h-4 w-4 shrink-0" />
                                                            <span className="text-muted-foreground">{feature}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                                <Button asChild className="mt-6 w-full" variant={plan.has_coach_access ? 'default' : 'outline'}>
                                                    <Link href={auth.user ? '/subscription' : '/register'}>Pilih Paket</Link>
                                                </Button>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    <section className="border-t py-20">
                        <div className="mx-auto max-w-2xl px-6 text-center">
                            <Activity className="text-primary mx-auto mb-4 h-10 w-10" />
                            <h2 className="text-3xl font-bold">Siap memulai transformasimu?</h2>
                            <p className="text-muted-foreground mt-3">Isi profil kesehatanmu, dan program pertamamu siap hari ini juga.</p>
                            <Button asChild size="lg" className="mt-8">
                                <Link href={auth.user ? '/dashboard' : '/register'}>{auth.user ? 'Buka Dashboard' : 'Mulai Gratis Sekarang'}</Link>
                            </Button>
                        </div>
                    </section>
                </main>

                <footer className="border-t py-8">
                    <div className="text-muted-foreground mx-auto max-w-6xl px-6 text-center text-sm">
                        &copy; {new Date().getFullYear()} AkuSehat. Semua hak dilindungi.
                    </div>
                </footer>
            </div>
        </>
    );
}
