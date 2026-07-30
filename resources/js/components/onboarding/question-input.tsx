import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { type AnswerValue, type OnboardingQuestion } from '@/types/onboarding';
import { Plus, Trash2 } from 'lucide-react';

export default function QuestionInput({
    question,
    value,
    onChange,
    ariaLabelledBy,
}: {
    question: OnboardingQuestion;
    value: AnswerValue;
    onChange: (value: AnswerValue) => void;
    ariaLabelledBy?: string;
}) {
    if (question.validation_rules?.repeatable) {
        return <RepeatableRows question={question} value={value as Record<string, string>[] | null} onChange={onChange} />;
    }

    switch (question.input_type) {
        case 'text':
            return (
                <Input
                    autoFocus
                    aria-labelledby={ariaLabelledBy}
                    className="h-14 text-center text-lg"
                    value={(value as string) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="Ketik jawaban kamu..."
                />
            );
        case 'number':
            return (
                <Input
                    autoFocus
                    type="number"
                    aria-labelledby={ariaLabelledBy}
                    className="h-14 text-center text-lg"
                    value={(value as string) ?? ''}
                    onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
                />
            );
        case 'date':
            return (
                <Input
                    autoFocus
                    type="date"
                    aria-labelledby={ariaLabelledBy}
                    className="h-14 text-center text-lg"
                    value={(value as string) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
        case 'time':
            return (
                <Input
                    autoFocus
                    type="time"
                    aria-labelledby={ariaLabelledBy}
                    className="h-14 text-center text-lg"
                    value={(value as string) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
        case 'single_choice':
            return (
                <ChoiceCards
                    options={question.options as string[]}
                    value={value as string}
                    multiple={false}
                    onChange={onChange}
                    ariaLabelledBy={ariaLabelledBy}
                />
            );
        case 'multi_choice':
            return (
                <ChoiceCards
                    options={question.options as string[]}
                    value={(value as string[]) ?? []}
                    multiple
                    onChange={onChange}
                    ariaLabelledBy={ariaLabelledBy}
                />
            );
        case 'scale':
            return (
                <ScaleInput
                    options={question.options as { min: number; max: number }}
                    value={value as number}
                    onChange={onChange}
                    ariaLabelledBy={ariaLabelledBy}
                />
            );
        default:
            return null;
    }
}

function ChoiceCards({
    options,
    value,
    multiple,
    onChange,
    ariaLabelledBy,
}: {
    options: string[];
    value: string | string[];
    multiple: boolean;
    onChange: (value: AnswerValue) => void;
    ariaLabelledBy?: string;
}) {
    const selected = new Set(multiple ? (value as string[]) : value ? [value as string] : []);

    const toggle = (option: string) => {
        if (!multiple) {
            onChange(option);
            return;
        }
        const next = new Set(selected);
        if (next.has(option)) {
            next.delete(option);
        } else {
            next.add(option);
        }
        onChange(Array.from(next));
    };

    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2" role="group" aria-labelledby={ariaLabelledBy}>
            {options.map((option) => (
                <button
                    key={option}
                    type="button"
                    aria-pressed={selected.has(option)}
                    onClick={() => toggle(option)}
                    className={cn(
                        'rounded-lg border p-4 text-left text-sm transition-colors',
                        selected.has(option) ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-accent',
                    )}
                >
                    {option}
                </button>
            ))}
        </div>
    );
}

function ScaleInput({
    options,
    value,
    onChange,
    ariaLabelledBy,
}: {
    options: { min: number; max: number };
    value: number;
    onChange: (value: AnswerValue) => void;
    ariaLabelledBy?: string;
}) {
    const steps = Array.from({ length: options.max - options.min + 1 }, (_, i) => options.min + i);

    return (
        <div className="flex justify-center gap-2" role="group" aria-labelledby={ariaLabelledBy}>
            {steps.map((step) => (
                <button
                    key={step}
                    type="button"
                    aria-pressed={value === step}
                    onClick={() => onChange(step)}
                    className={cn(
                        'flex h-12 w-12 items-center justify-center rounded-full border text-sm font-medium transition-colors',
                        value === step ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-accent',
                    )}
                >
                    {step}
                </button>
            ))}
        </div>
    );
}

function RepeatableRows({
    question,
    value,
    onChange,
}: {
    question: OnboardingQuestion;
    value: Record<string, string>[] | null;
    onChange: (value: AnswerValue) => void;
}) {
    const fields = question.validation_rules!.fields;
    const rows = value && value.length > 0 ? value : [Object.fromEntries(fields.map((f) => [f.key, '']))];

    const updateRow = (index: number, key: string, val: string) => {
        const next = rows.map((row, i) => (i === index ? { ...row, [key]: val } : row));
        onChange(next);
    };

    const addRow = () => onChange([...rows, Object.fromEntries(fields.map((f) => [f.key, '']))]);
    const removeRow = (index: number) => onChange(rows.filter((_, i) => i !== index));

    return (
        <div className="space-y-3">
            {rows.map((row, index) => (
                <div key={index} className="flex items-center gap-2">
                    {fields.map((field) => (
                        <Input
                            key={field.key}
                            aria-label={field.label}
                            placeholder={field.label}
                            value={row[field.key] ?? ''}
                            onChange={(e) => updateRow(index, field.key, e.target.value)}
                        />
                    ))}
                    {rows.length > 1 && (
                        <Button type="button" variant="ghost" size="icon" aria-label="Hapus baris" onClick={() => removeRow(index)}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            ))}
            <Button type="button" variant="outline" size="sm" onClick={addRow}>
                <Plus className="mr-1 h-4 w-4" /> Tambah lagi
            </Button>
        </div>
    );
}
