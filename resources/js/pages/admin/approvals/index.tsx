import { Head, useForm, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    account_status: string;
}

interface ReviewRequest {
    id: string;
    status: string;
    submitted_at: string;
    user: User;
}

interface PaginatedRequests {
    data: ReviewRequest[];
    links: { url: string | null; label: string; active: boolean }[];
}

export default function ApprovalsIndex({ requests }: { requests: PaginatedRequests }) {
    const { processing } = useForm();
    const [rejectReason, setRejectReason] = useState<Record<string, string>>({});

    const handleApprove = (id: string) => {
        router.post(route('admin.approvals.approve', id));
    };

    const handleReject = (id: string) => {
        const reason = rejectReason[id] || '';
        if (!reason.trim()) {
            alert('A reason is required for rejection.');
            return;
        }

        router.post(route('admin.approvals.reject', id), {
            reason: reason,
        });
    };

    return (
        <div className="p-8">
            <Head title="Account Approvals Queue" />

            <h1 className="mb-6 text-2xl font-bold">Pending Account Approvals</h1>

            {requests.data.length === 0 ? (
                <div className="p-4 text-center bg-gray-100 rounded">
                    No pending approval requests.
                </div>
            ) : (
                <div className="overflow-hidden bg-white shadow sm:rounded-md">
                    <ul role="list" className="divide-y divide-gray-200">
                        {requests.data.map((request) => (
                            <li key={request.id} className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-lg font-medium text-gray-900">
                                            {request.user.name} ({request.user.email || 'No Email'})
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-500">
                                            Submitted on {new Date(request.submitted_at).toLocaleString()}
                                        </p>
                                    </div>
                                    <div className="flex items-center space-x-4">
                                        <button
                                            onClick={() => handleApprove(request.id)}
                                            disabled={processing}
                                            className="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700"
                                        >
                                            Approve
                                        </button>
                                        
                                        <div className="flex items-center space-x-2">
                                            <input 
                                                type="text" 
                                                placeholder="Rejection reason..." 
                                                className="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                value={rejectReason[request.id] || ''}
                                                onChange={(e) => setRejectReason({ ...rejectReason, [request.id]: e.target.value })}
                                            />
                                            <button
                                                onClick={() => handleReject(request.id)}
                                                disabled={processing}
                                                className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700"
                                            >
                                                Reject
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
