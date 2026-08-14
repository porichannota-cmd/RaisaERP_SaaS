import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import HeadingSmall from '@/components/heading-small';
import { Transition } from '@headlessui/react';

export default function ContactSection({ contact }: { contact: Record<string, unknown> | null }) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        secondary_mobile: contact?.secondary_mobile || '',
        secondary_email: contact?.secondary_email || '',
        whatsapp_mobile: contact?.whatsapp_mobile || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.contact.update'));
    };

    return (
        <div className="space-y-6">
            <HeadingSmall title="Contact Details" description="Alternative ways to reach you" />

            <form onSubmit={submit} className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="secondary_mobile">Secondary Mobile</Label>
                        <Input id="secondary_mobile" value={data.secondary_mobile} onChange={e => setData('secondary_mobile', e.target.value)} />
                        <InputError message={errors.secondary_mobile} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="whatsapp_mobile">WhatsApp Number</Label>
                        <Input id="whatsapp_mobile" value={data.whatsapp_mobile} onChange={e => setData('whatsapp_mobile', e.target.value)} />
                        <InputError message={errors.whatsapp_mobile} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="secondary_email">Secondary Email</Label>
                        <Input id="secondary_email" type="email" value={data.secondary_email} onChange={e => setData('secondary_email', e.target.value)} />
                        <InputError message={errors.secondary_email} />
                    </div>
                </div>

                <div className="flex items-center gap-4">
                    <Button disabled={processing}>Save Contact Info</Button>
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
