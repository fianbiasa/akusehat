import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Prompt Templates', href: '/admin/ai/prompt-templates' }];

type Template = {
    id: number;
    key: string;
    purpose: string;
    template: string;
    variables: string[];
    response_schema: Record<string, unknown>;
    version: number;
    is_active: boolean;
};

export default function AdminAiPromptTemplatesIndex({ templates }: { templates: Template[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Prompt Templates" />

            <div className="space-y-6 p-4">
                <HeadingSmall
                    title="Prompt Templates"
                    description="Template AI seed-managed. Setiap penyimpanan menaikkan versi - log permintaan lama tidak ditafsirkan ulang dengan versi baru."
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Key</th>
                                <th className="p-3 font-medium">Tujuan</th>
                                <th className="p-3 font-medium">Versi</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {templates.map((template) => (
                                <tr key={template.id} className="border-t align-top">
                                    <td className="p-3 font-mono text-xs">{template.key}</td>
                                    <td className="max-w-xs p-3">{template.purpose}</td>
                                    <td className="p-3">v{template.version}</td>
                                    <td className="p-3">
                                        <Badge variant={template.is_active ? 'default' : 'secondary'}>
                                            {template.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <TemplateFormDialog template={template} trigger={<Button variant="outline" size="sm">Edit</Button>} />
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

function TemplateFormDialog({ template, trigger }: { template: Template; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, transform, processing, errors } = useForm({
        purpose: template.purpose,
        template: template.template,
        variables: JSON.stringify(template.variables ?? [], null, 2),
        response_schema: JSON.stringify(template.response_schema ?? {}, null, 2),
        is_active: template.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        let parsedVariables, parsedSchema;
        try {
            parsedVariables = JSON.parse(data.variables);
            parsedSchema = JSON.parse(data.response_schema);
        } catch {
            alert('Format JSON pada Variables atau Response Schema tidak valid.');
            return;
        }

        transform((formData) => ({ ...formData, variables: parsedVariables, response_schema: parsedSchema }));

        patch(`/admin/ai/prompt-templates/${template.id}`, { onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-w-3xl">
                <DialogTitle>
                    Edit Template: <span className="font-mono text-base">{template.key}</span>
                </DialogTitle>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Tujuan</Label>
                        <Input value={data.purpose} onChange={(e) => setData('purpose', e.target.value)} required />
                        <InputErrorText message={errors.purpose} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Template ({'{{ variable }}'} placeholders)</Label>
                        <Textarea
                            value={data.template}
                            onChange={(e) => setData('template', e.target.value)}
                            rows={10}
                            className="font-mono text-xs"
                            required
                        />
                        <InputErrorText message={errors.template} />
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Variables (JSON array)</Label>
                            <Textarea
                                value={data.variables}
                                onChange={(e) => setData('variables', e.target.value)}
                                rows={5}
                                className="font-mono text-xs"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Response Schema (JSON)</Label>
                            <Textarea
                                value={data.response_schema}
                                onChange={(e) => setData('response_schema', e.target.value)}
                                rows={5}
                                className="font-mono text-xs"
                            />
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', !!v)} />
                        <Label>Aktif</Label>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan (naikkan ke v{template.version + 1})
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
