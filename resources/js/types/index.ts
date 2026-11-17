import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { success: string | null };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin: boolean;
    /** Nano-USD. */
    credit_balance: number;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface Option {
    value: string;
    label: string;
}

export type GenerationStatus = 'pending' | 'queued' | 'processing' | 'succeeded' | 'failed';

/** All money fields are integer nano-USD (1 USD = 1e9). */
export interface Generation {
    id: string;
    status: GenerationStatus;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    estimated_cost: number;
    max_cost: number;
    charged_cost: number | null;
    image_url: string | null;
    image_url_expires_at: string | null;
    error_code: string | null;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
}

/** A Laravel API resource collection with pagination. */
export interface ResourceCollection<T> {
    data: T[];
    links: { prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number };
}

/** A plain Laravel paginator. */
export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
