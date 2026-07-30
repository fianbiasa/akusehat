import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Subscriptions', href: '/admin/subscriptions' }];

type SubscriptionRow = {
    id: number;
    status: 'trialing' | 'active' | 'past_due' | 'cancelled' | 'expired';
    starts_at: string;
    ends_at: string | null;
    cancelled_at: string | null;
    user: { id: number; name: string; email: string };
    plan: { id: number; name: string };
};

const statusVariant = {
    trialing: 'secondary',
    active: 'default',
    past_due: 'destructive',
    cancelled: 'secondary',
    expired: 'destructive',
} as const;

export default function AdminSubscriptionsIndex({
    subscriptions,
    plans,
    filters,
}: {
    subscriptions: Paginated<SubscriptionRow>;
    plans: { id: number; name: string }[];
    filters: { status?: string; plan_id?: string };
}) {
    const applyFilters = (next: Partial<typeof filters>) => {
        router.get('/admin/subscriptions', { ...filters, ...next }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subscriptions" />

            <div className="space-y-6 p-4">
                <HeadingSmall title="Subscriptions" description="Semua langganan Member di platform" />

                <div className="flex flex-wrap gap-3">
                    <Select value={filters.status ?? 'all'} onValueChange={(v) => applyFilters({ status: v === 'all' ? undefined : v })}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            <SelectItem value="trialing">Trial</SelectItem>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="past_due">Jatuh Tempo</SelectItem>
                            <SelectItem value="cancelled">Dibatalkan</SelectItem>
                            <SelectItem value="expired">Berakhir</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.plan_id ?? 'all'}
                        onValueChange={(v) => applyFilters({ plan_id: v === 'all' ? undefined : v })}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Plan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua plan</SelectItem>
                            {plans.map((plan) => (
                                <SelectItem key={plan.id} value={String(plan.id)}>
                                    {plan.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Member</th>
                                <th className="p-3 font-medium">Plan</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Mulai</th>
                                <th className="p-3 font-medium">Berakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            {subscriptions.data.map((subscription) => (
                                <tr key={subscription.id} className="border-t">
                                    <td className="p-3">
                                        <div className="font-medium">{subscription.user.name}</div>
                                        <div className="text-muted-foreground text-xs">{subscription.user.email}</div>
                                    </td>
                                    <td className="p-3">{subscription.plan.name}</td>
                                    <td className="p-3">
                                        <Badge variant={statusVariant[subscription.status]}>{subscription.status}</Badge>
                                    </td>
                                    <td className="text-muted-foreground p-3">{subscription.starts_at}</td>
                                    <td className="text-muted-foreground p-3">{subscription.ends_at ?? '–'}</td>
                                </tr>
                            ))}
                            {subscriptions.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground p-6 text-center">
                                        Tidak ada langganan yang cocok.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {subscriptions.links.map((link, index) => (
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
