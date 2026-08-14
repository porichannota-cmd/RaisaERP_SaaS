import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import HeadingSmall from '@/components/heading-small';
import { Transition } from '@headlessui/react';

export default function PersonalSection({ profile }: { profile: Record<string, unknown> | null }) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        first_name: profile?.first_name || '',
        last_name: profile?.last_name || '',
        date_of_birth: profile?.date_of_birth || '',
        gender: profile?.gender || '',
        nationality: profile?.nationality || 'Bangladesh',
        marital_status: profile?.marital_status || '',
        occupation: profile?.occupation || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.personal.update'));
    };

    return (
        <div className="space-y-6">
            <HeadingSmall title="Personal Information" description="Your basic identity details" />

            <form onSubmit={submit} className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="first_name">First Name</Label>
                        <Input id="first_name" value={data.first_name} onChange={e => setData('first_name', e.target.value)} />
                        <InputError message={errors.first_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="last_name">Last Name</Label>
                        <Input id="last_name" value={data.last_name} onChange={e => setData('last_name', e.target.value)} />
                        <InputError message={errors.last_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="date_of_birth">Date of Birth</Label>
                        <Input type="date" id="date_of_birth" value={data.date_of_birth} onChange={e => setData('date_of_birth', e.target.value)} />
                        <InputError message={errors.date_of_birth} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="gender">Gender</Label>
                        <Input id="gender" value={data.gender} onChange={e => setData('gender', e.target.value)} placeholder="male, female, other" />
                        <InputError message={errors.gender} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="nationality">Nationality</Label>
                        <Input id="nationality" value={data.nationality} onChange={e => setData('nationality', e.target.value)} />
                        <InputError message={errors.nationality} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="marital_status">Marital Status</Label>
                        <Input id="marital_status" value={data.marital_status} onChange={e => setData('marital_status', e.target.value)} />
                        <InputError message={errors.marital_status} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="occupation">Occupation</Label>
                        <Input id="occupation" value={data.occupation} onChange={e => setData('occupation', e.target.value)} />
                        <InputError message={errors.occupation} />
                    </div>
                </div>

                <div className="flex items-center gap-4">
                    <Button disabled={processing}>Save Personal Info</Button>
                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-green-600">Saved.</p>
                    </Transition>
                </div>
            </form>
        </div>
    );
}
