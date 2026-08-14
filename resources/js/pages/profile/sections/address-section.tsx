import { useForm, router } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import HeadingSmall from '@/components/heading-small';
import { Transition } from '@headlessui/react';

export default function AddressSection({ addresses }: { addresses: Record<string, unknown>[] }) {
    const present = addresses.find(a => a.type === 'PRESENT') || {};
    const permanent = addresses.find(a => a.type === 'PERMANENT') || {};

    const { data: pData, setData: setPData, post: postPresent, errors: pErrors, processing: pProcessing, recentlySuccessful: pSuccess } = useForm({
        type: 'PRESENT',
        country: present.country || 'Bangladesh',
        division: present.division || '',
        district: present.district || '',
        upazila_thana: present.upazila_thana || '',
        address_line_1: present.address_line_1 || '',
    });

    const { data: permData, setData: setPermData, post: postPerm, errors: permErrors, processing: permProcessing, recentlySuccessful: permSuccess } = useForm({
        type: 'PERMANENT',
        country: permanent.country || 'Bangladesh',
        division: permanent.division || '',
        district: permanent.district || '',
        upazila_thana: permanent.upazila_thana || '',
        address_line_1: permanent.address_line_1 || '',
    });

    const submitPresent: FormEventHandler = (e) => {
        e.preventDefault();
        postPresent(route('profile.addresses.upsert'));
    };

    const submitPermanent: FormEventHandler = (e) => {
        e.preventDefault();
        postPerm(route('profile.addresses.upsert'));
    };

    const copyPresent = () => {
        router.post(route('profile.addresses.copy-present'), {}, { preserveScroll: true });
    };

    return (
        <div className="space-y-8">
            <HeadingSmall title="Address Information" description="Where you live and are registered" />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                <form onSubmit={submitPresent} className="space-y-4 border p-4 rounded-md">
                    <h3 className="font-semibold text-lg">Present Address</h3>
                    <div className="grid gap-2">
                        <Label>Division</Label>
                        <Input value={pData.division} onChange={e => setPData('division', e.target.value)} />
                        <InputError message={pErrors.division} />
                    </div>
                    <div className="grid gap-2">
                        <Label>District</Label>
                        <Input value={pData.district} onChange={e => setPData('district', e.target.value)} />
                        <InputError message={pErrors.district} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Thana</Label>
                        <Input value={pData.upazila_thana} onChange={e => setPData('upazila_thana', e.target.value)} />
                        <InputError message={pErrors.upazila_thana} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Address Line 1</Label>
                        <Input value={pData.address_line_1} onChange={e => setPData('address_line_1', e.target.value)} />
                        <InputError message={pErrors.address_line_1} />
                    </div>
                    <div className="flex items-center gap-4">
                        <Button disabled={pProcessing}>Save Present</Button>
                        <Transition show={pSuccess} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                            <p className="text-sm text-green-600">Saved.</p>
                        </Transition>
                    </div>
                </form>

                <form onSubmit={submitPermanent} className="space-y-4 border p-4 rounded-md relative">
                    <div className="flex justify-between items-center">
                        <h3 className="font-semibold text-lg">Permanent Address</h3>
                        <Button type="button" variant="outline" size="sm" onClick={copyPresent}>Same as Present</Button>
                    </div>

                    <div className="grid gap-2">
                        <Label>Division</Label>
                        <Input value={permData.division} onChange={e => setPermData('division', e.target.value)} />
                        <InputError message={permErrors.division} />
                    </div>
                    <div className="grid gap-2">
                        <Label>District</Label>
                        <Input value={permData.district} onChange={e => setPermData('district', e.target.value)} />
                        <InputError message={permErrors.district} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Thana</Label>
                        <Input value={permData.upazila_thana} onChange={e => setPermData('upazila_thana', e.target.value)} />
                        <InputError message={permErrors.upazila_thana} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Address Line 1</Label>
                        <Input value={permData.address_line_1} onChange={e => setPermData('address_line_1', e.target.value)} />
                        <InputError message={permErrors.address_line_1} />
                    </div>
                    <div className="flex items-center gap-4">
                        <Button disabled={permProcessing}>Save Permanent</Button>
                        <Transition show={permSuccess} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                            <p className="text-sm text-green-600">Saved.</p>
                        </Transition>
                    </div>
                </form>
            </div>
        </div>
    );
}
