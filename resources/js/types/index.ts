import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    status: 'active' | 'suspended' | 'pending';
    email_verified_at: string | null;
    role: { id: number; name: string; label: string };
    permissions: string[];
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
