import * as React from "react"
import { Download, FileText, FileSpreadsheet, FileImage, Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"

interface ExportButtonProps {
  exportUrl: string
  queryParams?: Record<string, unknown>
  className?: string
  variant?: "default" | "outline" | "ghost"
  size?: "default" | "sm" | "lg"
  disabled?: boolean
}

export function ExportButton({
  exportUrl,
  queryParams = {},
  className,
  variant = "outline",
  size = "sm",
  disabled = false,
}: ExportButtonProps) {
  const [isExporting, setIsExporting] = React.useState(false)

  const buildExportUrl = (format: string) => {
    const url = new URL(exportUrl, window.location.origin)

    // Add current query parameters
    Object.entries(queryParams).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        if (Array.isArray(value)) {
          value.forEach((item, index) => {
            url.searchParams.set(`${key}[${index}]`, String(item))
          })
        } else if (typeof value === 'object') {
          Object.entries(value).forEach(([subKey, subValue]) => {
            if (Array.isArray(subValue)) {
              subValue.forEach((item, index) => {
                url.searchParams.set(`${key}[${subKey}][${index}]`, String(item))
              })
            } else {
              url.searchParams.set(`${key}[${subKey}]`, String(subValue))
            }
          })
        } else {
          url.searchParams.set(key, String(value))
        }
      }
    })

    url.searchParams.set('format', format)
    return url.toString()
  }

  const handleExport = (format: string) => {
    if (isExporting) return

    setIsExporting(true)

    const link = document.createElement('a')
    link.href = buildExportUrl(format)
    link.style.display = 'none'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    // Reset loading state after 2 seconds
    setTimeout(() => {
      setIsExporting(false)
    }, 2000)
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant={variant}
          size={size}
          disabled={disabled || isExporting}
          className={cn("gap-2", className)}
        >
          {isExporting ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Download className="h-4 w-4" />
          )}
          {isExporting ? "Exporting..." : "Export"}
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-48">
        <DropdownMenuItem
          onClick={() => handleExport('csv')}
          className="cursor-pointer"
          disabled={isExporting}
        >
          <FileText className="h-4 w-4 mr-2" />
          CSV File
        </DropdownMenuItem>

        <DropdownMenuItem
          onClick={() => handleExport('excel')}
          className="cursor-pointer"
          disabled={isExporting}
        >
          <FileSpreadsheet className="h-4 w-4 mr-2" />
          Excel File
        </DropdownMenuItem>

        <DropdownMenuItem
          onClick={() => handleExport('pdf')}
          className="cursor-pointer"
          disabled={isExporting}
        >
          <FileImage className="h-4 w-4 mr-2" />
          PDF Report
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}


