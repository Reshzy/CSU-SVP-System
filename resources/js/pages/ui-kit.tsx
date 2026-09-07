import { Head } from '@inertiajs/react';
import { CircleAlert, Palette, TriangleAlert } from 'lucide-react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { uiKit } from '@/routes';

const brandSwatches = [
    { name: 'Maroon', hex: '#800000', className: 'bg-brand-maroon' },
    { name: 'Gold', hex: '#FFD700', className: 'bg-brand-gold' },
    { name: 'Orange', hex: '#FF8C00', className: 'bg-brand-orange' },
    { name: 'Blue', hex: '#1D4ED8', className: 'bg-brand-blue' },
];

const semanticSwatches = [
    { name: 'primary', className: 'bg-primary' },
    { name: 'secondary', className: 'bg-secondary' },
    { name: 'accent', className: 'bg-accent' },
    { name: 'muted', className: 'bg-muted' },
    { name: 'destructive', className: 'bg-destructive' },
];

const fundClusters = [
    { code: '01', label: 'Regular Agency Fund' },
    { code: '05', label: 'Off-Budgetary Fund' },
    { code: '06', label: 'Income Generating Enterprise' },
    { code: '07', label: 'Trust Receipts' },
];

export default function UiKit() {
    return (
        <>
            <Head title="UI kit" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
                <Heading
                    title="CagSU UI kit"
                    description="Reference for the shadcn primitives and CagSU tokens every later slice reuses."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Brand tokens</CardTitle>
                        <CardDescription>
                            Exact CagSU hexes, available as Tailwind utilities.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-4">
                        {brandSwatches.map((swatch) => (
                            <div key={swatch.name} className="space-y-1.5">
                                <div
                                    className={`size-20 rounded-lg border ${swatch.className}`}
                                />
                                <p className="text-sm font-medium">
                                    {swatch.name}
                                </p>
                                <p className="text-muted-foreground font-mono text-xs">
                                    {swatch.hex}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Semantic surfaces</CardTitle>
                        <CardDescription>
                            shadcn variables mapped onto the brand palette.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-4">
                        {semanticSwatches.map((swatch) => (
                            <div key={swatch.name} className="space-y-1.5">
                                <div
                                    className={`size-20 rounded-lg border ${swatch.className}`}
                                />
                                <p className="font-mono text-xs">
                                    {swatch.name}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Buttons and badges</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-3">
                            <Button>Approve</Button>
                            <Button variant="secondary">Return</Button>
                            <Button variant="outline">Export</Button>
                            <Button variant="ghost">Cancel</Button>
                            <Button variant="destructive">Defer</Button>
                            <Button variant="link">View activity log</Button>
                            <Button size="icon" aria-label="Theme">
                                <Palette />
                            </Button>
                        </div>

                        <Separator />

                        <div className="flex flex-wrap items-center gap-2">
                            <Badge>Approved</Badge>
                            <Badge variant="secondary">
                                Supply office review
                            </Badge>
                            <Badge variant="outline">Draft</Badge>
                            <Badge variant="destructive">Deferred</Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Form controls</CardTitle>
                        <CardDescription>
                            Server-side Form Requests remain the validation
                            source of truth.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="pr-title">PR title</Label>
                            <Input
                                id="pr-title"
                                placeholder="Office supplies, Q1"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="fund-cluster">Fund cluster</Label>
                            <Select>
                                <SelectTrigger
                                    id="fund-cluster"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select a fund cluster" />
                                </SelectTrigger>
                                <SelectContent>
                                    {fundClusters.map((cluster) => (
                                        <SelectItem
                                            key={cluster.code}
                                            value={cluster.code}
                                        >
                                            {cluster.code} — {cluster.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Feedback</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Alert>
                            <CircleAlert />
                            <AlertTitle>Budget reserved</AlertTitle>
                            <AlertDescription>
                                The department budget is reserved when a
                                non-draft purchase request is created.
                            </AlertDescription>
                        </Alert>

                        <Alert variant="destructive">
                            <TriangleAlert />
                            <AlertTitle>Quotation exceeds ABC</AlertTitle>
                            <AlertDescription>
                                A unit price above the item estimated unit cost
                                is disqualified.
                            </AlertDescription>
                        </Alert>

                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    toast.success('Purchase request submitted.')
                                }
                            >
                                Show toast
                            </Button>

                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        Open dialog
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Confirm approval
                                        </DialogTitle>
                                        <DialogDescription>
                                            Approving moves the purchase request
                                            to the next workflow step.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <Button variant="outline">
                                            Cancel
                                        </Button>
                                        <Button>Approve</Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <div className="space-y-2">
                            <Skeleton className="h-4 w-1/3" />
                            <Skeleton className="h-4 w-1/2" />
                            <Skeleton className="h-4 w-1/4" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

UiKit.layout = {
    breadcrumbs: [
        {
            title: 'UI kit',
            href: uiKit(),
        },
    ],
};
