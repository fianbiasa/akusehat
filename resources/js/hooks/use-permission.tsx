import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export function usePermission() {
    const { auth } = usePage<SharedData>().props;

    return {
        hasPermission: (permission: string) => auth.user?.permissions?.includes(permission) ?? false,
        hasRole: (role: string) => auth.user?.role?.name === role,
    };
}
