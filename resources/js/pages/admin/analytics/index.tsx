import HeadingSmall from '@/components/heading-small';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Analytics', href: '/admin/analytics' }];

type ProviderCost = { provider: string; cost: number; percent: number };

type Summary = {
    active_users: number;
    program_completion_percent: number;
    avg_health_score: number | null;
    ai_cost_30d: number;
    ai_cost_by_provider: ProviderCost[];
};

const currency = (value: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 }).format(value);

export default function AdminAnalyticsIndex({ summary }: { summary: Summary }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Analytics" />

            <div className="space-y-6 p-4">
                <HeadingSmall title="Analytics" description="Ringkasan aktivitas platform 30 hari terakhir" />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-muted-foreground text-sm font-medium">Active Users</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{summary.active_users.toLocaleString()}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-muted-foreground text-sm font-medium">Program Completion</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{summary.program_completion_percent}%</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-muted-foreground text-sm font-medium">Avg Health Score</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{summary.avg_health_score ?? '–'}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-muted-foreground text-sm font-medium">AI Cost (30d)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{currency(summary.ai_cost_30d)}</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>AI Cost by Provider</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {summary.ai_cost_by_provider.length === 0 && (
                            <p className="text-muted-foreground text-sm">Belum ada request AI dalam 30 hari terakhir.</p>
                        )}
                        {summary.ai_cost_by_provider.map((row) => (
                            <div key={row.provider} className="space-y-1">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-medium">{row.provider}</span>
                                    <span className="text-muted-foreground">
                                        {currency(row.cost)} ({row.percent}%)
                                    </span>
                                </div>
                                <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                                    <div className="bg-primary h-full rounded-full" style={{ width: `${row.percent}%` }} />
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
