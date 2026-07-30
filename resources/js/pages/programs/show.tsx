import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Goal = {
    id: number;
    goal_type: string;
    target_weight_kg: number | null;
    target_waist_cm: number | null;
    target_date: string | null;
    notes: string | null;
};

type UserProgram = {
    id: number;
    status: string;
    start_date: string;
    end_date: string | null;
    created_by: string;
    program: { name: string; category: string; default_duration_days: number };
    goals: Goal[];
    coach: { id: number; name: string } | null;
};

type WeeklyPlan = {
    id: number;
    week_number: number;
    start_date: string;
    end_date: string;
    ai_summary: string | null;
    viewed_at: string | null;
};

type MealPlan = {
    id: number;
    meal_type: string;
    total_calories: number | null;
    source: string;
    items: { id: number; portion: number; calories: number | null; custom_name: string | null; kb_food: { name_local: string } | null }[];
};

type WorkoutPlan = {
    id: number;
    workout_type: string | null;
    duration_minutes: number | null;
    intensity: string;
    source: string;
    items: { id: number; sets: number | null; reps: number | null; custom_name: string | null; kb_exercise: { name: string } | null }[];
};

type MyReview = { rating: number; comment: string | null } | null;

export default function ProgramShow({
    userProgram,
    weeklyPlans,
    mealPlans,
    workoutPlans,
    generateStatus,
    myReview,
}: {
    userProgram: UserProgram;
    weeklyPlans: WeeklyPlan[];
    mealPlans: MealPlan[];
    workoutPlans: WorkoutPlan[];
    generateStatus: string;
    myReview: MyReview;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: userProgram.program.name, href: `/user-programs/${userProgram.id}` },
    ];

    const goal = userProgram.goals[0] ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={userProgram.program.name} />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>{userProgram.program.name}</CardTitle>
                        <Badge variant={userProgram.status === 'active' ? 'default' : 'secondary'}>{userProgram.status}</Badge>
                    </CardHeader>
                    <CardContent className="text-muted-foreground space-y-2 text-sm">
                        <p>
                            {userProgram.start_date} &rarr; {userProgram.end_date ?? '-'}
                        </p>
                        {userProgram.coach && <p>Coach: {userProgram.coach.name}</p>}
                        {goal && (
                            <p>
                                Target: {goal.goal_type}
                                {goal.target_weight_kg ? ` — ${goal.target_weight_kg} kg` : ''}
                                {goal.target_date ? ` sebelum ${goal.target_date}` : ''}
                            </p>
                        )}
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => router.post(`/user-programs/${userProgram.id}/regenerate`)}
                            disabled={generateStatus === 'pending'}
                        >
                            {generateStatus === 'pending' ? 'Sedang membuat ulang...' : 'Buat Ulang Rencana Hari Ini'}
                        </Button>
                    </CardContent>
                </Card>

                {generateStatus === 'pending' && mealPlans.length === 0 && workoutPlans.length === 0 && (
                    <Card>
                        <CardContent className="text-muted-foreground py-6 text-center text-sm">
                            Rencana hari ini sedang dibuat. Halaman ini akan menampilkan hasilnya setelah selesai.
                        </CardContent>
                    </Card>
                )}

                {mealPlans.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Rencana Makan Hari Ini</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {mealPlans.map((meal) => (
                                <div key={meal.id}>
                                    <p className="font-medium capitalize">
                                        {meal.meal_type} {meal.total_calories ? `(${meal.total_calories} kal)` : ''}
                                        {meal.source !== 'ai' && <span className="text-muted-foreground ml-2 text-xs">[{meal.source}]</span>}
                                    </p>
                                    <ul className="text-muted-foreground ml-4 list-disc text-sm">
                                        {meal.items.map((item) => (
                                            <li key={item.id}>{item.kb_food?.name_local ?? item.custom_name}</li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {workoutPlans.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Rencana Olahraga Hari Ini</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {workoutPlans.map((workout) => (
                                <div key={workout.id}>
                                    <p className="font-medium capitalize">
                                        {workout.workout_type} — {workout.duration_minutes} menit ({workout.intensity})
                                        {workout.source !== 'ai' && <span className="text-muted-foreground ml-2 text-xs">[{workout.source}]</span>}
                                    </p>
                                    <ul className="text-muted-foreground ml-4 list-disc text-sm">
                                        {workout.items.map((item) => (
                                            <li key={item.id}>
                                                {item.kb_exercise?.name ?? item.custom_name}
                                                {item.sets ? ` — ${item.sets}x${item.reps}` : ''}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Rencana Mingguan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {weeklyPlans.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Belum ada rencana mingguan.</p>
                        ) : (
                            <ul className="space-y-2">
                                {weeklyPlans.map((week) => (
                                    <li key={week.id} className="flex items-center justify-between">
                                        <span>
                                            Minggu {week.week_number} ({week.start_date} &ndash; {week.end_date})
                                        </span>
                                        <Button asChild size="sm" variant="outline">
                                            <Link href={`/user-programs/${userProgram.id}/weekly-plans/${week.week_number}`}>
                                                {week.ai_summary ? 'Lihat Review' : 'Belum ada review'}
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {userProgram.coach && <ReviewCard userProgramId={userProgram.id} coachName={userProgram.coach.name} myReview={myReview} />}
            </div>
        </AppLayout>
    );
}

function ReviewCard({ userProgramId, coachName, myReview }: { userProgramId: number; coachName: string; myReview: MyReview }) {
    const [rating, setRating] = useState(myReview?.rating ?? 0);
    const { data, setData, post, processing } = useForm({ rating: myReview?.rating ?? 0, comment: myReview?.comment ?? '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/user-programs/${userProgramId}/review`, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Nilai Coach {coachName}</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-3">
                    <div className="flex gap-1">
                        {[1, 2, 3, 4, 5].map((star) => (
                            <button
                                key={star}
                                type="button"
                                onClick={() => {
                                    setRating(star);
                                    setData('rating', star);
                                }}
                                className={`text-2xl ${star <= rating ? 'text-yellow-500' : 'text-muted-foreground'}`}
                                aria-label={`${star} bintang`}
                            >
                                ★
                            </button>
                        ))}
                    </div>
                    <Textarea
                        value={data.comment}
                        onChange={(e) => setData('comment', e.target.value)}
                        placeholder="Komentar (opsional)"
                        rows={2}
                        maxLength={500}
                    />
                    <Button type="submit" size="sm" disabled={processing || rating === 0}>
                        {myReview ? 'Perbarui Ulasan' : 'Kirim Ulasan'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
