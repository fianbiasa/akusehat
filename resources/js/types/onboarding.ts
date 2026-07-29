export type OnboardingInputType = 'text' | 'number' | 'date' | 'single_choice' | 'multi_choice' | 'time' | 'scale';

export type RepeatableField = { key: string; label: string };

export type OnboardingQuestion = {
    id: number;
    step: number;
    category: string;
    question_text: string;
    input_type: OnboardingInputType;
    options: string[] | { min: number; max: number } | null;
    validation_rules: { repeatable: true; fields: RepeatableField[] } | null;
    is_required: boolean;
};

export type OnboardingAnswer = {
    id: number;
    question_id: number;
    answer_value: unknown;
};

export type OnboardingSession = {
    id: number;
    status: 'in_progress' | 'completed' | 'abandoned';
    current_step: number;
    answers: OnboardingAnswer[];
};

export type AnswerValue = string | number | string[] | Record<string, string>[] | null;
