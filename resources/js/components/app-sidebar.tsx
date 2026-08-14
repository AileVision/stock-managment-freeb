import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BarChart3,
    ClipboardList,
    LayoutGrid,
    Package,
    ShieldCheck,
    Tags,
    Truck,
    Users,
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
        {
            title: 'Mouvements',
            href: stock.movements.index(),
            icon: ArrowLeftRight,
        },
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
