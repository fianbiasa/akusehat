import QuestionInput from '@/components/onboarding/question-input';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api';
import { type AnswerValue, type OnboardingQuestion, type OnboardingSession } from '@/types/onboarding';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function OnboardingWizard({ questions, session }: { questions: OnboardingQuestion[]; session: OnboardingSession }) {
    const answersByQuestionId = useMemo(() => {
        const map = new Map<number, AnswerValue>();
        session.answers.forEach((answer) => map.set(answer.question_id, answer.answer_value as AnswerValue));
        return map;
    }, [session.answers]);

    const initialStep = Math.min(Math.max(session.current_step - 1, 0), questions.length - 1);
    const [stepIndex, setStepIndex] = useState(initialStep);
    const [answers, setAnswers] = useState(answersByQuestionId);
    const [saving, setSaving] = useState(false);
    const [completing, setCompleting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const question = questions[stepIndex];
    const value = answers.get(question.id) ?? null;
    const isAnswered = value !== null && value !== '' && !(Array.isArray(value) && value.length === 0);
    const isLastStep = stepIndex === questions.length - 1;

    const setValue = (next: AnswerValue) => {
        setAnswers((prev) => new Map(prev).set(question.id, next));
    };

    const saveAnswer = async () => {
        await api.post(`/onboarding/sessions/${session.id}/answers`, {
            question_id: question.id,
            value: answers.get(question.id),
        });
    };

    const handleNext = async () => {
        setError(null);

        if (isAnswered) {
            setSaving(true);
            try {
                await saveAnswer();
            } catch {
                setError('Gagal menyimpan jawaban. Coba lagi.');
                setSaving(false);
                return;
            }
            setSaving(false);
        }

        if (isLastStep) {
            await handleComplete();
            return;
        }

        setStepIndex((i) => i + 1);
    };

    const handleBack = () => {
        setError(null);
        setStepIndex((i) => Math.max(0, i - 1));
    };

    const handleSkip = () => {
        setError(null);
        if (isLastStep) {
            handleComplete();
            return;
        }
        setStepIndex((i) => i + 1);
    };

    const handleComplete = async () => {
        setCompleting(true);
        setError(null);
        try {
            const result = await api.post<{ redirect: string }>(`/onboarding/sessions/${session.id}/complete`);
            router.visit(result.redirect);
        } catch (e) {
            const err = e as { status: number; data: { message?: string } };
            setCompleting(false);
            setError(err.data?.message ?? 'Beberapa jawaban wajib belum lengkap. Silakan periksa kembali.');
        }
    };

    if (completing) {
        return (
            <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
                <Head title="Menyelesaikan onboarding..." />
                <div className="text-2xl">✅ Selesai!</div>
                <p className="text-muted-foreground max-w-sm text-sm">Sedang menyusun profil kesehatan kamu... ini biasanya butuh beberapa detik.</p>
            </div>
        );
    }

    const progressPct = Math.round(((stepIndex + 1) / questions.length) * 100);

    return (
        <div className="mx-auto flex min-h-screen max-w-xl flex-col justify-center gap-8 p-6">
            <Head title="Onboarding" />

            <div className="space-y-2">
                <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                    <div className="bg-primary h-full transition-all" style={{ width: `${progressPct}%` }} />
                </div>
                <p className="text-muted-foreground text-center text-xs">
                    Langkah {stepIndex + 1} dari {questions.length}
                </p>
            </div>

            <div className="space-y-6">
                <h1 className="text-center text-xl font-semibold text-balance">{question.question_text}</h1>

                <QuestionInput question={question} value={value} onChange={setValue} />

                {error && <p className="text-destructive text-center text-sm">{error}</p>}
            </div>

            <div className="flex items-center justify-between">
                <Button type="button" variant="ghost" onClick={handleBack} disabled={stepIndex === 0 || saving}>
                    ← Kembali
                </Button>

                <div className="flex items-center gap-4">
                    {!question.is_required && (
                        <button type="button" onClick={handleSkip} className="text-muted-foreground text-sm underline">
                            Lewati
                        </button>
                    )}
                    <Button type="button" onClick={handleNext} disabled={saving || (question.is_required && !isAnswered)}>
                        {isLastStep ? 'Selesai' : 'Lanjut →'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
