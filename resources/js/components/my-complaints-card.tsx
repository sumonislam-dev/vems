import { Link } from '@inertiajs/react';
import { MessagesSquare, PlusCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export interface MyComplaintSummary {
    id: number;
    subject: string;
    type: 'feedback' | 'complaint';
    status: string;
    priority: string;
    created_at: string;
}

export interface MyComplaintsCardProps {
    complaints: {
        counts: { open: number; total: number };
        recent: MyComplaintSummary[];
    };
}

const complaintStatusColor = (status: string) => {
    switch (status) {
        case 'open': return 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300';
        case 'in_review': return 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300';
        case 'resolved':
        case 'closed': return 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300';
        default: return 'bg-slate-100 text-slate-800 dark:bg-slate-500/15 dark:text-slate-300';
    }
};

export function MyComplaintsCard({ complaints }: MyComplaintsCardProps) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle className="flex items-center">
                        <MessagesSquare className="w-5 h-5 mr-2" />
                        My Complaints
                    </CardTitle>
                    <CardDescription>Feedback & complaints you've submitted</CardDescription>
                </div>
                <Button asChild variant="ghost" size="sm">
                    <Link href={route('complaints.index')}>View all</Link>
                </Button>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-4 text-center">
                    <div className="rounded-lg border p-3">
                        <p className="text-2xl font-bold text-foreground">{complaints.counts.open}</p>
                        <p className="text-xs text-muted-foreground">Open</p>
                    </div>
                    <div className="rounded-lg border p-3">
                        <p className="text-2xl font-bold text-foreground">{complaints.counts.total}</p>
                        <p className="text-xs text-muted-foreground">Total</p>
                    </div>
                </div>

                {complaints.recent.length > 0 ? (
                    <div className="space-y-2">
                        {complaints.recent.map((complaint) => (
                            <Link
                                key={complaint.id}
                                href={route('complaints.show', complaint.id)}
                                className="flex items-center justify-between p-3 border rounded-lg hover:shadow-sm transition-shadow"
                            >
                                <p className="text-sm font-medium text-foreground truncate pr-2">{complaint.subject}</p>
                                <Badge className={complaintStatusColor(complaint.status)}>
                                    {complaint.status.replace('_', ' ')}
                                </Badge>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground flex items-center gap-2">
                        <PlusCircle className="w-4 h-4" />
                        Nothing submitted yet.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
