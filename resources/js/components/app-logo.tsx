export default function AppLogo() {
    return (
        <>
            {/* Logo Container */}
            <img
                src="/images/favicon.png"
                alt="RSC Logo"
                className="size-10 object-contain p-0.5 transition-transform duration-200 hover:scale-110"
                onError={(e) => {
                    const target = e.target as HTMLImageElement;
                    target.style.display = 'none';
                    const fallback = target.parentElement?.querySelector('.logo-fallback') as HTMLElement;
                    if (fallback) fallback.style.display = 'flex';
                }}
            />
            <div
                className="logo-fallback flex size-6 items-center justify-center rounded-md bg-gradient-to-br from-blue-600 to-indigo-600 shadow-sm"
                style={{ display: 'none' }}
            >
                <span className="text-xs font-bold text-white">RSC</span>
            </div>

            {/* Text Content - Hidden when sidebar is collapsed */}
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate font-semibold text-sidebar-foreground">Vehicle Management</span>
                <span className="truncate text-xs text-sidebar-foreground/70">{/*Professional Fleet Solutions*/}</span>
            </div>
        </>
    );
}
