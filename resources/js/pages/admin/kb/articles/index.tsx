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
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Knowledge Base: Artikel', href: '/admin/kb/articles' }];

type Article = {
    id: number;
    title: string;
    slug: string;
    category: string | null;
    content: string;
    tags: string[] | null;
    is_published: boolean;
};

export default function AdminKbArticlesIndex({
    articles,
    filters,
}: {
    articles: Article[];
    categories: string[];
    filters: { category?: string; search?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Knowledge Base: Artikel" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <HeadingSmall title="Knowledge Base: Artikel" description="Artikel edukasi gizi untuk member" />
                    <ArticleFormDialog trigger={<Button>+ Tambah Artikel</Button>} />
                </div>

                <Input
                    placeholder="Cari judul artikel..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) =>
                        e.key === 'Enter' && router.get('/admin/kb/articles', { search }, { preserveState: true, replace: true })
                    }
                    onBlur={() => router.get('/admin/kb/articles', { search }, { preserveState: true, replace: true })}
                    className="w-64"
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Judul</th>
                                <th className="p-3 font-medium">Kategori</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {articles.map((article) => (
                                <tr key={article.id} className="border-t align-top">
                                    <td className="p-3">
                                        <div className="font-medium">{article.title}</div>
                                        <div className="text-muted-foreground text-xs">{article.slug}</div>
                                    </td>
                                    <td className="p-3">{article.category ?? '-'}</td>
                                    <td className="p-3">
                                        <Badge variant={article.is_published ? 'default' : 'secondary'}>
                                            {article.is_published ? 'Terbit' : 'Draf'}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <ArticleFormDialog
                                            article={article}
                                            trigger={
                                                <Button variant="outline" size="sm" className="mr-2">
                                                    Edit
                                                </Button>
                                            }
                                        />
                                        <Button
                                            variant={article.is_published ? 'destructive' : 'default'}
                                            size="sm"
                                            onClick={() =>
                                                router.post(`/admin/kb/articles/${article.id}/toggle-published`, {}, { preserveScroll: true })
                                            }
                                        >
                                            {article.is_published ? 'Sembunyikan' : 'Terbitkan'}
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

function ArticleFormDialog({ article, trigger }: { article?: Article; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, transform, processing, errors } = useForm({
        title: article?.title ?? '',
        category: article?.category ?? '',
        content: article?.content ?? '',
        tags: article ? JSON.stringify(article.tags ?? [], null, 2) : '[]',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        let parsedTags;
        try {
            parsedTags = data.tags.trim() ? JSON.parse(data.tags) : null;
        } catch {
            alert('Format JSON pada Tags tidak valid.');
            return;
        }

        transform((formData) => ({ ...formData, tags: parsedTags }));

        if (article) {
            patch(`/admin/kb/articles/${article.id}`, { onSuccess: () => setOpen(false) });
        } else {
            post('/admin/kb/articles', { onSuccess: () => setOpen(false) });
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogTitle>{article ? 'Edit Artikel' : 'Tambah Artikel'}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Judul</Label>
                            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                            <InputErrorText message={errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Kategori</Label>
                            <Input value={data.category} onChange={(e) => setData('category', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Konten</Label>
                        <Textarea value={data.content} onChange={(e) => setData('content', e.target.value)} rows={10} required />
                        <InputErrorText message={errors.content} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Tags (JSON array)</Label>
                        <Textarea value={data.tags} onChange={(e) => setData('tags', e.target.value)} rows={2} className="font-mono text-xs" />
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
