import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowRightLeft,
    BarChart3,
    ClipboardList,
    Contact,
    FileCheck2,
    FileText,
    HandCoins,
    HardDriveDownload,
    Landmark,
    LayoutGrid,
    Package,
    PackagePlus,
    Percent,
    RotateCcw,
    ShieldCheck,
    ShoppingCart,
    Store,
    Tags,
    TrendingUp,
    Truck,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import backups from '@/routes/backups';
import { dashboard } from '@/routes';
import roles from '@/routes/roles';
import stock from '@/routes/stock';
import users from '@/routes/users';
import type { Auth, NavItem } from '@/types';

export function AppSidebar() {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;

    const mainNavItems: NavItem[] = [
        {
            title: 'Tableau de bord',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Produits',
            href: stock.products.index(),
            icon: Package,
        },
        {
            title: 'Catégories',
            href: stock.categories.index(),
            icon: Tags,
        },
        {
            title: 'Fournisseurs',
            href: stock.suppliers.index(),
            icon: Truck,
        },
        ...(permissions.includes('customers.view')
            ? [
                  {
                      title: 'Clients',
                      href: stock.customers.index(),
                      icon: Contact,
                  },
              ]
            : []),
        ...(permissions.includes('warehouses.view')
            ? [
                  {
                      title: 'Entrepôts',
                      href: stock.warehouses.index(),
                      icon: Warehouse,
                  },
              ]
            : []),
        ...(permissions.includes('transfers.view')
            ? [
                  {
                      title: 'Transferts',
                      href: stock.transfers.index(),
                      icon: ArrowRightLeft,
                  },
              ]
            : []),
        {
            title: 'Mouvements',
            href: stock.movements.index(),
            icon: ArrowLeftRight,
        },
        ...(permissions.includes('purchase_orders.view')
            ? [
                  {
                      title: 'Commandes fournisseur',
                      href: stock.purchaseOrders.index(),
                      icon: PackagePlus,
                  },
              ]
            : []),
        ...(permissions.includes('sales.create')
            ? [
                  {
                      title: 'Caisse',
                      href: stock.sales.pos(),
                      icon: Store,
                  },
              ]
            : []),
        ...(permissions.includes('sales.view')
            ? [
                  {
                      title: 'Ventes',
                      href: stock.sales.index(),
                      icon: ShoppingCart,
                  },
              ]
            : []),
        ...(permissions.includes('receivables.view')
            ? [
                  {
                      title: 'Créances',
                      href: stock.receivables.index(),
                      icon: HandCoins,
                  },
              ]
            : []),
        ...(permissions.includes('returns.view')
            ? [
                  {
                      title: 'Retours & avoirs',
                      href: stock.returns.index(),
                      icon: RotateCcw,
                  },
              ]
            : []),
        ...(permissions.includes('quotes.view')
            ? [
                  {
                      title: 'Devis',
                      href: stock.quotes.index(),
                      icon: FileText,
                  },
              ]
            : []),
        ...(permissions.includes('invoices.view')
            ? [
                  {
                      title: 'Factures',
                      href: stock.invoices.index(),
                      icon: FileCheck2,
                  },
              ]
            : []),
        ...(permissions.includes('sessions.view')
            ? [
                  {
                      title: 'Sessions de caisse',
                      href: stock.sessions.index(),
                      icon: Wallet,
                  },
              ]
            : []),
        ...(permissions.includes('replenishment.view')
            ? [
                  {
                      title: 'Réapprovisionnement',
                      href: stock.replenishment.index(),
                      icon: ClipboardList,
                  },
              ]
            : []),
        ...(permissions.includes('reports.view')
            ? [
                  {
                      title: 'Rapports',
                      href: stock.reports.index(),
                      icon: BarChart3,
                  },
                  {
                      title: 'Rapport de ventes',
                      href: stock.reports.sales(),
                      icon: TrendingUp,
                  },
                  {
                      title: 'Rapport financier',
                      href: stock.reports.financial(),
                      icon: Landmark,
                  },
              ]
            : []),
        ...(permissions.includes('taxes.view')
            ? [
                  {
                      title: 'TVA',
                      href: stock.taxes.index(),
                      icon: Percent,
                  },
              ]
            : []),
    ];

    const adminNavItems: NavItem[] = [
        ...(permissions.includes('users.manage')
            ? [
                  {
                      title: 'Utilisateurs',
                      href: users.index(),
                      icon: Users,
                  },
              ]
            : []),
        ...(permissions.includes('roles.manage')
            ? [
                  {
                      title: 'Rôles & permissions',
                      href: roles.index(),
                      icon: ShieldCheck,
                  },
              ]
            : []),
        ...(permissions.includes('backups.manage')
            ? [
                  {
                      title: 'Sauvegardes',
                      href: backups.index(),
                      icon: HardDriveDownload,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label="Navigation" />
                {adminNavItems.length > 0 && (
                    <NavMain items={adminNavItems} label="Administration" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
