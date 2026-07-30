import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ExportData() {
    const { post, processing, recentlySuccessful } = useForm();

    const exportData: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('data-export.store'), { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            <HeadingSmall title="Ekspor data" description="Unduh salinan seluruh data pribadimu di AkuSehat" />
            <form onSubmit={exportData} className="flex items-center gap-4">
                <Button type="submit" variant="outline" disabled={processing}>
                    Minta Ekspor Data
                </Button>
                {recentlySuccessful && (
                    <p className="text-muted-foreground text-sm">Kami sedang menyiapkan datamu, tautan unduh akan dikirim ke emailmu.</p>
                )}
            </form>
        </div>
    );
}
