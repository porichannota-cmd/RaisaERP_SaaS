import { useForm, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import HeadingSmall from '@/components/heading-small';
import { Checkbox } from '@/components/ui/checkbox';

export default function BankingSection({ bankAccounts }: { bankAccounts: Record<string, unknown>[] }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, errors, processing, reset } = useForm({
        bank_name: '',
        account_holder_name: '',
        account_number: '',
        is_primary: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('profile.bank-accounts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setAdding(false);
            },
        });
    };

    const remove = (id: string) => {
        if(confirm('Remove this bank account?')) {
            router.delete(route('profile.bank-accounts.destroy', id), { preserveScroll: true });
        }
    }

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <HeadingSmall title="Banking" description="Optional: Add your bank accounts" />
                <Button variant="outline" onClick={() => setAdding(!adding)}>{adding ? 'Cancel' : 'Add Bank Account'}</Button>
            </div>

            {bankAccounts.length > 0 && (
                <div className="grid gap-4">
                    {bankAccounts.map(account => (
                        <div key={account.id} className="p-4 border rounded flex justify-between items-center">
                            <div>
                                <p className="font-semibold">{account.bank_name}</p>
                                <p className="text-sm text-muted-foreground">{account.account_holder_name} - {account.account_number_masked}</p>
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
                            <Label htmlFor="bank_name">Bank Name</Label>
                            <Input id="bank_name" value={data.bank_name} onChange={e => setData('bank_name', e.target.value)} />
                            <InputError message={errors.bank_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="account_holder_name">Account Holder Name</Label>
                            <Input id="account_holder_name" value={data.account_holder_name} onChange={e => setData('account_holder_name', e.target.value)} />
                            <InputError message={errors.account_holder_name} />
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="account_number">Account Number</Label>
                            <Input id="account_number" value={data.account_number} onChange={e => setData('account_number', e.target.value)} />
                            <InputError message={errors.account_number} />
                        </div>
                        <div className="flex items-center space-x-2 md:col-span-2 mt-2">
                            <Checkbox id="is_primary" checked={data.is_primary} onCheckedChange={(c: boolean) => setData('is_primary', c)} />
                            <Label htmlFor="is_primary" className="font-normal cursor-pointer">Set as primary bank account</Label>
                        </div>
                    </div>
                    <Button disabled={processing}>Save Bank Account</Button>
                </form>
            )}
        </div>
    );
}
