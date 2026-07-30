import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Advisory = { summary: string; recommendation_notes: { recommendation_index: number; rationale: string }[]; manual_checks: string[] } | null;
type PendingRecommendation = { id: number; type: string; content: { detail?: string; type?: string }; rationale: string | null };
type Note = { id: number; note: string; is_visible_to_member: boolean; created_at: string };

export default function MemberDetail({
    member,
    activeProgram,
    advisory,
    pendingRecommendations,
    notes,
}: {
    member: { id: number; name: string; age: number | null; bmi: string | null; diseases: string[]; activity_level: string | null };
    activeProgram: { id: number; program_name: string } | null;
    advisory: Advisory;
    pendingRecommendations: PendingRecommendation[];
    notes: Note[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard Coach', href: '/coach/dashboard' },
        { title: member.name, href: `/coach/members/${member.id}` },
    ];

    const approve = (id: number) => router.post(`/coach/recommendations/${id}/approve`, {}, { preserveScroll: true });
    const reject = (id: number) => router.post(`/coach/recommendations/${id}/reject`, {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={member.name} />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{member.name}</h1>
                    <div className="flex gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href={`/progress?user_id=${member.id}`}>Progress</Link>
                        </Button>
                        {activeProgram && (
                            <Button asChild size="sm" variant="outline">
                                <Link href={`/user-programs/${activeProgram.id}`}>Program</Link>
                            </Button>
                        )}
                        <Button asChild size="sm" variant="outline">
                            <Link href={`/coach/members/${member.id}/conversation`}>Chat</Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Ringkasan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-muted-foreground text-sm">
                            {member.bmi ? `BMI ${member.bmi}` : 'BMI belum tersedia'}
                            {member.diseases.length > 0 && ` · ${member.diseases.join(', ')}`}
                            {member.activity_level && ` · Aktivitas ${member.activity_level}`}
                        </p>
                        {activeProgram && <p className="mt-1 text-sm">Program: {activeProgram.program_name}</p>}
                    </CardContent>
                </Card>

                {(advisory || pendingRecommendations.length > 0) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>🤖 AI Advisory (untuk Coach)</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {advisory && <p className="text-sm">{advisory.summary}</p>}

                            {pendingRecommendations.length > 0 && (
                                <div>
                                    <p className="mb-2 text-sm font-medium">Rekomendasi menunggu persetujuan:</p>
                                    <ul className="space-y-2">
                                        {pendingRecommendations.map((rec) => (
                                            <li key={rec.id} className="flex items-center justify-between gap-4 rounded-md border p-2">
                                                <span className="text-sm">{rec.content.detail ?? rec.rationale ?? rec.type}</span>
                                                <div className="flex gap-2">
                                                    <Button size="sm" onClick={() => approve(rec.id)}>
                                                        Setujui
                                                    </Button>
                                                    <Button size="sm" variant="outline" onClick={() => reject(rec.id)}>
                                                        Tolak
                                                    </Button>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Catatan Pribadi (tidak terlihat member)</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <NoteForm memberId={member.id} />
                        {notes.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Belum ada catatan.</p>
                        ) : (
                            <ul className="space-y-2">
                                {notes.map((note) => (
                                    <li key={note.id} className="rounded-md border p-2 text-sm">
                                        <p>{note.note}</p>
                                        <div className="text-muted-foreground mt-1 flex items-center justify-between text-xs">
                                            <span>{note.created_at}</span>
                                            <label className="flex cursor-pointer items-center gap-1">
                                                <Checkbox
                                                    checked={note.is_visible_to_member}
                                                    onCheckedChange={(checked) =>
                                                        router.patch(
                                                            `/coach/notes/${note.id}`,
                                                            { is_visible_to_member: !!checked },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                />
                                                Terlihat oleh member
                                            </label>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function NoteForm({ memberId }: { memberId: number }) {
    const { data, setData, post, processing, reset } = useForm({ note: '', is_visible_to_member: false as boolean });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/coach/members/${memberId}/notes`, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="space-y-2">
            <Textarea value={data.note} onChange={(e) => setData('note', e.target.value)} placeholder="Tulis catatan..." rows={2} maxLength={2000} />
            <div className="flex items-center justify-between">
                <label className="flex cursor-pointer items-center gap-2 text-sm">
                    <Checkbox checked={data.is_visible_to_member} onCheckedChange={(checked) => setData('is_visible_to_member', !!checked)} />
                    Terlihat oleh member
                </label>
                <Button type="submit" size="sm" disabled={processing || !data.note}>
                    + Catatan
                </Button>
            </div>
        </form>
    );
}
