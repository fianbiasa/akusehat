import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard Coach', href: '/coach/dashboard' }];

type Concern = { member_id: number; member_name: string; reason: string };
type MemberRow = {
    id: number;
    name: string;
    program_name: string | null;
    health_score: number | null;
    health_score_delta: number | null;
    needs_attention: boolean;
};

export default function CoachDashboard({
    maxMembers,
    memberCount,
    concerns,
    members,
}: {
    maxMembers: number;
    memberCount: number;
    concerns: Concern[];
    members: MemberRow[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard Coach" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Dashboard Coach</h1>
                    <span className="text-muted-foreground text-sm">
                        {memberCount} / {maxMembers} anggota
                    </span>
                </div>

                {concerns.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                🔴 Perlu Perhatian <Badge variant="destructive">{concerns.length}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {concerns.map((c) => (
                                    <li key={c.member_id} className="flex items-center justify-between gap-4">
                                        <div>
                                            <span className="font-medium">{c.member_name}</span>
                                            <span className="text-muted-foreground ml-2 text-sm">{c.reason}</span>
                                        </div>
                                        <Button asChild size="sm" variant="outline">
                                            <Link href={`/coach/members/${c.member_id}`}>Tinjau →</Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Semua Anggota</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {members.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Belum ada anggota yang ditugaskan.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-muted-foreground border-b text-left">
                                            <th className="py-2 pr-4">Nama</th>
                                            <th className="py-2 pr-4">Program</th>
                                            <th className="py-2 pr-4">Health Score</th>
                                            <th className="py-2 pr-4">Status</th>
                                            <th className="py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {members.map((m) => (
                                            <tr key={m.id} className="border-b last:border-0">
                                                <td className="py-2 pr-4 font-medium">{m.name}</td>
                                                <td className="py-2 pr-4">{m.program_name ?? '-'}</td>
                                                <td className="py-2 pr-4">
                                                    {m.health_score !== null ? (
                                                        <>
                                                            {m.health_score}
                                                            {m.health_score_delta !== null && (
                                                                <span
                                                                    className={
                                                                        m.health_score_delta >= 0 ? 'ml-1 text-green-600' : 'ml-1 text-red-600'
                                                                    }
                                                                >
                                                                    {m.health_score_delta >= 0 ? '▲' : '▼'}
                                                                </span>
                                                            )}
                                                        </>
                                                    ) : (
                                                        '-'
                                                    )}
                                                </td>
                                                <td className="py-2 pr-4">
                                                    <Badge variant={m.needs_attention ? 'destructive' : 'secondary'}>
                                                        {m.needs_attention ? 'Perlu tinjau' : 'Aktif'}
                                                    </Badge>
                                                </td>
                                                <td className="py-2 text-right">
                                                    <Button asChild size="sm" variant="ghost">
                                                        <Link href={`/coach/members/${m.id}`}>Detail →</Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
