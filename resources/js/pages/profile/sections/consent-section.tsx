import { useForm, router } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import HeadingSmall from '@/components/heading-small';
import { Checkbox } from '@/components/ui/checkbox';

export default function ConsentSection({ consents }: { consents: Record<string, unknown>[] }) {
    const marketingConsent = consents.find(c => c.consent_type === 'MARKETING');
    const isMarketingAccepted = marketingConsent && !marketingConsent.revoked_at;

    const { post, processing } = useForm({
        consent_type: 'MARKETING',
        document_version: 'v1.0',
    });

    const toggleMarketing = (checked: boolean) => {
        if (checked) {
            post(route('profile.consents.grant'), { preserveScroll: true });
        } else {
            router.delete(route('profile.consents.revoke', 'MARKETING'), { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6">
            <HeadingSmall title="Consents" description="Manage your preferences and agreements" />

            <div className="space-y-4 border p-4 rounded-md">
                <div className="flex items-start space-x-3">
                    <Checkbox
                        id="marketing_consent"
                        checked={!!isMarketingAccepted}
                        onCheckedChange={toggleMarketing}
                        disabled={processing}
                    />
                    <div className="grid gap-1.5 leading-none">
                        <Label htmlFor="marketing_consent" className="font-medium cursor-pointer">Marketing Communications (Optional)</Label>
                        <p className="text-sm text-muted-foreground">
                            I consent to receive marketing emails and SMS about new features and offers.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
