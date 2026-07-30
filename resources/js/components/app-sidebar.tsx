import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    Apple,
    BarChart3,
    BookOpen,
    Bot,
    CreditCard,
    Dumbbell,
    FileCode,
    Folder,
    HeartPulse,
    History,
    LayoutGrid,
    ListChecks,
    MessageCircle,
    MessageCircleQuestion,
    Newspaper,
    Receipt,
    Settings,
    ShieldCheck,
    TrendingUp,
    Users,
    HelpCircle,
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Progress',
        url: '/progress',
        icon: TrendingUp,
    },
];

const coachNavItems: NavItem[] = [
    {
        title: 'Dashboard Coach',
        url: '/coach/dashboard',
        icon: LayoutGrid,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Users',
        url: '/admin/users',
        icon: Users,
    },
    {
        title: 'Roles',
        url: '/admin/roles',
        icon: ShieldCheck,
    },
    {
        title: 'Rule Engine',
        url: '/admin/rule-engine/rules',
        icon: ListChecks,
    },
    {
        title: 'AI Providers',
        url: '/admin/ai/providers',
        icon: Bot,
    },
    {
        title: 'Prompt Templates',
        url: '/admin/ai/prompt-templates',
        icon: FileCode,
    },
    {
        title: 'AI Request Log',
        url: '/admin/ai/request-logs',
        icon: Receipt,
    },
    {
        title: 'Analytics',
        url: '/admin/analytics',
        icon: BarChart3,
    },
    {
        title: 'Activity Log',
        url: '/admin/activity-log',
        icon: History,
    },
    {
        title: 'Plans',
        url: '/admin/plans',
        icon: CreditCard,
    },
    {
        title: 'Subscriptions',
        url: '/admin/subscriptions',
        icon: CreditCard,
    },
    {
        title: 'Platform Settings',
        url: '/admin/settings',
        icon: Settings,
    },
    {
        title: 'Question Bank',
        url: '/admin/onboarding-questions',
        icon: HelpCircle,
    },
    {
        title: 'KB: Makanan',
        url: '/admin/kb/foods',
        icon: Apple,
    },
    {
        title: 'KB: Latihan',
        url: '/admin/kb/exercises',
        icon: Dumbbell,
    },
    {
        title: 'KB: Penyakit',
        url: '/admin/kb/diseases',
        icon: HeartPulse,
    },
    {
        title: 'KB: Artikel',
        url: '/admin/kb/articles',
        icon: Newspaper,
    },
    {
        title: 'KB: FAQ',
        url: '/admin/kb/faqs',
        icon: MessageCircleQuestion,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { hasPermission, hasRole } = usePermission();

    const chatNavItems: NavItem[] = hasPermission('chat.send') ? [{ title: 'Percakapan', url: '/conversations', icon: MessageCircle }] : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {hasRole('coach') ? <NavMain items={[...coachNavItems, ...chatNavItems]} /> : <NavMain items={[...mainNavItems, ...chatNavItems]} />}
                {hasPermission('users.manage') && <NavMain items={adminNavItems} label="Admin" />}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
