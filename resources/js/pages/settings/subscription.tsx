import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Langganan', href: '/subscription' }];

type Plan = {
    id: number;
    name: string;
    slug: string;
    price: string;
    billing_cycle: 'monthly' | 'yearly' | 'lifetime';
    features: string[] | null;
    max_programs: number;
    has_coach_access: boolean;
};

type SubscriptionData = {
    id: number;
    status: 'trialing' | 'active' | 'past_due' | 'cancelled' | 'expired';
    starts_at: string;
    ends_at: string | null;
    cancelled_at: string | null;
    plan: Plan;
};

type Payment = {
    id: number;
    provider: string;
    provider_reference: string | null;
    amount: string;
    currency: string;
    status: string;
    paid_at: string | null;
    created_at: string;
    subscription: { plan: { name: string } };
};

const currency = (value: string) => `Rp ${Number(value).toLocaleString('id-ID')}`;

const statusLabel: Record<SubscriptionData['status'], string> = {
    trialing: 'Trial',
    active: 'Aktif',
    past_due: 'Jatuh Tempo',
    cancelled: 'Dibatalkan',
    expired: 'Berakhir',
};

export default function SubscriptionSettings({
    subscription,
    usage,
    plans,
    payments,
}: {
    subscription: SubscriptionData;
    usage: { active_programs: number; max_programs: number };
    plans: Plan[];
    payments: Payment[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Langganan" />

            <SettingsLayout>
                <div className="space-y-8">
                    <div className="space-y-4">
                        <HeadingSmall title="Langganan" description="Kelola paket langganan dan lihat riwayat pembayaran" />

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>{subscription.plan.name}</CardTitle>
                                <Badge variant={subscription.status === 'active' ? 'default' : 'secondary'}>{statusLabel[subscription.status]}</Badge>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p className="text-muted-foreground">
                                    Program aktif: {usage.active_programs} / {usage.max_programs}
                                </p>
                                <p className="text-muted-foreground">Akses Coach: {subscription.plan.has_coach_access ? 'Ya' : 'Tidak'}</p>
                                {subscription.ends_at && (
                                    <p className="text-muted-foreground">
                                        {subscription.cancelled_at ? 'Berakhir pada' : 'Diperpanjang pada'} {subscription.ends_at}
                                    </p>
                                )}
                            </CardContent>
                            {Number(subscription.plan.price) > 0 && !subscription.cancelled_at && (
                                <CardFooter>
                                    <CancelSubscriptionDialog />
                                </CardFooter>
                            )}
                        </Card>
                    </div>

                    <div className="space-y-4">
                        <HeadingSmall title="Pilih Paket" description="Upgrade kapan saja - perubahan berlaku segera" />
                        <div className="grid gap-4 sm:grid-cols-3">
                            {plans.map((plan) => (
                                <PlanCard key={plan.id} plan={plan} isCurrent={plan.id === subscription.plan.id} />
                            ))}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <HeadingSmall title="Riwayat Pembayaran" description="" />
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">Tanggal</th>
                                        <th className="p-3 font-medium">Paket</th>
                                        <th className="p-3 font-medium">Jumlah</th>
                                        <th className="p-3 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payments.map((payment) => (
                                        <tr key={payment.id} className="border-t">
                                            <td className="p-3">{payment.paid_at ?? payment.created_at}</td>
                                            <td className="p-3">{payment.subscription.plan.name}</td>
                                            <td className="p-3">{currency(payment.amount)}</td>
                                            <td className="p-3">
                                                <Badge variant={payment.status === 'paid' ? 'default' : 'secondary'}>{payment.status}</Badge>
                                            </td>
                                        </tr>
                                    ))}
                                    {payments.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="text-muted-foreground p-6 text-center">
                                                Belum ada riwayat pembayaran.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function PlanCard({ plan, isCurrent }: { plan: Plan; isCurrent: boolean }) {
    const { post, processing } = useForm({ plan_id: plan.id });

    return (
        <Card className={isCurrent ? 'border-primary' : ''}>
            <CardHeader>
                <CardTitle className="text-base">{plan.name}</CardTitle>
                <p className="text-2xl font-bold">
                    {currency(plan.price)}
                    <span className="text-muted-foreground text-sm font-normal">/{plan.billing_cycle === 'monthly' ? 'bln' : plan.billing_cycle === 'yearly' ? 'thn' : 'selamanya'}</span>
                </p>
            </CardHeader>
            <CardContent>
                <ul className="text-muted-foreground space-y-1 text-sm">
                    {(plan.features ?? []).map((feature) => (
                        <li key={feature}>• {feature}</li>
                    ))}
                </ul>
            </CardContent>
            <CardFooter>
                {isCurrent ? (
                    <Button className="w-full" variant="outline" disabled>
                        Paket Saat Ini
                    </Button>
                ) : (
                    <Button
                        className="w-full"
                        disabled={processing}
                        onClick={() => post('/subscription/subscribe', { preserveScroll: true })}
                    >
                        Pilih Paket
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}

function CancelSubscriptionDialog() {
    const [open, setOpen] = useState(false);
    const { post, processing } = useForm();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    Batalkan Langganan
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Batalkan langganan?</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    Kamu tetap bisa memakai fitur premium sampai periode langganan saat ini berakhir. Setelah itu akun otomatis kembali ke paket
                    Gratis.
                </p>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={processing}
                        onClick={() => post('/subscription/cancel', { preserveScroll: true, onSuccess: () => setOpen(false) })}
                    >
                        Ya, Batalkan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
