import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Profil Coach', href: '/coach/profile' }];

type CoachProfile = {
    bio: string | null;
    specialization: string | null;
    certification: string | null;
    max_members: number;
    rating_avg: string;
} | null;

export default function CoachProfileEdit({ profile }: { profile: CoachProfile }) {
    const { data, setData, patch, processing } = useForm({
        bio: profile?.bio ?? '',
        specialization: profile?.specialization ?? '',
        certification: profile?.certification ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch('/coach/profile', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profil Coach" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardContent className="pt-6">
                        <HeadingSmall title="Profil Coach" description="Bio dan spesialisasi ini terlihat oleh member yang ditugaskan kepadamu." />

                        {profile && (
                            <p className="text-muted-foreground mt-2 text-sm">
                                Rating rata-rata: {profile.rating_avg} · Maks. {profile.max_members} anggota
                            </p>
                        )}

                        <form onSubmit={submit} className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="specialization">Spesialisasi</Label>
                                <Input id="specialization" value={data.specialization} onChange={(e) => setData('specialization', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="certification">Sertifikasi</Label>
                                <Input id="certification" value={data.certification} onChange={(e) => setData('certification', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="bio">Bio</Label>
                                <Textarea id="bio" rows={4} value={data.bio} onChange={(e) => setData('bio', e.target.value)} />
                            </div>
                            <Button type="submit" disabled={processing}>
                                Simpan
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
