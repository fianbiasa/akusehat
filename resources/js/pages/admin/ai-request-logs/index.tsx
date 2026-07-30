import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Request Log', href: '/admin/ai/request-logs' }];

type LogEntry = {
    id: number;
    purpose: string;
    status: 'success' | 'error' | 'timeout' | 'invalid_json';
    prompt_tokens: number | null;
    completion_tokens: number | null;
    estimated_cost: string | null;
    latency_ms: number | null;
    error_message: string | null;
    created_at: string;
    provider: { id: number; name: string } | null;
    model: { id: number; name: string } | null;
    user: { id: number; name: string } | null;
};

type Stats = {
    total_requests: number;
    success_rate: number;
    total_cost: number;
    avg_latency_ms: number;
    cost_by_purpose: { purpose: string; cost: string; requests: number }[];
};

const statusVariant: Record<LogEntry['status'], 'default' | 'destructive' | 'secondary'> = {
    success: 'default',
    error: 'destructive',
    timeout: 'destructive',
    invalid_json: 'secondary',
};

export default function AdminAiRequestLogsIndex({
    logs,
    purposes,
    providers,
    stats,
    filters,
}: {
    logs: { data: LogEntry[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; to: number | null; total: number };
    purposes: string[];
    providers: { id: number; name: string }[];
    stats: Stats;
    filters: { purpose?: string; status?: string; provider_id?: string };
}) {
    const applyFilter = (key: string, value: string) => {
        router.get(
            '/admin/ai/request-logs',
            { ...filters, [key]: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Request Log" />

            <div className="space-y-6 p-4">
                <HeadingSmall title="AI Request Log" description="Riwayat pemanggilan AI beserta estimasi biaya dan performa" />

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="Total Permintaan" value={stats.total_requests.toLocaleString('id-ID')} />
                    <StatCard label="Tingkat Sukses" value={`${stats.success_rate}%`} />
                    <StatCard label="Total Biaya (est.)" value={`$${stats.total_cost.toFixed(4)}`} />
                    <StatCard label="Rata-rata Latensi" value={`${stats.avg_latency_ms} ms`} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Biaya per Tujuan (Purpose)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-2 font-medium">Purpose</th>
                                        <th className="p-2 font-medium">Permintaan</th>
                                        <th className="p-2 font-medium">Biaya (est.)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stats.cost_by_purpose.map((row) => (
                                        <tr key={row.purpose} className="border-t">
                                            <td className="p-2 font-mono text-xs">{row.purpose}</td>
                                            <td className="p-2">{row.requests}</td>
                                            <td className="p-2">${Number(row.cost ?? 0).toFixed(4)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-3">
                    <Select value={filters.purpose ?? 'all'} onValueChange={(v) => applyFilter('purpose', v)}>
                        <SelectTrigger className="w-56">
                            <SelectValue placeholder="Semua tujuan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua tujuan</SelectItem>
                            {purposes.map((p) => (
                                <SelectItem key={p} value={p}>
                                    {p}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.status ?? 'all'} onValueChange={(v) => applyFilter('status', v)}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            <SelectItem value="success">success</SelectItem>
                            <SelectItem value="error">error</SelectItem>
                            <SelectItem value="timeout">timeout</SelectItem>
                            <SelectItem value="invalid_json">invalid_json</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={filters.provider_id ?? 'all'} onValueChange={(v) => applyFilter('provider_id', v)}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Semua provider" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua provider</SelectItem>
                            {providers.map((p) => (
                                <SelectItem key={p.id} value={String(p.id)}>
                                    {p.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Waktu</th>
                                <th className="p-3 font-medium">Purpose</th>
                                <th className="p-3 font-medium">Provider / Model</th>
                                <th className="p-3 font-medium">User</th>
                                <th className="p-3 font-medium">Token</th>
                                <th className="p-3 font-medium">Biaya</th>
                                <th className="p-3 font-medium">Latensi</th>
                                <th className="p-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-t align-top">
                                    <td className="p-3 text-xs whitespace-nowrap">{log.created_at}</td>
                                    <td className="p-3 font-mono text-xs">{log.purpose}</td>
                                    <td className="p-3 text-xs">
                                        {log.provider?.name ?? '-'} / {log.model?.name ?? '-'}
                                    </td>
                                    <td className="p-3 text-xs">{log.user?.name ?? '-'}</td>
                                    <td className="p-3 text-xs">
                                        {(log.prompt_tokens ?? 0) + (log.completion_tokens ?? 0) || '-'}
                                    </td>
                                    <td className="p-3 text-xs">{log.estimated_cost ? `$${Number(log.estimated_cost).toFixed(4)}` : '-'}</td>
                                    <td className="p-3 text-xs">{log.latency_ms ? `${log.latency_ms} ms` : '-'}</td>
                                    <td className="p-3">
                                        <Badge variant={statusVariant[log.status]}>{log.status}</Badge>
                                        {log.error_message && (
                                            <div className="text-destructive mt-1 max-w-xs text-xs break-words">{log.error_message}</div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="text-muted-foreground p-6 text-center">
                                        Belum ada log permintaan AI.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {logs.links.map((link, index) => (
                        <Button
                            key={index}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function StatCard({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="text-muted-foreground text-xs">{label}</div>
                <div className="mt-1 text-2xl font-semibold">{value}</div>
            </CardContent>
        </Card>
    );
}
