import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';

export function NotificationButton() {
    const { notifications } = usePage<SharedData>().props;
    const count = notifications.unread_count;

    const openNotification = (id: string, url: string | null) => {
        router.post(
            route('notifications.read', id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (url) router.visit(url);
                },
            },
        );
    };

    const markAllAsRead = () => {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="h-9 w-9 rounded-md relative">
                    <Bell className="h-4 w-4" />
                    {count > 0 && (
                        <span className="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-red-500 text-xs text-white flex items-center justify-center">
                            {count > 99 ? '99+' : count}
                        </span>
                    )}
                    <span className="sr-only">Notifications ({count})</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-80" align="end">
                <div className="flex items-center justify-between px-2 py-1.5">
                    <DropdownMenuLabel className="p-0">Notifications</DropdownMenuLabel>
                    {count > 0 && (
                        <button type="button" onClick={markAllAsRead} className="text-xs text-muted-foreground hover:underline">
                            Mark all read
                        </button>
                    )}
                </div>
                <DropdownMenuSeparator />
                {notifications.items.length === 0 ? (
                    <DropdownMenuItem disabled>
                        <div className="flex w-full flex-col items-center justify-center py-6 text-muted-foreground">
                            <Bell className="h-8 w-8 mb-2" />
                            <p className="text-sm">No notifications</p>
                        </div>
                    </DropdownMenuItem>
                ) : (
                    notifications.items.map((item) => (
                        <DropdownMenuItem
                            key={item.id}
                            className={!item.read ? 'bg-accent/50' : ''}
                            onClick={() => openNotification(item.id, item.url)}
                        >
                            <div className="flex flex-col space-y-1">
                                <p className="text-sm font-medium">{item.message}</p>
                                <p className="text-xs text-muted-foreground">{item.time}</p>
                            </div>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
