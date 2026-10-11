import * as React from "react"
import { Link } from "@inertiajs/react"
import { Button, buttonVariants } from "@/components/ui/button"
import { useCan } from "@/lib/permissions"
import { cn } from "@/lib/utils"

/**
 * Reusable PageHeader component for consistent page layouts
 *
 * @example
 * <PageHeader
 *   title="Vehicles"
 *   description="Manage the fleet"
 *   actions={[
 *     {
 *       label: "Add Vehicle",
 *       icon: <Plus className="mr-2 h-4 w-4" />,
 *       href: "/vehicles/create",
 *     }
 *   ]}
 *   stats={[
 *     { label: "Total Vehicles", value: 150 },
 *     { label: "Active Vehicles", value: 120 },
 *   ]}
 * />
 */

interface StatCard {
  label: string
  value: string | number
  description?: string
  className?: string
}

interface ActionButton {
  label: string
  icon?: React.ReactNode
  onClick?: () => void
  href?: string
  variant?: "default" | "outline" | "ghost" | "destructive" | "secondary"
  className?: string
  /** Only show the button to users with this permission (any of, if an array) */
  permission?: string | string[]
}

interface PageHeaderProps {
  title: string
  description?: string
  actions?: ActionButton[]
  stats?: StatCard[]
  className?: string
  children?: React.ReactNode
}

export function PageHeader({
  title,
  description,
  actions: allActions = [],
  stats = [],
  className,
  children,
}: PageHeaderProps) {
  const can = useCan()
  const actions = allActions.filter((action) => can(action.permission))

  return (
    <div className={cn("space-y-6", className)}>
      {/* Header Section */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{title}</h1>
          {description && (
            <p className="text-muted-foreground mt-1">{description}</p>
          )}
        </div>

        {actions.length > 0 && (
          <div className="flex items-center gap-2">
            {actions.map((action, index) => (
              action.href ? (
                <Link
                  key={index}
                  href={action.href}
                  className={cn(
                    buttonVariants({
                      variant: action.variant || "default",
                      size: "default"
                    }),
                    "gap-2",
                    action.className
                  )}
                >
                  {action.icon}
                  {action.label}
                </Link>
              ) : (
                <Button
                  key={index}
                  variant={action.variant || "default"}
                  onClick={action.onClick}
                  className={cn("gap-2", action.className)}
                >
                  {action.icon}
                  {action.label}
                </Button>
              )
            ))}
          </div>
        )}
      </div>

      {/* Stats Cards */}
      {stats.length > 0 && (
        <div className={cn(
          "grid gap-4",
          stats.length === 1 && "grid-cols-1",
          stats.length === 2 && "md:grid-cols-2",
          stats.length === 3 && "md:grid-cols-3",
          stats.length === 4 && "md:grid-cols-4",
          stats.length > 4 && "md:grid-cols-4 lg:grid-cols-5"
        )}>
          {stats.map((stat, index) => (
            <div key={index} className={cn("rounded-lg border p-4", stat.className)}>
              <div className="text-2xl font-bold">{stat.value}</div>
              <p className="text-sm text-muted-foreground">{stat.label}</p>
              {stat.description && (
                <p className="text-xs text-muted-foreground mt-1">{stat.description}</p>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Custom Content */}
      {children}
    </div>
  )
}

// Convenience component for individual stat cards
export function StatCard({ label, value, description, className }: StatCard) {
  return (
    <div className={cn("rounded-lg border p-4", className)}>
      <div className="text-2xl font-bold">{value}</div>
      <p className="text-sm text-muted-foreground">{label}</p>
      {description && (
        <p className="text-xs text-muted-foreground mt-1">{description}</p>
      )}
    </div>
  )
}

// Keep backward compatibility
export default PageHeader
