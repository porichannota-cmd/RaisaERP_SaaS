import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

interface Workspace {
    id: string;
    name: string;
    status: string;
}

interface WorkspaceIndexProps {
    workspaces: Workspace[];
}

export default function WorkspaceIndex({ workspaces }: WorkspaceIndexProps) {
    const [loadingId, setLoadingId] = useState<string | null>(null);

    const handleSwitch = (tenantId: string) => {
        setLoadingId(tenantId);
        router.post(route('workspaces.switch'), { tenant_id: tenantId }, {
            onFinish: () => setLoadingId(null)
        });
    };

    return (
        <AppLayout>
            <Head title="My Workspaces" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h2 className="text-2xl font-semibold mb-6">My Workspaces</h2>

                            {workspaces.length === 0 ? (
                                <div className="text-center py-10">
                                    <p className="text-gray-500 mb-4">You do not have any active workspaces.</p>
                                    <Link
                                        href={route('business.setup')}
                                        className="text-blue-600 hover:underline"
                                    >
                                        Set up a new Business
                                    </Link>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {workspaces.map((workspace) => (
                                        <div
                                            key={workspace.id}
                                            className="border rounded-lg p-6 hover:shadow-md transition-shadow"
                                        >
                                            <h3 className="text-xl font-medium mb-2">{workspace.name}</h3>
                                            <p className="text-sm text-gray-500 mb-4">
                                                Status: <span className="capitalize">{workspace.status}</span>
                                            </p>
                                            <button
                                                onClick={() => handleSwitch(workspace.id)}
                                                disabled={loadingId === workspace.id}
                                                className="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 disabled:opacity-50"
                                            >
                                                Enter Workspace
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
