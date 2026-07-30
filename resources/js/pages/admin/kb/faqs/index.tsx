import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Knowledge Base: FAQ', href: '/admin/kb/faqs' }];

type Faq = {
    id: number;
    question: string;
    answer: string;
    category: string | null;
    order: number;
    is_published: boolean;
};

export default function AdminKbFaqsIndex({ faqs }: { faqs: Faq[]; categories: string[]; filters: { category?: string } }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Knowledge Base: FAQ" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <HeadingSmall title="Knowledge Base: FAQ" description="Pertanyaan yang sering diajukan" />
                    <FaqFormDialog trigger={<Button>+ Tambah FAQ</Button>} />
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="w-16 p-3 font-medium">Urutan</th>
                                <th className="p-3 font-medium">Pertanyaan</th>
                                <th className="p-3 font-medium">Kategori</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {faqs.map((faq, index) => (
                                <tr key={faq.id} className="border-t align-top">
                                    <td className="p-3">
                                        <div className="flex items-center gap-1">
                                            <span className="text-muted-foreground w-6 text-xs">{faq.order}</span>
                                            <div className="flex flex-col">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-5 w-5"
                                                    disabled={index === 0}
                                                    aria-label="Naikkan urutan"
                                                    onClick={() => router.post(`/admin/kb/faqs/${faq.id}/move-up`, {}, { preserveScroll: true })}
                                                >
                                                    <ArrowUp className="h-3 w-3" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-5 w-5"
                                                    disabled={index === faqs.length - 1}
                                                    aria-label="Turunkan urutan"
                                                    onClick={() => router.post(`/admin/kb/faqs/${faq.id}/move-down`, {}, { preserveScroll: true })}
                                                >
                                                    <ArrowDown className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="max-w-md p-3">
                                        <div className="font-medium">{faq.question}</div>
                                        <div className="text-muted-foreground mt-1 line-clamp-2 text-xs">{faq.answer}</div>
                                    </td>
                                    <td className="p-3">{faq.category ?? '-'}</td>
                                    <td className="p-3">
                                        <Badge variant={faq.is_published ? 'default' : 'secondary'}>{faq.is_published ? 'Terbit' : 'Draf'}</Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <FaqFormDialog
                                            faq={faq}
                                            trigger={
                                                <Button variant="outline" size="sm" className="mr-2">
                                                    Edit
                                                </Button>
                                            }
                                        />
                                        <Button
                                            variant={faq.is_published ? 'destructive' : 'default'}
                                            size="sm"
                                            onClick={() => router.post(`/admin/kb/faqs/${faq.id}/toggle-published`, {}, { preserveScroll: true })}
                                        >
                                            {faq.is_published ? 'Sembunyikan' : 'Terbitkan'}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function FaqFormDialog({ faq, trigger }: { faq?: Faq; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors } = useForm({
        question: faq?.question ?? '',
        answer: faq?.answer ?? '',
        category: faq?.category ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (faq) {
            patch(`/admin/kb/faqs/${faq.id}`, { onSuccess: () => setOpen(false) });
        } else {
            post('/admin/kb/faqs', { onSuccess: () => setOpen(false) });
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-w-lg">
                <DialogTitle>{faq ? 'Edit FAQ' : 'Tambah FAQ'}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Pertanyaan</Label>
                        <Input value={data.question} onChange={(e) => setData('question', e.target.value)} required />
                        <InputErrorText message={errors.question} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Jawaban</Label>
                        <Textarea value={data.answer} onChange={(e) => setData('answer', e.target.value)} rows={5} required />
                        <InputErrorText message={errors.answer} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Kategori</Label>
                        <Input value={data.category} onChange={(e) => setData('category', e.target.value)} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function InputErrorText({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="text-destructive text-sm">{message}</p>;
}
