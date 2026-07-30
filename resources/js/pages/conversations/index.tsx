import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Percakapan', href: '/conversations' }];

type ConversationRow = {
    id: number;
    type: 'coach_member' | 'ai_assistant';
    user: { id: number; name: string };
    coach: { id: number; name: string } | null;
    last_message_at: string | null;
};

export default function ConversationsIndex({ conversations, hasActiveCoach }: { conversations: ConversationRow[]; hasActiveCoach: boolean }) {
    const startAiChat = () => router.post('/conversations');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Percakapan" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Percakapan</h1>

                {conversations.length === 0 && !hasActiveCoach && <p className="text-muted-foreground text-sm">Belum ada percakapan.</p>}

                <div className="grid gap-4 md:grid-cols-2">
                    {conversations.map((c) => (
                        <Card key={c.id}>
                            <CardHeader>
                                <CardTitle className="text-base">{c.type === 'ai_assistant' ? 'Asisten AI' : `Coach ${c.coach?.name}`}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Button asChild size="sm" variant="outline">
                                    <Link href={`/conversations/${c.id}`}>Buka</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}

                    {!conversations.some((c) => c.type === 'ai_assistant') && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Asisten AI</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Button size="sm" onClick={startAiChat}>
                                    Mulai Chat
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
