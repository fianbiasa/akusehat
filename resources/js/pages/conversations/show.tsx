import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { api } from '@/lib/api';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

const POLL_INTERVAL_MS = 4000;

type SuggestedAction = { type: string; label: string; payload?: Record<string, unknown> };

type Message = {
    id: number;
    sender_type: 'user' | 'coach' | 'ai' | 'system';
    content: string;
    meta: { suggested_actions?: SuggestedAction[] } | null;
    created_at: string;
};

type ConversationData = {
    id: number;
    type: 'coach_member' | 'ai_assistant';
    user: { id: number; name: string };
    coach: { id: number; name: string } | null;
};

/**
 * Real-time messaging (Echo/Pusher) isn't wired up in this environment -
 * this is the documented polling fallback (docs/11-Development-
 * Checklist.md Phase 8), shared between coach_member and ai_assistant
 * conversation types per the same checklist item.
 */
export default function ConversationShow({ conversation }: { conversation: ConversationData }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Percakapan', href: '/conversations' },
        { title: conversation.type === 'ai_assistant' ? 'Asisten AI' : conversation.coach?.name || 'Coach', href: '#' },
    ];

    const [messages, setMessages] = useState<Message[]>([]);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);

    const fetchMessages = async () => {
        const data = await api.get<Message[]>(`/conversations/${conversation.id}/messages`);
        setMessages(data);
    };

    useEffect(() => {
        fetchMessages();
        api.patch(`/conversations/${conversation.id}/read`).catch(() => {});

        const interval = setInterval(fetchMessages, POLL_INTERVAL_MS);
        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [conversation.id]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length]);

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();
        if (!draft.trim()) return;

        setSending(true);
        try {
            await api.post(`/conversations/${conversation.id}/messages`, { content: draft });
            setDraft('');
            await fetchMessages();
        } finally {
            setSending(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={conversation.type === 'ai_assistant' ? 'Asisten AI' : `Coach ${conversation.coach?.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <Card className="flex flex-1 flex-col">
                    <CardContent className="flex flex-1 flex-col gap-3 overflow-y-auto pt-6">
                        {messages.length === 0 && <p className="text-muted-foreground text-sm">Belum ada pesan. Mulai percakapan di bawah.</p>}
                        {messages.map((m) => (
                            <MessageBubble key={m.id} message={m} />
                        ))}
                        <div ref={bottomRef} />
                    </CardContent>
                </Card>

                <form onSubmit={submit} className="flex gap-2">
                    <Textarea
                        value={draft}
                        onChange={(e) => setDraft(e.target.value)}
                        placeholder="Tulis pesan..."
                        rows={1}
                        className="min-h-0"
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                submit(e as unknown as React.FormEvent);
                            }
                        }}
                    />
                    <Button type="submit" disabled={sending || !draft.trim()}>
                        Kirim
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}

function MessageBubble({ message }: { message: Message }) {
    const isMine = message.sender_type === 'user' || message.sender_type === 'coach';
    const isSystemish = message.sender_type === 'ai' || message.sender_type === 'system';

    return (
        <div className={`flex ${isMine ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`max-w-[75%] rounded-lg px-3 py-2 text-sm ${
                    isSystemish ? 'bg-muted text-foreground' : 'bg-primary text-primary-foreground'
                }`}
            >
                <p>{message.content}</p>
                {message.meta?.suggested_actions?.map((action, i) => (
                    <span key={i} className="mt-1 block text-xs opacity-80">
                        💡 {action.label}
                    </span>
                ))}
            </div>
        </div>
    );
}
