import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Question Bank', href: '/admin/onboarding-questions' }];

const inputTypes = ['text', 'number', 'date', 'single_choice', 'multi_choice', 'time', 'scale'] as const;

type Question = {
    id: number;
    step: number;
    order: number;
    category: string;
    question_text: string;
    input_type: (typeof inputTypes)[number];
    options: unknown;
    validation_rules: unknown;
    is_required: boolean;
    is_active: boolean;
};

export default function AdminOnboardingQuestionsIndex({ questions, categories }: { questions: Question[]; categories: string[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Question Bank" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <HeadingSmall title="Question Bank" description="Kelola urutan dan isi pertanyaan wizard onboarding" />
                    <QuestionFormDialog categories={categories} trigger={<Button>+ Tambah Pertanyaan</Button>} />
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="w-20 p-3 font-medium">Urutan</th>
                                <th className="p-3 font-medium">Kategori</th>
                                <th className="p-3 font-medium">Pertanyaan</th>
                                <th className="p-3 font-medium">Tipe</th>
                                <th className="p-3 font-medium">Wajib</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {questions.map((question, index) => (
                                <tr key={question.id} className="border-t align-top">
                                    <td className="p-3">
                                        <div className="flex items-center gap-1">
                                            <span className="text-muted-foreground w-6 text-xs">{question.order}</span>
                                            <div className="flex flex-col">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-5 w-5"
                                                    disabled={index === 0}
                                                    aria-label="Naikkan urutan"
                                                    onClick={() => router.post(`/admin/onboarding-questions/${question.id}/move-up`, {}, { preserveScroll: true })}
                                                >
                                                    <ArrowUp className="h-3 w-3" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-5 w-5"
                                                    disabled={index === questions.length - 1}
                                                    aria-label="Turunkan urutan"
                                                    onClick={() => router.post(`/admin/onboarding-questions/${question.id}/move-down`, {}, { preserveScroll: true })}
                                                >
                                                    <ArrowDown className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="p-3">{question.category}</td>
                                    <td className="max-w-xs p-3">{question.question_text}</td>
                                    <td className="p-3 font-mono text-xs">{question.input_type}</td>
                                    <td className="p-3">{question.is_required ? 'Ya' : 'Tidak'}</td>
                                    <td className="p-3">
                                        <Badge variant={question.is_active ? 'default' : 'secondary'}>{question.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <QuestionFormDialog
                                            categories={categories}
                                            question={question}
                                            trigger={
                                                <Button variant="outline" size="sm" className="mr-2">
                                                    Edit
                                                </Button>
                                            }
                                        />
                                        <Button
                                            variant={question.is_active ? 'destructive' : 'default'}
                                            size="sm"
                                            onClick={() =>
                                                router.post(`/admin/onboarding-questions/${question.id}/toggle-active`, {}, { preserveScroll: true })
                                            }
                                        >
                                            {question.is_active ? 'Nonaktifkan' : 'Aktifkan'}
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

function QuestionFormDialog({ question, categories, trigger }: { question?: Question; categories: string[]; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, transform, processing, errors } = useForm({
        category: question?.category ?? categories[0] ?? '',
        question_text: question?.question_text ?? '',
        input_type: question?.input_type ?? 'text',
        options: question ? JSON.stringify(question.options ?? [], null, 2) : '[]',
        validation_rules: question ? JSON.stringify(question.validation_rules ?? {}, null, 2) : '{}',
        is_required: question?.is_required ?? true,
    });

    const needsOptions = data.input_type === 'single_choice' || data.input_type === 'multi_choice' || data.input_type === 'scale';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        let parsedOptions;
        let parsedValidationRules;
        try {
            parsedOptions = data.options.trim() ? JSON.parse(data.options) : null;
            parsedValidationRules = data.validation_rules.trim() ? JSON.parse(data.validation_rules) : null;
        } catch {
            alert('Format JSON pada Options atau Validation Rules tidak valid.');
            return;
        }

        transform((formData) => ({ ...formData, options: parsedOptions, validation_rules: parsedValidationRules }));

        if (question) {
            patch(`/admin/onboarding-questions/${question.id}`, { onSuccess: () => setOpen(false) });
        } else {
            post('/admin/onboarding-questions', { onSuccess: () => setOpen(false) });
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-w-lg">
                <DialogTitle>{question ? 'Edit Pertanyaan' : 'Tambah Pertanyaan'}</DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Kategori</Label>
                        <Input value={data.category} onChange={(e) => setData('category', e.target.value)} required />
                        <InputErrorText message={errors.category} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Teks Pertanyaan</Label>
                        <Textarea value={data.question_text} onChange={(e) => setData('question_text', e.target.value)} required rows={2} />
                        <InputErrorText message={errors.question_text} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Tipe Input</Label>
                        <Select value={data.input_type} onValueChange={(v) => setData('input_type', v as typeof data.input_type)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {inputTypes.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {needsOptions && (
                        <div className="grid gap-2">
                            <Label>Options (JSON)</Label>
                            <Textarea
                                value={data.options}
                                onChange={(e) => setData('options', e.target.value)}
                                rows={4}
                                className="font-mono text-xs"
                            />
                            <p className="text-muted-foreground text-xs">
                                Untuk single/multi choice: array string, mis. {'["A", "B"]'}. Untuk scale: {'{"min": 1, "max": 5}'}.
                            </p>
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label>Validation Rules (JSON, opsional)</Label>
                        <Textarea
                            value={data.validation_rules}
                            onChange={(e) => setData('validation_rules', e.target.value)}
                            rows={3}
                            className="font-mono text-xs"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox checked={data.is_required} onCheckedChange={(v) => setData('is_required', !!v)} />
                        <Label>Wajib dijawab</Label>
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
