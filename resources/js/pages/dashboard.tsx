import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Building2, ShieldCheck, UserCheck } from 'lucide-react';

interface DashboardData {
    workspace: {
        id: string;
        name: string;
        status: string;
    };
    membership: {
        status: string;
        access_level: string;
    };
    account: {
        name: string;
        status: string;
    };
}

interface WorkspacePageProps extends SharedData {
    dashboardData: DashboardData;
}

export default function Dashboard() {
    const { dashboardData } = usePage<WorkspacePageProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: dashboardData.workspace.name,
            href: '/dashboard',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={dashboardData.workspace.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4">
                    <h1 className="text-2xl font-bold">
                        Welcome to {dashboardData.workspace.name}
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Enterprise OS Operational Dashboard
                    </p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-2 rounded-xl border p-4">
                        <div className="text-muted-foreground flex items-center gap-2 text-sm font-medium">
                            <Building2 className="h-4 w-4" />
                            Active Workspace
                        </div>
                        <div className="text-2xl font-bold">{dashboardData.workspace.name}</div>
                        <div className="text-muted-foreground text-xs uppercase tracking-wider">
                            Status: {dashboardData.workspace.status}
                        </div>
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-2 rounded-xl border p-4">
                        <div className="text-muted-foreground flex items-center gap-2 text-sm font-medium">
                            <ShieldCheck className="h-4 w-4" />
                            Access Level
                        </div>
                        <div className="text-2xl font-bold">{dashboardData.membership.access_level}</div>
                        <div className="text-muted-foreground text-xs uppercase tracking-wider">
                            Membership: {dashboardData.membership.status}
                        </div>
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-2 rounded-xl border p-4">
                        <div className="text-muted-foreground flex items-center gap-2 text-sm font-medium">
                            <UserCheck className="h-4 w-4" />
                            Account Status
                        </div>
                        <div className="text-2xl font-bold">{dashboardData.account.status}</div>
                        <div className="text-muted-foreground text-xs uppercase tracking-wider">
                            User: {dashboardData.account.name}
                        </div>
                    </div>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border bg-sidebar/30 flex min-h-[400px] flex-1 flex-col items-center justify-center rounded-xl border p-8 text-center md:min-h-min">
                    <h3 className="text-lg font-semibold">ERP modules will appear here as they are activated.</h3>
                    <p className="text-muted-foreground mt-2 max-w-sm text-sm">
                        This workspace is currently operating with foundation capabilities. Subscribe to business modules to unlock inventory, sales, and accounting features.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
