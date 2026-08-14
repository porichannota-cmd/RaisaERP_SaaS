import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { router } from '@inertiajs/react';
import { ShieldAlert, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

export default function IdentitySection({
    identityStatus,
}: {
    identityStatus?: { status: string; manualReviewRequired: boolean; maskedNid: string | null };
}) {
    const [isExtracting, setIsExtracting] = useState(false);
    const [isVerifying, setIsVerifying] = useState(false);

    const status = identityStatus?.status || 'NOT_STARTED';

    const handleExtract = () => {
        setIsExtracting(true);
        router.post(
            '/profile/identity-verification/extract',
            {},
            {
                onError: () => {
                    alert('Automatic extraction unavailable.');
                },
                onFinish: () => setIsExtracting(false),
            },
        );
    };

    const handleVerify = () => {
        setIsVerifying(true);
        router.post(
            '/profile/identity-verification/verify',
            {},
            {
                onError: () => {
                    alert('Verification failed or requires manual review.');
                },
                onFinish: () => setIsVerifying(false),
            },
        );
    };

    return (
        <section className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="flex items-center gap-2 text-xl font-semibold">
                        Identity Verification
                        {status === 'VERIFIED' ? (
                            <ShieldCheck className="h-5 w-5 text-green-500" />
                        ) : (
                            <ShieldAlert className="h-5 w-5 text-amber-500" />
                        )}
                    </h2>
                    <p className="text-muted-foreground mt-1 text-sm">Verify your identity to unlock full account features.</p>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">Status: {status.replace(/_/g, ' ')}</CardTitle>
                    {identityStatus?.manualReviewRequired && (
                        <CardDescription className="font-medium text-amber-600">
                            Manual verification/review required. Automatic extraction unavailable.
                        </CardDescription>
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    {identityStatus?.maskedNid && (
                        <div className="bg-muted rounded-md p-3 text-sm">
                            <span className="mr-2 font-semibold">National ID:</span>
                            {identityStatus.maskedNid}
                        </div>
                    )}

                    <div className="flex gap-3">
                        <Button variant="secondary" onClick={handleExtract} disabled={isExtracting || status === 'VERIFIED'}>
                            {isExtracting ? 'Extracting...' : 'Test Extraction'}
                        </Button>

                        <Button variant="outline" onClick={handleVerify} disabled={isVerifying || status === 'VERIFIED'}>
                            {isVerifying ? 'Verifying...' : 'Test Verification'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </section>
    );
}
