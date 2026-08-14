import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import PersonalSection from './sections/personal-section';
import ContactSection from './sections/contact-section';
import AddressSection from './sections/address-section';
import BankingSection from './sections/banking-section';
import MfsSection from './sections/mfs-section';
import ConsentSection from './sections/consent-section';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile Onboarding',
        href: '/profile',
    },
];

export default function ProfileOnboarding({
    profile,
    contact,
    addresses,
    bankAccounts,
    mfsAccounts,
    consents,
}: {
    profile: Record<string, unknown> | null;
    contact: Record<string, unknown> | null;
    addresses: Record<string, unknown>[];
    bankAccounts: Record<string, unknown>[];
    mfsAccounts: Record<string, unknown>[];
    consents: Record<string, unknown>[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile Onboarding" />
            <div className="max-w-4xl mx-auto p-4 space-y-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Complete your Profile</h1>
                    <p className="text-muted-foreground mt-1">Please provide the following details to complete your account setup.</p>
                </div>

                <div className="space-y-12">
                    <PersonalSection profile={profile} />
                    <hr className="border-border" />

                    <ContactSection contact={contact} />
                    <hr className="border-border" />

                    <AddressSection addresses={addresses} />
                    <hr className="border-border" />

                    <BankingSection bankAccounts={bankAccounts} />
                    <hr className="border-border" />

                    <MfsSection mfsAccounts={mfsAccounts} />
                    <hr className="border-border" />

                    <ConsentSection consents={consents} />
                </div>
            </div>
        </AppLayout>
    );
}
