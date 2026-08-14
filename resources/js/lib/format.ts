import type {
    MovementType,
    PaymentMethod,
    QuoteStatus,
    SaleStatus,
} from '@/types';

const CURRENCY = (import.meta.env.VITE_CURRENCY as string | undefined) || 'XOF';

export function formatCurrency(
    value: number | string | null | undefined,
    maximumFractionDigits = 0,
): string {
    const num =
        typeof value === 'string' ? parseFloat(value) : Number(value ?? 0);

    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: CURRENCY,
        minimumFractionDigits: maximumFractionDigits > 0 ? 2 : 0,
        maximumFractionDigits,
    }).format(Number.isFinite(num) ? num : 0);
}

export function formatNumber(value: number): string {
    return new Intl.NumberFormat('fr-FR').format(
        Number.isFinite(value) ? value : 0,
    );
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export const UNIT_LABELS: Record<string, string> = {
    pcs: 'pièce(s)',
    box: 'boîte(s)',
    pack: 'paquet(s)',
    kg: 'kg',
    g: 'g',
    l: 'L',
    ml: 'mL',
    m: 'm',
};

export function unitLabel(unit: string): string {
    return UNIT_LABELS[unit] ?? unit;
}

export const MOVEMENT_TYPE_LABELS: Record<MovementType, string> = {
    in: 'Entrée',
    out: 'Sortie',
    adjustment: 'Ajustement',
};

export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
    cash: 'Espèces',
    card: 'Carte bancaire',
    transfer: 'Virement',
};

export const SALE_STATUS_LABELS: Record<SaleStatus, string> = {
    completed: 'Validée',
    cancelled: 'Annulée',
};

export const QUOTE_STATUS_LABELS: Record<QuoteStatus, string> = {
    draft: 'Brouillon',
    sent: 'Envoyé',
    accepted: 'Accepté',
    converted: 'Converti',
    rejected: 'Refusé',
};

export function formatDateOnly(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
    }).format(new Date(value));
}
