import { cn } from '@/lib/utils';

type Tone = 'default' | 'danger' | 'accent' | 'warning' | 'muted';

const TONE_STYLES: Record<Tone, string> = {
    default: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    danger: 'bg-red-500/10 text-red-600 dark:text-red-400',
    accent: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    warning: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    muted: 'bg-gray-500/10 text-gray-600 dark:text-gray-400',
};

export function StatCard({
    icon,
    label,
    value,
    hint,
    tone = 'default',
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    hint?: string;
    tone?: Tone;
}) {
    return (
        <div className="flex items-start gap-3 rounded-xl border p-4">
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-full',
                    TONE_STYLES[tone],
                )}
            >
                {icon}
            </span>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="text-lg font-semibold tabular-nums">{value}</p>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </div>
        </div>
    );
}
