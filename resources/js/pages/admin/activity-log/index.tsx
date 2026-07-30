import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Activity Log', href: '/admin/activity-log' }];

type ActivityLogEntry = {
    id: number;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    meta: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
};

export default function AdminActivityLogIndex({
    logs,
    filters,
}: {
    logs: Paginated<ActivityLogEntry>;
    filters: { action?: string; user_id?: string };
}) {
    const [action, setAction] = useState(filters.action ?? '');

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get('/admin/activity-log', { action, ...next }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Activity Log" />

            <div className="space-y-6 p-4">
                <HeadingSmall title="Activity Log" description="Riwayat aksi admin, coach, dan sistem yang tercatat" />

                <Input
                    placeholder="Cari action (mis. user.created)..."
                    value={action}
                    onChange={(e) => setAction(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && applyFilters({ action })}
                    className="max-w-xs"
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Waktu</th>
                                <th className="p-3 font-medium">User</th>
                                <th className="p-3 font-medium">Action</th>
                                <th className="p-3 font-medium">Subjek</th>
                                <th className="p-3 font-medium">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-t align-top">
                                    <td className="text-muted-foreground p-3 whitespace-nowrap">{new Date(log.created_at).toLocaleString()}</td>
                                    <td className="p-3">{log.user?.name ?? 'Sistem'}</td>
                                    <td className="p-3 font-mono text-xs">{log.action}</td>
                                    <td className="p-3">
                                        {log.subject_type && (
                                            <span className="text-muted-foreground text-xs">
                                                {log.subject_type.split('\\').pop()} #{log.subject_id}
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-muted-foreground p-3">{log.ip_address ?? '–'}</td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground p-6 text-center">
                                        Belum ada aktivitas yang tercatat.
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
