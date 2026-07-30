import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type ActiveProgram = {
    id: number;
    program_name: string;
    start_date: string;
    end_date: string | null;
    day_number: number;
    duration_days: number;
};

type ChecklistItem = {
    id: number;
    label: string;
    is_checked: boolean;
};

type WeeklyReview = {
    id: number;
    user_program_id: number;
    week_number: number;
    ai_summary: string | null;
    ai_review: { adjustments?: { type: string; detail: string }[] } | null;
};

type HealthScore = {
    score: number;
    scored_at: string;
    delta: number | null;
};

export default function Dashboard({
    selectedProgramId,
    activePrograms,
    checklist,
    latestMeasurement,
    healthScore,
    weeklyReview,
}: {
    selectedProgramId: number | null;
    activePrograms: ActiveProgram[];
    checklist: ChecklistItem[];
    latestMeasurement: { weight_kg: number; measured_at: string } | null;
    healthScore: HealthScore | null;
    weeklyReview: WeeklyReview | null;
}) {
    const primaryProgram = activePrograms.find((p) => p.id === selectedProgramId) ?? activePrograms[0];

    const toggleChecklist = (item: ChecklistItem) => {
        router.patch(`/checklist-items/${item.id}`, { is_checked: !item.is_checked }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                {activePrograms.length > 1 && (
                    <div className="flex gap-2">
                        {activePrograms.map((p) => (
                            <Button
                                key={p.id}
                                size="sm"
                                variant={p.id === primaryProgram?.id ? 'default' : 'outline'}
                                onClick={() => router.get('/dashboard', { program: p.id }, { preserveState: true })}
                            >
                                {p.program_name}
                            </Button>
                        ))}
                    </div>
                )}

                {!primaryProgram && (
                    <Card>
                        <CardContent className="text-muted-foreground py-8 text-center">
                            Belum ada program aktif. Program akan dibuat otomatis setelah kamu menyelesaikan onboarding.
                        </CardContent>
                    </Card>
                )}

                {primaryProgram && (
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Health Score</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {healthScore ? (
                                    <>
                                        <p className="text-3xl font-bold">{healthScore.score} / 100</p>
                                        {healthScore.delta !== null && (
                                            <p className={`text-sm ${healthScore.delta >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                {healthScore.delta >= 0 ? '▲' : '▼'} {Math.abs(healthScore.delta)} dari kemarin
                                            </p>
                                        )}
                                        <Button asChild size="sm" variant="outline" className="mt-3">
                                            <Link href="/progress">Lihat Detail</Link>
                                        </Button>
                                    </>
                                ) : (
                                    <p className="text-muted-foreground text-sm">Skor akan muncul setelah dihitung otomatis besok.</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{primaryProgram.program_name}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-muted-foreground mb-2 text-sm">
                                    Hari ke-{primaryProgram.day_number} dari {primaryProgram.duration_days}
                                </p>
                                <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                                    <div
                                        className="bg-primary h-full rounded-full"
                                        style={{
                                            width: `${Math.min(100, Math.round((primaryProgram.day_number / primaryProgram.duration_days) * 100))}%`,
                                        }}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {primaryProgram && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Checklist Hari Ini</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {checklist.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Rencana hari ini sedang disiapkan. Muat ulang halaman sebentar lagi.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {checklist.map((item) => (
                                        <li key={item.id} className="flex items-center gap-3">
                                            <Checkbox checked={item.is_checked} onCheckedChange={() => toggleChecklist(item)} />
                                            <span className={item.is_checked ? 'text-muted-foreground line-through' : ''}>{item.label}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}

                {primaryProgram && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Berat Badan</CardTitle>
                            <Button asChild size="sm" variant="outline">
                                <Link href="/progress">Log Berat +</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {latestMeasurement ? (
                                <p className="text-2xl font-semibold">
                                    {latestMeasurement.weight_kg} kg
                                    <span className="text-muted-foreground ml-2 text-sm font-normal">per {latestMeasurement.measured_at}</span>
                                </p>
                            ) : (
                                <p className="text-muted-foreground text-sm">Belum ada data berat badan.</p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {weeklyReview && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                Weekly Review <Badge>baru</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-2 text-sm">{weeklyReview.ai_summary}</p>
                            <Button asChild size="sm" variant="outline">
                                <Link href={`/user-programs/${weeklyReview.user_program_id}/weekly-plans/${weeklyReview.week_number}`}>
                                    Lihat Detail
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
