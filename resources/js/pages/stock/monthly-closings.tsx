import { Head, router, useForm } from '@inertiajs/react';
import { Calendar, Download, Eye, FileText, Plus, XCircle } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { formatCurrency, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type { Auth, MonthlyClosing, MonthlyClosingPreview } from '@/types';

type Props = {
    closings: MonthlyClosing[];
    proposal: {
        year: number;
        month: number;
        label: string;
    } | null;
};

const MONTHS = [
    '', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
];

function ResultBadge({ value }: { value: number }) {
    if (value > 0) {
        return <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">+{formatCurrency(value)}</Badge>;
    }
    if (value < 0) {
        return <Badge className="bg-red-500/10 text-red-600 dark:text-red-400">{formatCurrency(value)}</Badge>;
    }
    return <Badge className="bg-gray-500/10 text-gray-600 dark:text-gray-400">{formatCurrency(0)}</Badge>;
}

export default function MonthlyClosings({ closings, proposal }: Props) {
    const [closeDialogOpen, setCloseDialogOpen] = useState(false);
    const [previewDialogOpen, setPreviewDialogOpen] = useState(false);
    const [previewData, setPreviewData] = useState<MonthlyClosingPreview | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);

    const form = useForm({
        year: proposal?.year ?? new Date().getFullYear(),
        month: proposal?.month ?? new Date().getMonth(),
        notes: '',
    });

    const openCloseDialog = () => {
        form.setData({
            year: proposal?.year ?? new Date().getFullYear(),
            month: proposal?.month ?? new Date().getMonth(),
            notes: '',
        });
        setCloseDialogOpen(true);
    };

    const openPreview = (year: number, month: number) => {
        setPreviewLoading(true);
        setPreviewDialogOpen(true);
        fetch(stock.monthlyClosings.preview({ query: { year, month } }).url)
            .then((res) => res.json())
            .then((data: MonthlyClosingPreview) => {
                setPreviewData(data);
                setPreviewLoading(false);
            })
            .catch(() => setPreviewLoading(false));
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.post(stock.monthlyClosings.store().url, {
            onSuccess: () => setCloseDialogOpen(false),
        });
    };

    const totalRevenue = closings.reduce((sum, c) => sum + c.revenue, 0);
    const totalExpenses = closings.reduce((sum, c) => sum + c.expenses, 0);
    const totalNetResult = closings.reduce((sum, c) => sum + c.net_result, 0);

    return (
        <>
            <Head title="Clôture comptable mensuelle" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Clôture comptable mensuelle</h1>
                        <p className="text-muted-foreground">
                            Bilan mensuel des revenus, achats, dépenses et TVA.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => window.location.href = stock.monthlyClosings.export().url}>
                            <Download className="mr-2 h-4 w-4" />
                            Export CSV
                        </Button>
                        {proposal && (
                            <Button onClick={openCloseDialog}>
                                <Plus className="mr-2 h-4 w-4" />
                                Clôturer {proposal.label}
                            </Button>
                        )}
                    </div>
                </div>

                {/* Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">CA total (TTC)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(totalRevenue)}</div>
                            <p className="text-xs text-muted-foreground">{closings.length} mois clôturé(s)</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Dépenses totales</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">{formatCurrency(totalExpenses)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Résultat net total</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className={cn("text-2xl font-bold", totalNetResult >= 0 ? "text-emerald-600" : "text-red-600")}>
                                {formatCurrency(totalNetResult)}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Historique des clôtures</CardTitle>
                        <CardDescription>
                            Consultez les bilans mensuels passés et téléchargez les PDF.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Mois</TableHead>
                                    <TableHead className="text-right">CA TTC</TableHead>
                                    <TableHead className="text-right">CA HT</TableHead>
                                    <TableHead className="text-right">Achats TTC</TableHead>
                                    <TableHead className="text-right">Dépenses</TableHead>
                                    <TableHead className="text-right">TVA collectée</TableHead>
                                    <TableHead className="text-right">TVA déductible</TableHead>
                                    <TableHead className="text-right">TVA nette</TableHead>
                                    <TableHead className="text-right">Marge brute</TableHead>
                                    <TableHead className="text-right">Résultat net</TableHead>
                                    <TableHead>Clôturé le</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {closings.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={12} className="text-center text-muted-foreground py-8">
                                            Aucune clôture comptable effectuée.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    closings.map((closing) => (
                                        <TableRow key={closing.id}>
                                            <TableCell className="font-medium">
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                                    {closing.label}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {formatCurrency(closing.revenue)}
                                            </TableCell>
                                            <TableCell className="text-right">{formatCurrency(closing.revenue_ht)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(closing.purchases)}</TableCell>
                                            <TableCell className="text-right text-orange-600">{formatCurrency(closing.expenses)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(closing.vat_collected)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(closing.vat_deductible)}</TableCell>
                                            <TableCell className="text-right font-medium">{formatCurrency(closing.vat_net)}</TableCell>
                                            <TableCell className="text-right">{formatCurrency(closing.gross_margin)}</TableCell>
                                            <TableCell className="text-right">
                                                <ResultBadge value={closing.net_result} />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {formatDate(closing.closed_at)}
                                                {closing.user && <span className="ml-1">— {closing.user.name}</span>}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Aperçu"
                                                        onClick={() => openPreview(closing.year, closing.month)}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Télécharger le PDF"
                                                        onClick={() => window.location.href = stock.monthlyClosings.pdf({ closing: closing.id }).url}
                                                    >
                                                        <FileText className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Close dialog */}
            <Dialog open={closeDialogOpen} onOpenChange={setCloseDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Clôturer un mois</DialogTitle>
                        <DialogDescription>
                            Cette action fige les données financières du mois sélectionné. Elle est irréversible.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Année</Label>
                                <Input
                                    type="number"
                                    value={form.data.year}
                                    onChange={(e) => form.setData('year', parseInt(e.target.value))}
                                    min={2020}
                                    max={new Date().getFullYear() + 1}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Mois</Label>
                                <select
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors"
                                    value={form.data.month}
                                    onChange={(e) => form.setData('month', parseInt(e.target.value))}
                                    required
                                >
                                    {MONTHS.map((name, i) =>
                                        i > 0 ? <option key={i} value={i}>{name}</option> : null
                                    )}
                                </select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label>Remarques (optionnel)</Label>
                            <textarea
                                className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                value={form.data.notes}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => form.setData('notes', e.target.value)}
                                rows={3}
                                placeholder="Notes sur cette clôture..."
                            />
                        </div>
                        {proposal && (
                            <p className="text-sm text-muted-foreground">
                                Prochain mois à clôturer : <strong>{proposal.label}</strong>
                            </p>
                        )}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCloseDialogOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Clôture en cours...' : 'Clôturer'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Preview dialog */}
            <Dialog open={previewDialogOpen} onOpenChange={setPreviewDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Aperçu avant clôture</DialogTitle>
                        <DialogDescription>
                            Données financières du mois sélectionné.
                        </DialogDescription>
                    </DialogHeader>
                    {previewLoading ? (
                        <div className="flex items-center justify-center py-8 text-muted-foreground">
                            Chargement...
                        </div>
                    ) : previewData ? (
                        <div className="space-y-4">
                            {previewData.already_closed && (
                                <div className="flex items-center gap-2 rounded-md bg-yellow-500/10 p-3 text-sm text-yellow-600 dark:text-yellow-400">
                                    <XCircle className="h-4 w-4" />
                                    Ce mois est déjà clôturé.
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div><span className="text-muted-foreground">CA TTC :</span> <strong>{formatCurrency(previewData.revenue)}</strong></div>
                                <div><span className="text-muted-foreground">CA HT :</span> <strong>{formatCurrency(previewData.revenue_ht)}</strong></div>
                                <div><span className="text-muted-foreground">Ventes :</span> {previewData.sales_count}</div>
                                <div><span className="text-muted-foreground">Crédit exclu :</span> {formatCurrency(previewData.credit_excluded)} ({previewData.credit_excluded_count})</div>
                                <div><span className="text-muted-foreground">Achats TTC :</span> {formatCurrency(previewData.purchases_ttc)}</div>
                                <div><span className="text-muted-foreground">Achats HT :</span> {formatCurrency(previewData.purchases_ht)}</div>
                                <div><span className="text-muted-foreground">Dépenses :</span> <span className="text-orange-600">{formatCurrency(previewData.expenses)}</span> ({previewData.expenses_count})</div>
                                <div><span className="text-muted-foreground">TVA collectée :</span> {formatCurrency(previewData.vat_collected)}</div>
                                <div><span className="text-muted-foreground">TVA déductible :</span> {formatCurrency(previewData.vat_deductible)}</div>
                                <div><span className="text-muted-foreground">TVA nette :</span> <strong>{formatCurrency(previewData.vat_net)}</strong></div>
                                <div><span className="text-muted-foreground">Marge brute :</span> <strong>{formatCurrency(previewData.gross_margin)}</strong></div>
                                <div><span className="text-muted-foreground">Résultat net :</span> <strong className={previewData.net_result >= 0 ? "text-emerald-600" : "text-red-600"}>{formatCurrency(previewData.net_result)}</strong></div>
                            </div>
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}
