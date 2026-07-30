import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type AiReview = {
    summary: string;
    trend: string;
    adjustments: { type: string; detail: string; auto_applicable: boolean }[];
    motivation: string;
};

type WeeklyPlan = {
    id: number;
    week_number: number;
    start_date: string;
    end_date: string;
    ai_summary: string | null;
    ai_review: AiReview | null;
    generated_by: string;
};

export default function WeeklyReview({
    userProgram,
    weeklyPlan,
}: {
    userProgram: { id: number; program: { name: string } };
    weeklyPlan: WeeklyPlan;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: userProgram.program.name, href: `/user-programs/${userProgram.id}` },
        { title: `Minggu ${weeklyPlan.week_number}`, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Weekly Review — Minggu ${weeklyPlan.week_number}`} />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>
                            Minggu {weeklyPlan.week_number} ({weeklyPlan.start_date} &ndash; {weeklyPlan.end_date})
                        </CardTitle>
                        {weeklyPlan.ai_review && <Badge variant="secondary">{weeklyPlan.generated_by}</Badge>}
                    </CardHeader>
                    <CardContent>
                        {!weeklyPlan.ai_review ? (
                            <p className="text-muted-foreground text-sm">
                                Minggu ini belum selesai atau belum direview. Review akan muncul otomatis setelah minggu ini berakhir.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                <p>{weeklyPlan.ai_review.summary}</p>
                                <p>
                                    Tren: <Badge>{weeklyPlan.ai_review.trend}</Badge>
                                </p>

                                {weeklyPlan.ai_review.adjustments.length > 0 && (
                                    <div>
                                        <p className="mb-2 font-medium">Perubahan minggu ini:</p>
                                        <ul className="ml-4 list-disc space-y-1 text-sm">
                                            {weeklyPlan.ai_review.adjustments.map((adj, i) => (
                                                <li key={i}>
                                                    {adj.detail}
                                                    {adj.auto_applicable && (
                                                        <span className="text-muted-foreground ml-2 text-xs">(diterapkan otomatis)</span>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                <p className="bg-muted rounded-md p-3 text-sm italic">{weeklyPlan.ai_review.motivation}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
