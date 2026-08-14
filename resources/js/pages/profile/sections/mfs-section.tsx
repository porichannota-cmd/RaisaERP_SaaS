import { useForm, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import HeadingSmall from '@/components/heading-small';
import { Checkbox } from '@/components/ui/checkbox';

export default function MfsSection({ mfsAccounts }: { mfsAccounts: Record<string, unknown>[] }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, errors, processing, reset } = useForm({
        provider: '',
        account_name: '',
        mobile_number: '',
        is_primary: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('profile.mfs-accounts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setAdding(false);
            },
        });
    };

    const remove = (id: string) => {
        if(confirm('Remove this MFS account?')) {
            router.delete(route('profile.mfs-accounts.destroy', id), { preserveScroll: true });
        }
    }

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <HeadingSmall title="Mobile Financial Services (MFS)" description="Optional: Add your bKash, Nagad, etc." />
                <Button variant="outline" onClick={() => setAdding(!adding)}>{adding ? 'Cancel' : 'Add MFS Account'}</Button>
            </div>

            {mfsAccounts.length > 0 && (
                <div className="grid gap-4">
                    {mfsAccounts.map(account => (
                        <div key={account.id} className="p-4 border rounded flex justify-between items-center">
                            <div>
                                <p className="font-semibold">{account.provider}</p>
                                <p className="text-sm text-muted-foreground">{account.account_name} - {account.mobile_masked}</p>
                                {account.is_primary && <span className="text-xs bg-primary/10 text-primary px-2 py-1 rounded mt-1 inline-block">Primary</span>}
                            </div>
                            <Button variant="destructive" size="sm" onClick={() => remove(account.id)}>Remove</Button>
                        </div>
                    ))}
                </div>
            )}

            {adding && (
                <form onSubmit={submit} className="space-y-4 border p-4 rounded bg-slate-50 dark:bg-slate-900">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="provider">Provider (e.g. bKash)</Label>
                            <Input id="provider" value={data.provider} onChange={e => setData('provider', e.target.value)} />
                            <InputError message={errors.provider} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="mfs_account_name">Account Name (Optional)</Label>
                            <Input id="mfs_account_name" value={data.account_name} onChange={e => setData('account_name', e.target.value)} />
                            <InputError message={errors.account_name} />
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="mobile_number">Mobile Number</Label>
                            <Input id="mobile_number" value={data.mobile_number} onChange={e => setData('mobile_number', e.target.value)} />
                            <InputError message={errors.mobile_number} />
                        </div>
                        <div className="flex items-center space-x-2 md:col-span-2 mt-2">
                            <Checkbox id="is_mfs_primary" checked={data.is_primary} onCheckedChange={(c: boolean) => setData('is_primary', c)} />
                            <Label htmlFor="is_mfs_primary" className="font-normal cursor-pointer">Set as primary MFS account</Label>
                        </div>
                    </div>
                    <Button disabled={processing}>Save MFS Account</Button>
                </form>
            )}
        </div>
    );
}
