import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

export default function Setup({ profile, is_ready, is_provisioned }) {
    const profileForm = useForm({
        legal_name: profile?.legal_name || '',
        display_name: profile?.display_name || '',
        trade_license: '',
        tin: '',
        bin: '',
    });

    const addressForm = useForm({
        address_line_1: profile?.address?.address_line_1 || '',
        address_line_2: profile?.address?.address_line_2 || '',
        city: profile?.address?.city || '',
        state: profile?.address?.state || '',
        postal_code: profile?.address?.postal_code || '',
        country: profile?.address?.country || 'BD',
    });

    const readyForm = useForm({});
    const provisionForm = useForm({});

    const submitProfile = (e) => {
        e.preventDefault();
        profileForm.post(route('business.profile.save'), {
            preserveScroll: true,
        });
    };

    const submitAddress = (e) => {
        e.preventDefault();
        addressForm.post(route('business.address.save'), {
            preserveScroll: true,
        });
    };

    const submitReady = (e) => {
        e.preventDefault();
        readyForm.post(route('business.ready'), {
            preserveScroll: true,
        });
    };

    const submitProvision = (e) => {
        e.preventDefault();
        provisionForm.post(route('business.provision'));
    };

    return (
        <AppLayout
            header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Business Setup</h2>}
        >
            <Head title="Business Setup" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {/* Status Alert */}
                    <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">Status</h3>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Current Status: <strong className="uppercase">{profile?.provisioning_status || 'DRAFT'}</strong>
                        </p>
                    </div>

                    {is_provisioned ? (
                        <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-green-600 dark:text-green-400">Workspace Provisioned Successfully!</h3>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Your business workspace is ready. You can now switch to the tenant workspace.
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* Profile Details */}
                            <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">Business Details</h3>
                                <form onSubmit={submitProfile} className="mt-6 space-y-6">
                                    <div>
                                        <InputLabel htmlFor="legal_name" value="Legal Name" />
                                        <TextInput
                                            id="legal_name"
                                            className="mt-1 block w-full"
                                            value={profileForm.data.legal_name}
                                            onChange={(e) => profileForm.setData('legal_name', e.target.value)}
                                            required
                                        />
                                        <InputError className="mt-2" message={profileForm.errors.legal_name} />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="display_name" value="Display Name (Optional)" />
                                        <TextInput
                                            id="display_name"
                                            className="mt-1 block w-full"
                                            value={profileForm.data.display_name}
                                            onChange={(e) => profileForm.setData('display_name', e.target.value)}
                                        />
                                        <InputError className="mt-2" message={profileForm.errors.display_name} />
                                    </div>

                                    <h4 className="text-md font-medium text-gray-900 dark:text-gray-100 mt-4">Legal Identifiers (Optional)</h4>
                                    
                                    <div>
                                        <InputLabel htmlFor="trade_license" value="Trade License" />
                                        <TextInput
                                            id="trade_license"
                                            className="mt-1 block w-full"
                                            value={profileForm.data.trade_license}
                                            onChange={(e) => profileForm.setData('trade_license', e.target.value)}
                                            placeholder={profile?.trade_license_fingerprint ? '••• Provided •••' : ''}
                                        />
                                        <InputError className="mt-2" message={profileForm.errors.trade_license} />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="tin" value="TIN" />
                                        <TextInput
                                            id="tin"
                                            className="mt-1 block w-full"
                                            value={profileForm.data.tin}
                                            onChange={(e) => profileForm.setData('tin', e.target.value)}
                                            placeholder={profile?.tin_fingerprint ? '••• Provided •••' : ''}
                                        />
                                        <InputError className="mt-2" message={profileForm.errors.tin} />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="bin" value="BIN" />
                                        <TextInput
                                            id="bin"
                                            className="mt-1 block w-full"
                                            value={profileForm.data.bin}
                                            onChange={(e) => profileForm.setData('bin', e.target.value)}
                                            placeholder={profile?.bin_fingerprint ? '••• Provided •••' : ''}
                                        />
                                        <InputError className="mt-2" message={profileForm.errors.bin} />
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <PrimaryButton disabled={profileForm.processing}>Save Profile</PrimaryButton>
                                    </div>
                                </form>
                            </div>

                            {/* Address Details */}
                            {profile && (
                                <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">Business Address</h3>
                                    <form onSubmit={submitAddress} className="mt-6 space-y-6">
                                        <div>
                                            <InputLabel htmlFor="address_line_1" value="Address Line 1" />
                                            <TextInput
                                                id="address_line_1"
                                                className="mt-1 block w-full"
                                                value={addressForm.data.address_line_1}
                                                onChange={(e) => addressForm.setData('address_line_1', e.target.value)}
                                                required
                                            />
                                            <InputError className="mt-2" message={addressForm.errors.address_line_1} />
                                        </div>

                                        <div>
                                            <InputLabel htmlFor="address_line_2" value="Address Line 2 (Optional)" />
                                            <TextInput
                                                id="address_line_2"
                                                className="mt-1 block w-full"
                                                value={addressForm.data.address_line_2}
                                                onChange={(e) => addressForm.setData('address_line_2', e.target.value)}
                                            />
                                            <InputError className="mt-2" message={addressForm.errors.address_line_2} />
                                        </div>

                                        <div>
                                            <InputLabel htmlFor="city" value="City" />
                                            <TextInput
                                                id="city"
                                                className="mt-1 block w-full"
                                                value={addressForm.data.city}
                                                onChange={(e) => addressForm.setData('city', e.target.value)}
                                                required
                                            />
                                            <InputError className="mt-2" message={addressForm.errors.city} />
                                        </div>

                                        <div>
                                            <InputLabel htmlFor="state" value="State/Division" />
                                            <TextInput
                                                id="state"
                                                className="mt-1 block w-full"
                                                value={addressForm.data.state}
                                                onChange={(e) => addressForm.setData('state', e.target.value)}
                                                required
                                            />
                                            <InputError className="mt-2" message={addressForm.errors.state} />
                                        </div>

                                        <div>
                                            <InputLabel htmlFor="postal_code" value="Postal Code" />
                                            <TextInput
                                                id="postal_code"
                                                className="mt-1 block w-full"
                                                value={addressForm.data.postal_code}
                                                onChange={(e) => addressForm.setData('postal_code', e.target.value)}
                                                required
                                            />
                                            <InputError className="mt-2" message={addressForm.errors.postal_code} />
                                        </div>

                                        <div>
                                            <InputLabel htmlFor="country" value="Country" />
                                            <TextInput
                                                id="country"
                                                className="mt-1 block w-full"
                                                value={addressForm.data.country}
                                                onChange={(e) => addressForm.setData('country', e.target.value)}
                                                disabled
                                            />
                                            <InputError className="mt-2" message={addressForm.errors.country} />
                                        </div>

                                        <div className="flex items-center gap-4">
                                            <PrimaryButton disabled={addressForm.processing}>Save Address</PrimaryButton>
                                        </div>
                                    </form>
                                </div>
                            )}

                            {/* Readiness and Provisioning */}
                            {profile?.address && !is_ready && (
                                <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">Review & Submit</h3>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Once your profile and address are complete, evaluate readiness to proceed to provisioning.
                                    </p>
                                    <form onSubmit={submitReady} className="mt-4">
                                        <PrimaryButton disabled={readyForm.processing}>Mark as Ready</PrimaryButton>
                                    </form>
                                </div>
                            )}

                            {is_ready && (
                                <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 border-b pb-2 border-gray-200 dark:border-gray-700">Provision Workspace</h3>
                                    <p className="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                        Your business is ready! Provision your tenant workspace to get started.
                                    </p>
                                    <form onSubmit={submitProvision} className="mt-6">
                                        <PrimaryButton disabled={provisionForm.processing}>Provision Workspace Now</PrimaryButton>
                                    </form>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
